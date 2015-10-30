<?php

namespace VersionPress\Utils;

use Nette\Utils\Strings;

class StringUtils {

    public static function verbToPastTense($verb) {
        return $verb . (Strings::endsWith($verb, "e") ? "d" : "ed");
    }

    public static function crlfize($str) {
        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $str));
    }

    public static function pluralize($string) {
        $plural = array(
            '/(quiz)$/i' => "$1zes",
            '/^(ox)$/i' => "$1en",
            '/([m|l])ouse$/i' => "$1ice",
            '/(matr|vert|ind)ix|ex$/i' => "$1ices",
            '/(x|ch|ss|sh)$/i' => "$1es",
            '/([^aeiouy]|qu)y$/i' => "$1ies",
            '/(hive)$/i' => "$1s",
            '/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
            '/(shea|lea|loa|thie)f$/i' => "$1ves",
            '/sis$/i' => "ses",
            '/([ti])um$/i' => "$1a",
            '/(tomat|potat|ech|her|vet)o$/i' => "$1oes",
            '/(bu)s$/i' => "$1ses",
            '/(alias)$/i' => "$1es",
            '/(octop)us$/i' => "$1i",
            '/(ax|test)is$/i' => "$1es",
            '/(us)$/i' => "$1es",
            '/s$/i' => "s",
            '/$/' => "s"
        );

        $irregular = array(
            'move' => 'moves',
            'foot' => 'feet',
            'goose' => 'geese',
            'sex' => 'sexes',
            'child' => 'children',
            'man' => 'men',
            'tooth' => 'teeth',
            'person' => 'people',
            'valve' => 'valves'
        );

        $uncountable = array(
            'sheep',
            'fish',
            'deer',
            'series',
            'species',
            'money',
            'rice',
            'information',
            'equipment'
        );

        if (in_array(strtolower($string), $uncountable))
            return $string;

        foreach ($irregular as $pattern => $result) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        foreach ($plural as $pattern => $result) {
            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        return $string;
    }
}
