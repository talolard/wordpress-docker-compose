<?php

namespace VersionPress;

class VersionPress {
    

    public static function getVersion() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $pluginData = get_plugin_data(VERSIONPRESS_PLUGIN_DIR . "/versionpress.php", false, false);
        return $pluginData['Version'];
    }

    public static function isActive() {
        return defined('VERSIONPRESS_ACTIVATION_FILE') && file_exists(VERSIONPRESS_ACTIVATION_FILE);
    }
}