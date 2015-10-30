<?php
namespace VersionPress\ChangeInfos;

use VersionPress\Git\CommitMessage;

abstract class TrackedChangeInfo implements ChangeInfo {

    const ACTION_TAG = "VP-Action";

    abstract function getEntityName();

    abstract function getAction();

    public function getCommitMessage() {
        return new CommitMessage($this->getChangeDescription(), $this->getCommitMessageBody());
    }

    private function getCommitMessageBody() {
        $actionTag = $this->getActionTagValue();

        $tags = array();
        if ($actionTag) {
            $tags[self::ACTION_TAG] = $actionTag;
        }

        $customTags = $this->getCustomTags();
        $tags = array_merge($tags, $customTags);

        $body = "";
        foreach ($tags as $tagName => $tagValue) {
            $body .= "$tagName: $tagValue\n";
        }
        return $body;
    }

    abstract protected function getActionTagValue();

    abstract public function getCustomTags();

    abstract public function getChangedFiles();

}
