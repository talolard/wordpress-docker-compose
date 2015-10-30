<?php
namespace VersionPress\Database;
use DateTime;
use Nette\Neon\Neon;
use Nette\Neon\Entity;

class DbSchemaInfo {

    private $schema;

    private $prefix;

    private $entityInfoRegistry;

    function __construct($schemaFile, $prefix) {
        $neonSchema = file_get_contents($schemaFile);
        $this->schema = Neon::decode($neonSchema);
        $this->prefix = $prefix;
    }

    public function getEntityInfo($entityName) {
        if (!isset($this->entityInfoRegistry[$entityName])) {
            $this->entityInfoRegistry[$entityName] = new EntityInfo(array($entityName => $this->schema[$entityName]));
        }

        return $this->entityInfoRegistry[$entityName];
    }

    public function getAllEntityNames() {
        return array_keys($this->schema);
    }

    public function getTableName($entityName) {
        $tableName = $this->isEntity($entityName) ? $this->getEntityInfo($entityName)->tableName : $entityName;
        return $tableName;
    }

    public function getPrefixedTableName($entityName) {
        return $this->prefix . $this->getTableName($entityName);
    }

    public function getEntityInfoByTableName($tableName) {
        $entityNames = $this->getAllEntityNames();
        foreach ($entityNames as $entityName) {
            $entityInfo = $this->getEntityInfo($entityName);
            if ($entityInfo->tableName === $tableName)
                return $entityInfo;
        }
        return null;
    }

    public function getEntityInfoByPrefixedTableName($tableName) {
        $tableName = substr($tableName, strlen($this->prefix));
        return $this->getEntityInfoByTableName($tableName);
    }

    private function isEntity($entityOrTableName) {
        return in_array($entityOrTableName, $this->getAllEntityNames());
    }
}