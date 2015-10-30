<?php

namespace VersionPress\Utils;

class SecurityUtils {

    public static function protectDirectory($path) {
        $templatesLocation = __DIR__ . "/../Initialization";
        FileSystem::copy("$templatesLocation/.htaccess.tpl", "$path/.htaccess");
        FileSystem::copy("$templatesLocation/web.tpl.config", "$path/web.config");
    }
}
