<?php
use VersionPress\ChangeInfos\ChangeInfo;
use VersionPress\ChangeInfos\ChangeInfoEnvelope;
use VersionPress\ChangeInfos\TrackedChangeInfo;
use VersionPress\Git\ChangeInfoPreprocessors\ChangeInfoPreprocessor;
use VersionPress\Git\GitConfig;
use VersionPress\Git\GitRepository;
use VersionPress\Storages\Mirror;
use VersionPress\Storages\StorageFactory;
use VersionPress\Utils\FileSystem;

class Committer {

    private $mirror;

    private $forcedChangeInfos = array();
    

    private $repository;
    

    private $commitDisabled;
    

    private $commitPostponed;
    

    private $postponeKey;
    

    private $storageFactory;
    private $fileForPostpone = 'postponed-commits';
    private $postponedChangeInfos = array();

    public function __construct(Mirror $mirror, GitRepository $repository, StorageFactory $storageFactory) {
        $this->mirror = $mirror;
        $this->repository = $repository;
        $this->storageFactory = $storageFactory;
    }

    public function commit() {
        if ($this->commitDisabled) return;

        if (count($this->forcedChangeInfos) > 0) {
            $changeInfoList = $this->forcedChangeInfos;
        } elseif ($this->shouldCommit()) {
            $changeInfoList = array_merge($this->postponedChangeInfos, $this->mirror->getChangeList());
            if (empty($changeInfoList)) {
                return;
            }
        } else {
            return;
        }

        if ($this->commitPostponed) {
            $this->postponeChangeInfo($changeInfoList);
            $this->commitPostponed = false;
            $this->postponeKey = null;
            $this->flushChangeLists();
            return;
        }

        if (is_user_logged_in() && is_admin()) {
            $currentUser = wp_get_current_user();
            

            $authorName = $currentUser->display_name;
            

            $authorEmail = $currentUser->user_email;
        } else if (defined('WP_CLI') && WP_CLI) {
            $authorName = GitConfig::$wpcliUserName;
            $authorEmail = GitConfig::$wpcliUserEmail;
        } else {
            $authorName = "Non-admin action";
            $authorEmail = "nonadmin@example.com";
        }

        $changeInfoLists = $this->preprocessChangeInfoList($changeInfoList);

        if (count($this->forcedChangeInfos) === 1) {
            
            
            
            $this->stageRelatedFiles(new ChangeInfoEnvelope($this->mirror->getChangeList()));
        }

        foreach ($changeInfoLists as $listToCommit) {
            $changeInfoEnvelope = new ChangeInfoEnvelope($listToCommit);
            $this->stageRelatedFiles($changeInfoEnvelope);
            $this->repository->commit($changeInfoEnvelope->getCommitMessage(), $authorName, $authorEmail);
        }

        if (count($this->forcedChangeInfos) === 1 && $this->forcedChangeInfos[0] instanceof \VersionPress\ChangeInfos\WordPressUpdateChangeInfo) {
            FileSystem::remove(ABSPATH . 'versionpress.maintenance');
        }

        $this->flushChangeLists();
    }

    private function preprocessChangeInfoList($changeInfoList) {
        $preprocessors = array(
            'VersionPress\Git\ChangeInfoPreprocessors\PostChangeInfoPreprocessor',
            'VersionPress\Git\ChangeInfoPreprocessors\PostTermSplittingPreprocessor',
        );

        $changeInfoLists = array($changeInfoList);
        foreach ($preprocessors as $preprocessorClass) {
            

            $preprocessor = new $preprocessorClass();
            $processedLists = array();
            foreach ($changeInfoLists as $changeInfoList) {
                $processedLists = array_merge($processedLists, $preprocessor->process($changeInfoList));
            }
            $changeInfoLists = $processedLists;
        }

        return $changeInfoLists;
    }

    public function forceChangeInfo(TrackedChangeInfo $changeInfo) {
        $this->forcedChangeInfos[] = $changeInfo;
    }

    public function disableCommit() {
        $this->commitDisabled = true;
    }

    public function postponeCommit($key) {
        $this->commitPostponed = true;
        $this->postponeKey = $key;
    }

    public function discardPostponedCommit($key) {
        $postponed = $this->loadPostponedChangeInfos();
        if (isset($postponed[$key])) {
            unset($postponed[$key]);
            $this->savePostponedChangeInfos($postponed);
        }
    }

    public function usePostponedChangeInfos($key) {
        $postponed = $this->loadPostponedChangeInfos();
        if (isset($postponed[$key])) {
            $this->postponedChangeInfos = array_merge($this->postponedChangeInfos, $postponed[$key]);
            unset($postponed[$key]);
            $this->savePostponedChangeInfos($postponed);
        }
    }

    private function shouldCommit() {
        return !$this->existsMaintenanceFile();
    }

    private function existsMaintenanceFile() {
        $maintenanceFile = ABSPATH . 'versionpress.maintenance';
        return file_exists($maintenanceFile);
    }

    private function stageRelatedFiles($changeInfo) {
        if ($changeInfo instanceof ChangeInfoEnvelope) {
            

            foreach ($changeInfo->getChangeInfoList() as $subChangeInfo) {
                $this->stageRelatedFiles($subChangeInfo);
            }
            return;
        }

        $changes = $changeInfo->getChangedFiles();

        foreach ($changes as $change) {
            if ($change["type"] === "storage-file") {
                $entityName = $change["entity"];
                $entityId = $change["id"];
                $parentId = $change["parent-id"];
                $path = $this->storageFactory->getStorage($entityName)->getEntityFilename($entityId, $parentId);
            } elseif ($change["type"] === "path") {
                $path = $change["path"];
            } else {
                continue;
            }

            $this->repository->stageAll($path);
        }
    }

    private function postponeChangeInfo($changeInfoList) {
        $postponed = $this->loadPostponedChangeInfos();

        if (!isset($postponed[$this->postponeKey])) {
            $postponed[$this->postponeKey] = array();
        }

        $postponed[$this->postponeKey] = $changeInfoList;
        $this->savePostponedChangeInfos($postponed);
    }

    private function loadPostponedChangeInfos() {
        $file = VERSIONPRESS_TEMP_DIR . '/' . $this->fileForPostpone;
        if (is_file($file)) {
            $serializedPostponedChangeInfos = file_get_contents($file);
            return unserialize($serializedPostponedChangeInfos);
        }
        return array();
    }

    private function savePostponedChangeInfos($postponedChangeInfos) {
        $file = VERSIONPRESS_TEMP_DIR . '/' . $this->fileForPostpone;
        $serializedPostponedChangeInfos = serialize($postponedChangeInfos);
        file_put_contents($file, $serializedPostponedChangeInfos);
    }

    private function flushChangeLists() {
        $this->mirror->flushChangeList();
        $this->forcedChangeInfos = array();
    }
}
