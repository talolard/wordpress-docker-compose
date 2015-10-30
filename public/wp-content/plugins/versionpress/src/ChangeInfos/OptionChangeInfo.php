<?php
namespace VersionPress\ChangeInfos;

use Nette\Utils\Strings;
use VersionPress\Git\CommitMessage;
use VersionPress\Utils\StringUtils;

class OptionChangeInfo extends EntityChangeInfo {

    public function __construct($action, $entityId) {
        parent::__construct("option", $action, $entityId);
    }

    function getChangeDescription() {
        return Strings::capitalize(StringUtils::verbToPastTense($this->getAction())) . " option '{$this->getEntityId()}'";
    }

    static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $tags = $commitMessage->getVersionPressTags();
        $actionTag = $tags[TrackedChangeInfo::ACTION_TAG];
        list(, $action, $entityId) = explode("/", $actionTag, 3);
        return new self($action, $entityId);
    }

    public function getCustomTags() {
        return array();
    }

    public function getChangedFiles() {

        $result = parent::getChangedFiles();
        if ($this->getEntityId() == "rewrite_rules") {
            $result[] = array("type" => "path", "path" => ABSPATH . ".htaccess");
        }

        return $result;
    }
}
