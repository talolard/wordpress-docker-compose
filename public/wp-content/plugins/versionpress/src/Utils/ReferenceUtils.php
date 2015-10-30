<?php

namespace VersionPress\Utils;

use VersionPress\Database\DbSchemaInfo;

class ReferenceUtils {

    public static function getMnReferenceDetails(DbSchemaInfo $dbSchema, $entityName, $reference) {
        list($junctionTable, $targetColumn) = explode(".", $reference);
        $targetEntity = $dbSchema->getEntityInfo($entityName)->mnReferences[$reference];
        $sourceColumn = self::getSourceColumn($dbSchema, $entityName, $targetEntity, $junctionTable);
        return array(
            'junction-table' => $junctionTable,
            'source-entity' => $entityName,
            'source-column' => $sourceColumn,
            'target-entity' => $targetEntity,
            'target-column' => $targetColumn,
        );
    }

    public static function getValueReferenceDetails($reference) {
        list($keyCol, $valueColumn) = explode("@", $reference);
        list($sourceColumn, $sourceValue) = explode("=", $keyCol);

        return array(
            'source-column' => $sourceColumn,
            'source-value'  => $sourceValue,
            'value-column' => $valueColumn,
        );
    }

    private static function getSourceColumn(DbSchemaInfo $dbSchema, $sourceEntity, $targetEntity, $junctionTable) {
        $targetEntityMnReferences = $dbSchema->getEntityInfo($targetEntity)->mnReferences;
        foreach ($targetEntityMnReferences as $reference => $referencedEntity) {
            list($referencedTable, $referenceColumn) = explode(".", $reference);
            if ($referencedTable === $junctionTable && $referencedEntity === $sourceEntity) {
                return $referenceColumn;
            }
        }

        return null;
    }
}