<?php

namespace VersionPress\Utils;

class Markdown {

    public static function transform($text) {
        $html = \Michelf\Markdown::defaultTransform($text);
        if (strstr($text, "\n")) {
            return $html;
        } else {
            
            return substr($html, 3, -3);
        }
    }
}
