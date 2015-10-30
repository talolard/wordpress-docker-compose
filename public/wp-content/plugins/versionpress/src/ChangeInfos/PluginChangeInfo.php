<?php

namespace VersionPress\ChangeInfos;
use Nette\Utils\Strings;
use VersionPress\Git\CommitMessage;
use VersionPress\Utils\StringUtils;

class PluginChangeInfo extends TrackedChangeInfo {

    private static $OBJECT_TYPE = "plugin";
    const PLUGIN_NAME_TAG = "VP-Plugin-Name";

    private $pluginFile;

    private $pluginName;

    private $action;

    public function __construct($pluginFile, $action, $pluginName = null) {
        $this->pluginFile = $pluginFile;
        $this->action = $action;
        $this->pluginName = $pluginName ? $pluginName : $this->findPluginName();
    }

    public function getEntityName() {
        return self::$OBJECT_TYPE;
    }

    public function getAction() {
        return $this->action;
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $actionTag = $commitMessage->getVersionPressTag(TrackedChangeInfo::ACTION_TAG);
        $pluginName = $commitMessage->getVersionPressTag(self::PLUGIN_NAME_TAG);
        list(, $action, $pluginFile) = explode("/", $actionTag, 3);
        return new self($pluginFile, $action, $pluginName);
    }

    public function getChangeDescription() {
        return Strings::capitalize(StringUtils::verbToPastTense($this->action)) . " plugin '{$this->pluginName}'";
    }

    protected function getActionTagValue() {
        return "{$this->getEntityName()}/{$this->getAction()}/" . $this->pluginFile;
    }

    public function getCustomTags() {
        return array(
            self::PLUGIN_NAME_TAG => $this->pluginName
        );
    }

    private function findPluginName() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = \get_plugins();
        return $plugins[$this->pluginFile]["Name"];
    }

    public function getChangedFiles() {
        $path = WP_CONTENT_DIR . "/plugins/";
        if (dirname($this->pluginFile) == ".") {
            
            $path .= $this->pluginFile;
        } else {
            
            $path .= dirname($this->pluginFile) . "/*";
        }
        $pluginChange = array("type" => "path", "path" => $path);

        $optionChange = array("type" => "storage-file", "entity" => "option", "id" => "", "parent-id" => "");

        return array($pluginChange, $optionChange);
    }
}
