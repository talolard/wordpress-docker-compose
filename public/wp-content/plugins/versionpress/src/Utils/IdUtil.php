<?php

namespace VersionPress\Utils;

final class IdUtil {

    static function newId() {
        return self::newUuid('%04x%04x%04x%04x%04x%04x%04x%04x');
    }

    public static function newUuid($formatString = '%04x%04x-%04x-%04x-%04x-%04x%04x%04x') {
        return strtoupper(sprintf($formatString,
            
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            mt_rand(0, 0xffff),

            mt_rand(0, 0x0fff) | 0x4000,

            mt_rand(0, 0x3fff) | 0x8000,

            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        ));
    }

    private function __construct() {
    }
}