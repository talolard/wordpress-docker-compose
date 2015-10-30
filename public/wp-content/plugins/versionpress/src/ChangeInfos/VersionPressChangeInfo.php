<?php
namespace VersionPress\ChangeInfos;

use Nette\Utils\Strings;
use VersionPress\Git\CommitMessage;
use VersionPress\Utils\StringUtils;

class VersionPressChangeInfo extends TrackedChangeInfo {

    private $action;
    private $versionPressVersion;

    function __construct($action, $versionPressVersion = null) {
        $this->action = $action;
        $this->versionPressVersion = $versionPressVersion;
    }

    public function getEntityName() {
        return "versionpress";
    }

    public function getAction() {
        return $this->action;
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $actionTag = $commitMessage->getVersionPressTag(TrackedChangeInfo::ACTION_TAG);
        list(, $action, $versionPressVersion) = array_pad(explode("/", $actionTag, 3), 3, "");
        return new self($action, $versionPressVersion);
    }

    public function getChangeDescription() {

        switch ($this->action) {

            case "install":
                
                return "Installed VersionPress";

            case "activate":
                return "Activated VersionPress " . $this->versionPressVersion;

            case "deactivate":
                return "Deactivated VersionPress";

            default:
                
                return Strings::capitalize(StringUtils::verbToPastTense($this->action)) . " VersionPress";

        }

    }

    protected function getActionTagValue() {
        $actionTag = "versionpress/$this->action";
        if ($this->versionPressVersion) {
            $actionTag .= "/" . $this->versionPressVersion;
        }
        return $actionTag;
    }

    public function getCustomTags() {
        return array();
    }

    public function getChangedFiles() {
        switch ($this->action) {
            case "deactivate":
                return array(
                    array("type" => "path", "path" => VERSIONPRESS_MIRRORING_DIR . "/*"),
                    array("type" => "path", "path" => ABSPATH . WPINC . "/wp-db.php"),
                    array("type" => "path", "path" => ABSPATH . WPINC . "/wp-db.php.original"),
                );
            default:
                return array(array("type" => "path", "path" => "*"));
        }
    }
}
