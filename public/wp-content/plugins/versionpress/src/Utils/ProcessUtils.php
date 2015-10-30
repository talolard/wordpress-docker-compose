<?php

namespace VersionPress\Utils;

class ProcessUtils {

    public static function escapeshellarg($arg, $os = null) {

        if (!$os) {
            return escapeshellarg($arg);
        }

        if ($os == "windows") {
            return self::_drush_escapeshellarg_windows($arg);
        } else {
            return self::_drush_escapeshellarg_linux($arg);
        }

    }

    public static function _drush_escapeshellarg_linux($arg) {
        
        
        
        
        $arg = preg_replace('/\'/', '\'\\\'\'', $arg);

        $arg = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), ' ', $arg);

        $arg = "'" . $arg . "'";

        return $arg;
    }

    public static function _drush_escapeshellarg_windows($arg) {
        
        $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);

        $arg = preg_replace('/"/', '""', $arg);

        $arg = preg_replace('/%/', '%%', $arg);

        $arg = '"' . $arg . '"';

        return $arg;
    }
}
