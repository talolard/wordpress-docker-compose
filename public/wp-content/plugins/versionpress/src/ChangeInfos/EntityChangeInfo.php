<?php

namespace VersionPress\ChangeInfos;

abstract class EntityChangeInfo extends TrackedChangeInfo {

    private $entityName;

    private $action;

    private $entityId;

    public function __construct($entityName, $action, $entityId) {
        $this->entityName = $entityName;
        $this->action = $action;
        $this->entityId = $entityId;
    }

    public function getEntityName() {
        return $this->entityName;
    }

    public function getAction() {
        return $this->action;
    }

    public function getEntityId() {
        return $this->entityId;
    }

    protected function getActionTagValue() {
        return "{$this->getEntityName()}/{$this->getAction()}/{$this->getEntityId()}";
    }

    public function getChangedFiles() {
        $change = array(
            "type" => "storage-file",
            "entity" => $this->getEntityName(),
            "id" => $this->getEntityId(),
            "parent-id" => $this->getParentId()
        );

        return array($change);
    }

    public function getParentId() {
        return null;
    }
}