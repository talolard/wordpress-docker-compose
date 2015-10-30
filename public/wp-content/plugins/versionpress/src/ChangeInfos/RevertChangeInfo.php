<?php
namespace VersionPress\ChangeInfos;

use VersionPress\DI\VersionPressServices;
use VersionPress\Git\CommitMessage;
use VersionPress\Git\GitRepository;

class RevertChangeInfo extends TrackedChangeInfo {

    const OBJECT_TYPE = "versionpress";
    const ACTION_UNDO = "undo";
    const ACTION_ROLLBACK = "rollback";

    private $action;

    private $commitHash;

    function __construct($action, $commitHash) {
        $this->action = $action;
        $this->commitHash = $commitHash;
    }

    public function getEntityName() {
        return self::OBJECT_TYPE;
    }

    public function getAction() {
        return $this->action;
    }

    public function getCommitHash() {
        return $this->commitHash;
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $tags = $commitMessage->getVersionPressTags();
        list(, $action, $commitHash) = explode("/", $tags[TrackedChangeInfo::ACTION_TAG], 3);
        return new self($action, $commitHash);
    }

    public function getChangeDescription() {
        global $versionPressContainer; 
        

        $repository = $versionPressContainer->resolve(VersionPressServices::REPOSITORY);
        $revertedCommit = $repository->getCommit($this->commitHash);

        if ($this->action === self::ACTION_UNDO) {
            $revertedChangeInfo = ChangeInfoMatcher::buildChangeInfo($revertedCommit->getMessage());
            return sprintf("Reverted change \"%s\"", $revertedChangeInfo->getChangeDescription());
        }

        return sprintf("Rollback to the same state as of %s", $revertedCommit->getDate()->format('d-M-y H:i:s'));
    }

    protected function getActionTagValue() {
        return sprintf("%s/%s/%s", self::OBJECT_TYPE, $this->getAction(), $this->commitHash);
    }

    public function getCustomTags() {
        return array();
    }

    public function getChangedFiles() {
        return array(array("type" => "path", "path" => "*"));
    }
}
