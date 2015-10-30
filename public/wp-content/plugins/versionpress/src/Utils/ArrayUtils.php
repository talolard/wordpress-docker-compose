<?php

namespace VersionPress\Utils;

class ArrayUtils {

    public static function parametrize($array) {
        $out = array();
        foreach ($array as $key => $value)
            $out[] = "$key=$value";
        return $out;
    }

    public static function stablesort(&$array, $cmp_function = 'strcmp') {
        
        if (count($array) < 2) return;
        
        $halfway = count($array) / 2;
        $array1 = array_slice($array, 0, $halfway);
        $array2 = array_slice($array, $halfway);
        
        self::stablesort($array1, $cmp_function);
        self::stablesort($array2, $cmp_function);
        
        if (call_user_func($cmp_function, end($array1), $array2[0]) < 1) {
            $array = array_merge($array1, $array2);
            return;
        }
        
        $array = array();
        $ptr1 = $ptr2 = 0;
        while ($ptr1 < count($array1) && $ptr2 < count($array2)) {
            if (call_user_func($cmp_function, $array1[$ptr1], $array2[$ptr2]) < 1) {
                $array[] = $array1[$ptr1++];
            } else {
                $array[] = $array2[$ptr2++];
            }
        }
        
        while ($ptr1 < count($array1)) $array[] = $array1[$ptr1++];
        while ($ptr2 < count($array2)) $array[] = $array2[$ptr2++];
        return;
    }

    public static function isAssociative($array) {
        if (!is_array($array)) {
            return false;
        }
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    public static function any($array, $predicate) {
        foreach ($array as $item) {
            if ($predicate($item)) {
                return true;
            }
        }

        return false;
    }

    public static function column($array, $columnKey, $indexKey = null) {
        if (function_exists('array_column')) {
            return call_user_func_array('array_column', func_get_args());
        }

        $argc = func_num_args();
        $params = func_get_args();
        if ($argc < 2) {
            trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
            return null;
        }
        if (!is_array($params[0])) {
            trigger_error('array_column() expects parameter 1 to be array, ' . gettype($params[0]) . ' given', E_USER_WARNING);
            return null;
        }
        if (!is_int($params[1])
            && !is_float($params[1])
            && !is_string($params[1])
            && $params[1] !== null
            && !(is_object($params[1]) && method_exists($params[1], '__toString'))
        ) {
            trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
            return false;
        }
        if (isset($params[2])
            && !is_int($params[2])
            && !is_float($params[2])
            && !is_string($params[2])
            && !(is_object($params[2]) && method_exists($params[2], '__toString'))
        ) {
            trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
            return false;
        }
        $paramsInput = $params[0];
        $paramsColumnKey = ($params[1] !== null) ? (string) $params[1] : null;
        $paramsIndexKey = null;
        if (isset($params[2])) {
            if (is_float($params[2]) || is_int($params[2])) {
                $paramsIndexKey = (int) $params[2];
            } else {
                $paramsIndexKey = (string) $params[2];
            }
        }
        $resultArray = array();
        foreach ($paramsInput as $row) {
            $key = $value = null;
            $keySet = $valueSet = false;
            if ($paramsIndexKey !== null && array_key_exists($paramsIndexKey, $row)) {
                $keySet = true;
                $key = (string) $row[$paramsIndexKey];
            }
            if ($paramsColumnKey === null) {
                $valueSet = true;
                $value = $row;
            } elseif (is_array($row) && array_key_exists($paramsColumnKey, $row)) {
                $valueSet = true;
                $value = $row[$paramsColumnKey];
            }
            if ($valueSet) {
                if ($keySet) {
                    $resultArray[$key] = $value;
                } else {
                    $resultArray[] = $value;
                }
            }
        }
        return $resultArray;
    }

    public static function mapreduce($data, $mapFn, $reduceFn) {
        $mapResult = array();
        $reduceResult = array();

        $mapEmit = function ($key, $value) use (&$mapResult) {
          $mapResult[$key][] = $value;
        };

        $reduceEmit = function ($obj) use (&$reduceResult) {
          $reduceResult[] = $obj;
        };

        foreach ($data as $item) {
            $mapFn($item, $mapEmit);
        }

        foreach ($mapResult as $key => $value) {
            $reduceFn($key, $mapResult[$key], $reduceEmit);
        }

        return $reduceResult;
    }

    public static function map($mapFn, $array) {
        return array_map(function ($key) use ($mapFn, $array) {
            return $mapFn($array[$key], $key);
        }, array_keys($array));
    }
}
