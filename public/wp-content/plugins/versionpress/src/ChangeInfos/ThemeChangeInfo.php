<?php

namespace VersionPress\ChangeInfos;
use Nette\Utils\Strings;
use VersionPress\Git\CommitMessage;
use VersionPress\Utils\StringUtils;

class ThemeChangeInfo extends TrackedChangeInfo {

    private static $OBJECT_TYPE = "theme";
    const THEME_NAME_TAG = "VP-Theme-Name";

    private $themeId;

    private $themeName;

    private $action;

    public function __construct($themeId, $action, $themeName = null) {
        $this->themeId = $themeId;
        $this->action = $action;

        if ($themeName == null) {
            $themes = wp_get_themes();
            $themeName = $themes[$themeId]->name;
        }

        $this->themeName = $themeName;
    }

    public function getEntityName() {
        return self::$OBJECT_TYPE;
    }

    public function getAction() {
        return $this->action;
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $actionTag = $commitMessage->getVersionPressTag(TrackedChangeInfo::ACTION_TAG);
        $themeName = $commitMessage->getVersionPressTag(self::THEME_NAME_TAG);
        list(, $action, $themeId) = explode("/", $actionTag, 3);
        return new self($themeId, $action, $themeName);
    }

    public function getChangeDescription() {

        if ($this->action === 'switch') {
            return "Theme switched to '{$this->themeName}'";
        }

        return Strings::capitalize(StringUtils::verbToPastTense($this->action)) . " theme '{$this->themeName}'";
    }

    public function getChangedFiles() {
        $themeChange = array("type" => "path", "path" => $path = WP_CONTENT_DIR . "/themes/" . $this->themeId . "/*");
        $optionChange = array("type" => "storage-file", "entity" => "option", "id" => "", "parent-id" => "");
        return array($themeChange, $optionChange);
    }

    protected function getActionTagValue() {
        return "{$this->getEntityName()}/{$this->getAction()}/" . $this->themeId;
    }

    public function getCustomTags() {
        return array(
            self::THEME_NAME_TAG => $this->themeName
        );
    }
}
