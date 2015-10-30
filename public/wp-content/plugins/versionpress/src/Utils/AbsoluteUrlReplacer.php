<?php

namespace VersionPress\Utils;

class AbsoluteUrlReplacer {

    const PLACEHOLDER = "<<[site-url]>>";
    private $siteUrl;
    private $replacedObjects = array();

    public function __construct($siteUrl) {
        $this->siteUrl = $siteUrl;
    }

    public function replace($entity) {
        $this->replacedObjects = array();

        foreach ($entity as $field => $value) {
            if ($field === "guid") continue; 
            if (isset($entity[$field])) {
                $entity[$field] = $this->replaceLocalUrls($value);
            }
        }
        return $entity;
    }

    public function restore($entity) {
        $this->replacedObjects = array();

        foreach ($entity as $field => $value) {
            if (isset($entity[$field])) {
                $entity[$field] = $this->replacePlaceholders($value);
            }
        }
        return $entity;
    }

    private function replaceLocalUrls($value) {
        if ($this->isSerializedValue($value)) {
            $unserializedValue = unserialize($value);
            $replacedValue = $this->replaceRecursively($unserializedValue, array($this, 'replaceLocalUrls'));
            return serialize($replacedValue);
        } else {
            return is_string($value) ? str_replace($this->siteUrl, self::PLACEHOLDER, $value) : $value;
        }
    }

    private function replacePlaceholders($value) {
        if ($this->isSerializedValue($value)) {
            $unserializedValue = unserialize($value);
            $replacedValue = $this->replaceRecursively($unserializedValue, array($this, 'replacePlaceholders'));
            return serialize($replacedValue);
        } else {
            return is_string($value) ? str_replace(self::PLACEHOLDER, $this->siteUrl, $value) : $value;
        }
    }

    private function isSerializedValue($value) {
        

        $test = @unserialize(($value)); 
        return $test !== false || $value === 'b:0;';
    }

    private function replaceRecursively($value, $replaceFn) {
        if (is_string($value)) {
            return call_user_func($replaceFn, $value);
        } else if (is_array($value)) {
            $tmp = array();
            foreach ($value as $key => $arrayValue) {
                $tmp[$key] = $this->replaceRecursively($arrayValue, $replaceFn);
            }
            return $tmp;
        } else if (is_object($value) && !in_array(spl_object_hash($value), $this->replacedObjects)) {
            $this->replacedObjects[] = spl_object_hash($value); 

            $r = new \ReflectionObject($value);
            $p = $r->getProperties();
            foreach ($p as $prop) {
                $prop->setAccessible(true);
                $prop->setValue($value, $this->replaceRecursively($prop->getValue($value), $replaceFn));
            }
            return $value;
        } else {
            return $value;
        }
    }
}
