<?php

namespace VersionPress\Storages;
use VersionPress\Database\EntityInfo;
use VersionPress\Utils\IniSerializer;

abstract class SingleFileStorage extends Storage {

    protected $file;
    

    protected $entityInfo;

    protected $entities = null;

    protected $notSavedFields = array();

    private $uncommittedEntities;

    private $batchMode;

    public function __construct($file, $entityInfo) {
        $this->file = $file;
        $this->entityInfo = $entityInfo;
    }

    public function save($data) {
        if (!$this->shouldBeSaved($data))
            return null;

        $vpid = $data[$this->entityInfo->vpidColumnName];

        if (!$vpid) {
            return null;
        }

        $this->loadEntities();
        $originalEntities = $this->entities;

        $isNew = !isset($this->entities[$vpid]);

        if ($isNew) {
            $this->entities[$vpid] = array();
            $oldEntity = null;
        } else {
            $oldEntity = $originalEntities[$vpid];
        }

        $this->updateEntity($vpid, $data);

        if ($this->entities !== $originalEntities) {
            $this->saveEntities();
            return $this->createChangeInfo($oldEntity, $this->entities[$vpid], $isNew ? 'create' : 'edit');
        } else {
            return null;
        }

    }

    public function delete($restriction) {
        if (!$this->shouldBeSaved($restriction)) {
            return null;
        }

        $vpid = $restriction[$this->entityInfo->vpidColumnName];

        $this->loadEntities();
        $originalEntities = $this->entities;
        $entity = $this->entities[$vpid];

        unset($this->entities[$vpid]);

        if ($this->entities !== $originalEntities) {
            $this->saveEntities();
            return $this->createChangeInfo($entity, $entity, 'delete');
        } else {
            return null;
        }
    }

    public function saveLater($data) {
        $this->uncommittedEntities[] = $data;
    }

    public function commit() {
        $this->batchMode = true;
        foreach ($this->uncommittedEntities as $entity) {
            $this->save($entity);
        }
        $this->batchMode = false;
        $this->saveEntities();
        $this->uncommittedEntities = null;
    }

    public function loadEntity($id, $parentId = null) {
        $this->loadEntities();
        return $this->entities[$id];
    }

    public function loadAll() {
        $this->loadEntities();
        return $this->entities;
    }

    public function exists($id, $parentId = null) {
        $this->loadEntities();
        return isset($this->entities[$id]);
    }

    public function prepareStorage() {
    }

    private function updateEntity($vpid, $data) {

        if ($this->entityInfo->usesGeneratedVpids) { 
            unset($data[$this->entityInfo->idColumnName]);
        }

        foreach ($this->notSavedFields as $field) {
            unset($data[$field]);
        }

        foreach ($data as $field => $value) {
            $this->entities[$vpid][$field] = $value;
        }

    }

    protected function loadEntities() {
        if ($this->batchMode && $this->entities != null) {
            return;
        }

        if (is_file($this->file)) {
            $entities = $this->deserializeEntities(file_get_contents($this->file));

            foreach ($entities as $id => &$entity) {
                $entity[$this->entityInfo->vpidColumnName] = $id;
            }

            $this->entities = $entities;
        } else {
            $this->entities = array();
        }
    }

    protected function saveEntities() {
        if ($this->batchMode) {
            return;
        }

        $entities = $this->entities;
        foreach ($entities as &$entity) {
            unset ($entity[$this->entityInfo->vpidColumnName]);
        }

        $serializedEntities = IniSerializer::serialize($entities);
        file_put_contents($this->file, $serializedEntities);
    }

    public function shouldBeSaved($data) {
        return true;
    }

    public function getEntityFilename($id, $parentId = null) {
        return $this->file;
    }

    protected function deserializeEntities($fileContent) {
        return IniSerializer::deserialize($fileContent);
    }

}
