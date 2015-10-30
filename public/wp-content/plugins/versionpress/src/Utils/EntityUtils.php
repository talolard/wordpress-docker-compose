<?php

namespace VersionPress\Utils;

class EntityUtils {

    public static function getDiff($oldEntityData, $newEntityData) {
        $diff = array();
        foreach ($newEntityData as $key => $value) {
            if (!isset($oldEntityData[$key]) || $oldEntityData[$key] != $value) { 
                $diff[$key] = $value;
            }
        }

        return $diff;

    }

}