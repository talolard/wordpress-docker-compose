<?php

namespace VersionPress\ChangeInfos;

use Nette\NotSupportedException;
use Nette\Utils\Strings;
use VersionPress\Git\CommitMessage;
use VersionPress\Utils\StringUtils;

abstract class BulkChangeInfo implements ChangeInfo {

    protected $changeInfos;
    

    protected $count;

    public function __construct(array $changeInfos) {
        $this->changeInfos = $changeInfos;
        $this->count = $this->countUniqueChanges($changeInfos);
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        throw new NotSupportedException("Building bulk changeinfo from commit message is not supported");
    }

    public function getCommitMessage() {
        throw new NotSupportedException("Commit message is created in ChangeInfoEnvelope from original objects");
    }

    public function getChangeInfos() {
        return $this->changeInfos;
    }

    public function getChangeDescription() {
        if ($this->count === 1) {
            return $this->changeInfos[0]->getChangeDescription();
        }

        return sprintf("%s %d %s",
            Strings::capitalize(StringUtils::verbToPastTense($this->getAction())),
            $this->count,
            StringUtils::pluralize($this->getEntityName()));
    }

    public function getAction() {
        return $this->changeInfos[0]->getAction();
    }

    public function getEntityName() {
        return $this->changeInfos[0]->getEntityName();
    }

    private function countUniqueChanges($changeInfos) {
        if (!($changeInfos[0] instanceof EntityChangeInfo)) {
            return count($changeInfos);
        }

        $numberOfUniqueChanges = 0;
        $uniqueEntities = array();

        foreach ($changeInfos as $changeInfo) {
            if (!in_array($changeInfo->getEntityId(), $uniqueEntities)) {
                $numberOfUniqueChanges += 1;
                $uniqueEntities[] = $changeInfo->getEntityId();
            }
        }

        return $numberOfUniqueChanges;
    }
}