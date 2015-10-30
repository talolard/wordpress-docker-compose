<?php
namespace VersionPress\Initialization;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use VersionPress\ChangeInfos\VersionPressChangeInfo;
use VersionPress\Database\DbSchemaInfo;
use VersionPress\Database\VpidRepository;
use VersionPress\Git\GitConfig;
use VersionPress\Git\GitRepository;
use VersionPress\Storages\DirectoryStorage;
use VersionPress\Storages\MetaEntityStorage;
use VersionPress\Storages\SingleFileStorage;
use VersionPress\Storages\Storage;
use VersionPress\Storages\StorageFactory;
use VersionPress\Synchronizers\SynchronizationProcess;
use VersionPress\Utils\AbsoluteUrlReplacer;
use VersionPress\Utils\ArrayUtils;
use VersionPress\Utils\FileSystem;
use VersionPress\Utils\IdUtil;
use VersionPress\Utils\SecurityUtils;
use VersionPress\VersionPress;
use wpdb;

class Initializer {

    public $onProgressChanged = array();

    private $database;

    private $dbSchema;

    private $storageFactory;

    private $isDatabaseLocked;

    private $repository;

    private $urlReplacer;

    private $vpidRepository;
    private $idCache;
    private $executionStartTime;

    function __construct($wpdb, DbSchemaInfo $dbSchema, StorageFactory $storageFactory, GitRepository $repository, AbsoluteUrlReplacer $urlReplacer, VpidRepository $vpidRepository) {
        $this->database = $wpdb;
        $this->dbSchema = $dbSchema;
        $this->storageFactory = $storageFactory;
        $this->repository = $repository;
        $this->urlReplacer = $urlReplacer;
        $this->vpidRepository = $vpidRepository;
        $this->executionStartTime = microtime(true);
    }

    public function initializeVersionPress() {
        

        @set_time_limit(0); 
        $this->adjustGitProcessTimeout();

        $this->reportProgressChange(InitializerStates::START);
        vp_enable_maintenance();
        try {
            $this->createVersionPressTables();
            $this->lockDatabase();
            $this->saveDatabaseToStorages();
            $this->commitDatabase();
            $this->createGitRepository();
            $this->activateVersionPress();
            $this->copyAccessRulesFiles();
            $this->doInitializationCommit();
            vp_disable_maintenance();
            $this->reportProgressChange(InitializerStates::FINISHED);
        } catch (InitializationAbortedException $ex) {
            $this->reportProgressChange(InitializerStates::ABORTED);
        }
    }

    public function createVersionPressTables() {
        $table_prefix = $this->database->prefix;
        $process = array();

        $process[] = "DROP TABLE IF EXISTS `{$table_prefix}vp_id`";
        $process[] = "CREATE TABLE `{$table_prefix}vp_id` (
          `vp_id` BINARY(16) NOT NULL,
          `table` VARCHAR(64) NOT NULL,
          `id` BIGINT(20) NOT NULL,
          PRIMARY KEY (`vp_id`),
          UNIQUE KEY `table_id` (`table`,`id`),
          KEY `id` (`id`)
        ) ENGINE=InnoDB;";

        foreach ($process as $query) {
            $this->database->query($query);
        }

        $this->reportProgressChange(InitializerStates::DB_TABLES_CREATED);
    }

    private function lockDatabase() {
        return; 
        

        $entityNames = $this->dbSchema->getAllEntityNames();
        $dbSchema = $this->dbSchema;
        $tableNames = array_map(function ($entityName) use ($dbSchema) {
            return "`{$dbSchema->getPrefixedTableName($entityName)}`";
        }, $entityNames);

        $lockQueries = array();
        $lockQueries[] = "FLUSH TABLES " . join(",", $tableNames) . " WITH READ LOCK;";
        $lockQueries[] = "SET AUTOCOMMIT=0;";
        $lockQueries[] = "START TRANSACTION;";

        register_shutdown_function(array('self', 'rollbackDatabase'));

        foreach ($lockQueries as $lockQuery)
            $this->database->query($lockQuery);

        $this->isDatabaseLocked = true;
    }

    private function createVpidsForEntitiesOfType($entityName) {

        if (!$this->dbSchema->getEntityInfo($entityName)->usesGeneratedVpids) {
            return;
        }

        $idColumnName = $this->dbSchema->getEntityInfo($entityName)->idColumnName;
        $tableName = $this->dbSchema->getTableName($entityName);
        $prefixedTableName = $this->dbSchema->getPrefixedTableName($entityName);
        $entities = $this->database->get_results("SELECT * FROM $prefixedTableName", ARRAY_A);
        $entities = $this->replaceForeignKeysWithReferencesInAllEntities($entityName, $entities);

        $storage = $this->storageFactory->getStorage($entityName);
        $entities = array_filter($entities, function ($entity) use ($storage) { return $storage->shouldBeSaved($entity); });
        $chunks = array_chunk($entities, 1000);
        $this->idCache[$entityName] = array();

        foreach ($chunks as $entitiesInChunk) {
            $wordpressIds = ArrayUtils::column($entitiesInChunk, $idColumnName);
            $vpIds = array_map(array('VersionPress\Utils\IdUtil', 'newId'), $entitiesInChunk);
            $idPairs = array_combine($wordpressIds, $vpIds);
            $this->idCache[$entityName] = $this->idCache[$entityName] + $idPairs; 
            $sqlValues = join(', ', ArrayUtils::map(function ($vpId, $id) use ($tableName) { return "('$tableName', $id, UNHEX('$vpId'))"; }, $idPairs));
            $query = "INSERT INTO {$this->getTableName('vp_id')} (`table`, id, vp_id) VALUES $sqlValues";
            $this->database->query($query);
            $this->checkTimeout();
        }
    }

    private function saveDatabaseToStorages() {

        if (is_dir(VERSIONPRESS_MIRRORING_DIR)) {
            FileSystem::remove(VERSIONPRESS_MIRRORING_DIR);
        }

        FileSystem::mkdir(VERSIONPRESS_MIRRORING_DIR);

        $entityNames = SynchronizationProcess::sortStoragesToSynchronize($this->storageFactory->getAllSupportedStorages());
        foreach ($entityNames as $entityName) {
            $this->createVpidsForEntitiesOfType($entityName);
            $this->saveEntitiesOfTypeToStorage($entityName);
            $this->reportProgressChange("All " . $entityName . " saved into files");
        }
    }

    private function saveEntitiesOfTypeToStorage($entityName) {
        $storage = $this->storageFactory->getStorage($entityName);

        $entities = $this->getEntitiesFromDatabase($entityName);
        $entities = $this->replaceForeignKeysWithReferencesInAllEntities($entityName, $entities);

        $entities = array_values(array_filter($entities, function ($entity) use ($storage) {
            return $storage->shouldBeSaved($entity);
        }));

        $urlReplacer = $this->urlReplacer;
        $entities = $this->extendEntitiesWithVpids($entityName, $entities);
        $entities = array_map(function ($entity) use ($urlReplacer) { return $urlReplacer->replace($entity); }, $entities);
        $entities = $this->doEntitySpecificActions($entityName, $entities);
        $storage->prepareStorage();

        if ($storage instanceof DirectoryStorage) {
            $this->saveDirectoryStorageEntities($storage, $entities);
        }

        if ($storage instanceof SingleFileStorage) {
            $this->saveSingleFileStorageEntities($storage, $entities);
        }

        if ($storage instanceof MetaEntityStorage) {
            $entityInfo = $this->dbSchema->getEntityInfo($entityName);
            reset($entityInfo->references);
            $parentReference = "vp_" . key($entityInfo->references);

            $this->saveMetaEntities($storage, $entities, $parentReference);
        }
    }

    private function saveDirectoryStorageEntities(DirectoryStorage $storage, $entities) {
        foreach ($entities as $entity) {
            $storage->save($entity);
            $this->checkTimeout();
        }
    }

    private function saveSingleFileStorageEntities(SingleFileStorage $storage, $entities) {
        foreach ($entities as $entity) {
            $storage->saveLater($entity);
        }
        $storage->commit();
        $this->checkTimeout();
    }

    private function saveMetaEntities(MetaEntityStorage $storage, $entities, $parentReference) {
        if (count($entities) == 0) {
            return;
        }

        $lastParent = $entities[0][$parentReference];
        foreach ($entities as $entity) {
            if ($entity[$parentReference] !== $lastParent) {
                $storage->commit();
                $this->checkTimeout();
            }
            $storage->saveLater($entity);
        }
        $storage->commit();
    }

    private function replaceForeignKeysWithReferencesInAllEntities($entityName, $entities) {
        $vpidRepository = $this->vpidRepository;
        return array_map(function ($entity) use ($vpidRepository, $entityName) {
            return $vpidRepository->replaceForeignKeysWithReferences($entityName, $entity);
        }, $entities);
    }

    private function extendEntitiesWithVpids($entityName, $entities) {
        if (!$this->dbSchema->getEntityInfo($entityName)->usesGeneratedVpids) {
            return $entities;
        }

        $idColumnName = $this->dbSchema->getEntityInfo($entityName)->idColumnName;
        $idCache = $this->idCache;

        $entities = array_map(function ($entity) use ($entityName, $idColumnName, $idCache) {
            $entity['vp_id'] = $idCache[$entityName][intval($entity[$idColumnName])];
            return $entity;
        }, $entities);

        return $entities;
    }

    private function doEntitySpecificActions($entityName, $entities) {
        if ($entityName === 'post') {
            return array_map(array($this, 'extendPostWithTaxonomies'), $entities);
        }
        if ($entityName === 'usermeta') {
            return array_map(array($this, 'restoreUserIdInUsermeta'), $entities);
        }
        return $entities;
    }

    private function extendPostWithTaxonomies($post) {
        $idColumnName = $this->dbSchema->getEntityInfo('post')->idColumnName;
        $id = $post[$idColumnName];

        $postType = $post['post_type'];
        $taxonomies = get_object_taxonomies($postType);

        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($id, $taxonomy);
            if ($terms) {
                $idCache = $this->idCache;
                $referencedTaxonomies = array_map(function ($term) use ($idCache) {
                    return $idCache['term_taxonomy'][$term->term_taxonomy_id];
                }, $terms);

                $currentTaxonomies = isset($post['vp_term_taxonomy']) ? $post['vp_term_taxonomy'] : array();
                $post['vp_term_taxonomy'] = array_merge($currentTaxonomies, $referencedTaxonomies);
            }
        }

        return $post;
    }

    private function restoreUserIdInUsermeta($usermeta) {
        $userIds = $this->idCache['user'];
        foreach ($userIds as $userId => $vpId) {
            if (strval($vpId) === strval($usermeta['vp_user_id'])) {
                $usermeta['user_id'] = $userId;
                return $usermeta;
            }
        }

        return $usermeta;
    }

    private function rollbackDatabase() {
        if ($this->isDatabaseLocked) {
            $this->database->query("ROLLBACK");
            $this->database->query("UNLOCK TABLES");
            $this->isDatabaseLocked = false;
        }
    }

    private function commitDatabase() {
        if ($this->isDatabaseLocked) {
            $this->database->query("COMMIT");
            $this->database->query("UNLOCK TABLES");
            $this->isDatabaseLocked = false;
        }

        $this->reportProgressChange(InitializerStates::DB_WORK_DONE);
    }

    private function createGitRepository() {
        if (!$this->repository->isVersioned()) {
            $this->reportProgressChange(InitializerStates::CREATING_GIT_REPOSITORY);
            $this->repository->init();
            $this->installGitignore();
        }
    }

    private function activateVersionPress() {
        WpdbReplacer::replaceMethods();
        touch(VERSIONPRESS_ACTIVATION_FILE);
        $this->reportProgressChange(InitializerStates::VERSIONPRESS_ACTIVATED);
    }

    private function doInitializationCommit() {
        $this->checkTimeout();

        $lastCommitHash = $this->repository->getLastCommitHash();
        file_put_contents(VERSIONPRESS_ACTIVATION_FILE, $lastCommitHash);

        $this->reportProgressChange(InitializerStates::CREATING_INITIAL_COMMIT);
        $installationChangeInfo = new VersionPressChangeInfo("activate", VersionPress::getVersion());

        $currentUser = wp_get_current_user();
        

        $authorName = $currentUser->display_name;
        

        $authorEmail = $currentUser->user_email;

        if (defined('WP_CLI') && WP_CLI) {
            $authorName = GitConfig::$wpcliUserName;
            $authorEmail = GitConfig::$wpcliUserEmail;
        }

        try {
            $this->repository->stageAll();
            $this->repository->commit($installationChangeInfo->getCommitMessage(), $authorName, $authorEmail);
        } catch (ProcessTimedOutException $ex) {
            $this->abortInitialization();
        }
    }

    private function reportProgressChange($message) {
        foreach ($this->onProgressChanged as $listener) {
            call_user_func($listener, $message);
        }
    }

    private function getTableName($entityName) {
        return $this->dbSchema->getPrefixedTableName($entityName);
    }

    private function copyAccessRulesFiles() {
        SecurityUtils::protectDirectory(ABSPATH . "/.git");
        SecurityUtils::protectDirectory(VERSIONPRESS_MIRRORING_DIR);
    }

    private function installGitignore() {
        FileSystem::copy(__DIR__ . '/.gitignore.tpl', ABSPATH . '.gitignore', false);
    }

    private function adjustGitProcessTimeout() {
        $maxExecutionTime = ini_get('max_execution_time');
        $processTimeout = $maxExecutionTime > 0 ? $maxExecutionTime / 2 : 5 * 60;
        $this->repository->setGitProcessTimeout($processTimeout);
    }

    private function checkTimeout() {
        if ($this->timeoutIsClose()) {
            $this->abortInitialization();
        }
    }

    private function timeoutIsClose() {
        $maxExecutionTime = intval(ini_get('max_execution_time'));

        if ($maxExecutionTime === 0) {
            return false;
        }

        $executionTime = microtime(true) - $this->executionStartTime;
        $remainingTime = $maxExecutionTime - $executionTime;

        return $remainingTime < 3; 
    }

    private function abortInitialization() {
        vp_disable_maintenance();
        if (VersionPress::isActive()) {
            @unlink(WP_CONTENT_DIR . '/db.php');
            @unlink(VERSIONPRESS_ACTIVATION_FILE);
        }
        throw new InitializationAbortedException();
    }

    private function getEntitiesFromDatabase($entityName) {
        if ($this->storageFactory->getStorage($entityName) instanceof MetaEntityStorage) {
            $entityInfo = $this->dbSchema->getEntityInfo($entityName);
            $parentReference = key($entityInfo->references);

            return $this->database->get_results("SELECT * FROM {$this->getTableName($entityName)} ORDER BY {$parentReference}", ARRAY_A);
        }

        return $this->database->get_results("SELECT * FROM {$this->getTableName($entityName)}", ARRAY_A);
    }
}
