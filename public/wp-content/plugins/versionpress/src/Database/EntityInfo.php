<?php

namespace VersionPress\Database;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;

class EntityInfo {

    public $entityName;

    public $tableName;

    public $idColumnName;

    public $vpidColumnName;

    public $usesGeneratedVpids;

    public $hasNaturalVpid;

    public $references = array();

    public $mnReferences = array();

    public $valueReferences = array();

    public $hasReferences = false;

    private $virtualReferences = array();

    public function __construct($entitySchema) {
        list($key) = array_keys($entitySchema);
        $this->entityName = $key;

        $schemaInfo = $entitySchema[$key];

        if (isset($schemaInfo['table'])) {
            $this->tableName = $schemaInfo['table'];
        } else {
            $this->tableName = $this->entityName;
        }

        if (isset($schemaInfo['id'])) {
            $this->idColumnName = $schemaInfo['id'];
            $this->vpidColumnName = 'vp_id'; 
            $this->usesGeneratedVpids = true;
            $this->hasNaturalVpid = false;
        } else {
            $this->idColumnName = $schemaInfo['vpid'];
            $this->vpidColumnName = $schemaInfo['vpid'];
            $this->usesGeneratedVpids = false;
            $this->hasNaturalVpid = true;
        }

        if (isset($schemaInfo['references'])) {
            $this->references = $schemaInfo['references'];
            $this->hasReferences = true;
        }

        if (isset($schemaInfo['mn-references'])) {
            foreach ($schemaInfo['mn-references'] as $reference => $targetEntity) {
                if (Strings::startsWith($reference, '@')) {
                    $reference = Strings::substring($reference, 1);
                    $this->virtualReferences[$reference] = true;
                }
                $this->mnReferences[$reference] = $targetEntity;
            }
            $this->hasReferences = true;
        }

        if (isset($schemaInfo['value-references'])) {
            foreach ($schemaInfo['value-references'] as $key => $references) {
                list($keyCol, $valueCol) = explode('@', $key);
                foreach($references as $reference => $targetEntity) {
                    $key = $keyCol . '=' . $reference . '@' . $valueCol;
                    $this->valueReferences[$key] = $targetEntity;
                }
            }
            $this->hasReferences = true;
        }
    }

    public function isVirtualReference($reference) {
        return isset($this->virtualReferences[$reference]);
    }
}
