<?php
namespace VersionPress\ChangeInfos;

use VersionPress\Git\CommitMessage;

class UntrackedChangeInfo implements ChangeInfo {

    private $commitMessage;

    public function __construct(CommitMessage $commitMessage) {
        $this->commitMessage = $commitMessage;
    }

    public function getCommitMessage() {
        return $this->commitMessage;
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        return new self($commitMessage);
    }

    public function getChangeDescription() {
        return $this->commitMessage->getSubject();
    }

}
