<?php

namespace VersionPress\Storages;

use Nette\Utils\Strings;

abstract class MetaEntityStorage extends Storage {

    private $lastVpId;

    protected $keyName;
    protected $valueName;
    private $parentReferenceName;

    private $parentStorage;

    function __construct(Storage $parentStorage, $keyName, $valueName, $parentReferenceName) {
        $this->parentStorage = $parentStorage;
        $this->keyName = $keyName;
        $this->valueName = $valueName;
        $this->parentReferenceName = $parentReferenceName;
    }

    public function save($data) {

        if (!$this->shouldBeSaved($data)) {
            return null;
        }

        $oldParent = $this->parentStorage->loadEntity($data[$this->parentReferenceName], null);
        $oldEntity = $this->extractEntityFromParentByVpId($oldParent, $data['vp_id']);

        $transformedData = $this->transformToParentEntityField($data);

        $this->lastVpId = $data['vp_id'];

        $this->parentStorage->save($transformedData);
        $newParent = $this->parentStorage->loadEntity($data[$this->parentReferenceName], null);
        $newEntity = $this->extractEntityFromParentByVpId($newParent, $data['vp_id']);

        if ($oldEntity == $newEntity) {
            return null;
        }

        if (!$oldEntity) {
            $action = 'create';
        } else {
            $action = 'edit';
        }

        return $this->createChangeInfoWithParentEntity($oldEntity, $newEntity, $oldParent, $newParent, $action);
    }

    public function delete($restriction) {
        $parentVpId = $restriction[$this->parentReferenceName];
        $parent = $this->parentStorage->loadEntity($parentVpId, null);
        $fieldToDelete = $this->getJoinedKeyByVpId($parent, $restriction['vp_id']);

        $oldEntity = $this->extractEntityFromParentByVpId($parent, $restriction['vp_id']);
        $oldParentEntity = $parent;

        $parent[$fieldToDelete] = false; 
        $newParentEntity = $parent;

        $this->parentStorage->save($parent);
        return $this->createChangeInfoWithParentEntity($oldEntity, $oldEntity, $oldParentEntity, $newParentEntity, 'delete');
    }

    public function saveLater($data) {
        $transformedData = $this->transformToParentEntityField($data);
        $this->parentStorage->saveLater($transformedData);
    }

    public function commit() {
        $this->parentStorage->commit();
    }

    public function loadAll() {
        $parentEntities = $this->parentStorage->loadAll();
        $entities = array();

        foreach ($parentEntities as $parent) {
            foreach ($parent as $field => $value) {
                if (!Strings::contains($field, '#')) {
                    continue;
                }
                list ($key, $vpId) = explode('#', $field, 2);
                $entities[$vpId] = $this->extractEntityFromParentByVpId($parent, $vpId);
            }

        }

        return $entities;
    }

    function exists($vpId, $parentId) {
        $parentExists = $this->parentStorage->exists($parentId, null);
        if (!$parentExists) {
            return false;
        }
        return (bool)$this->getJoinedKeyByVpId($this->parentStorage->loadEntity($parentId, null), $vpId);
    }

    public function getEntityFilename($vpId, $parentId) {
        return $this->parentStorage->getEntityFilename($parentId, null);
    }

    public function loadEntity($id, $parentId) {
        $parent = $this->parentStorage->loadEntity($parentId, null);
        return $this->extractEntityFromParentByVpId($parent, $id);
    }

    public function loadEntityByName($name, $parentId) {
        $parent = $this->parentStorage->loadEntity($parentId, null);
        return $this->extractEntityFromParentByName($parent, $name);
    }

    protected function createChangeInfo($oldParentEntity, $newParentEntity, $action) {
        $oldEntity = $this->extractEntityFromParentByVpId($oldParentEntity, $this->lastVpId);
        $newEntity = $this->extractEntityFromParentByVpId($newParentEntity, $this->lastVpId);
        return $this->createChangeInfoWithParentEntity($oldEntity, $newEntity, $oldParentEntity, $newParentEntity, $action);
    }

    protected abstract function createChangeInfoWithParentEntity($oldEntity, $newEntity, $oldParentEntity, $newParentEntity, $action);

    private function transformToParentEntityField($values) {
        $joinedKey = $this->createJoinedKey($values[$this->keyName], $values['vp_id']);

        $data = array(
            'vp_id' => $values[$this->parentReferenceName],
            $joinedKey => $values[$this->valueName]
        );
        return $data;
    }

    protected function createJoinedKey($key, $vpId) {
        return sprintf('%s#%s', $key, $vpId);
    }

    protected function splitJoinedKey($key) {
        $splittedKey = explode('#', $key, 2);
        return array(
            $this->keyName => $splittedKey[0],
            'vp_id' => $splittedKey[1],
        );
    }

    private function getJoinedKeyByVpId($parent, $vpId) {
        foreach ($parent as $field => $value) {
            if (Strings::contains($field, $vpId)) {
                return $field;
            }
        }

        return null;
    }

    private function getJoinedKeyByName($parent, $name) {
        foreach ($parent as $field => $value) {
            if (Strings::startsWith($field, "$name#")) {
                return $field;
            }
        }

        return null;
    }

    protected function extractEntityFromParentByVpId($parentEntity, $vpId) {
        if (!$parentEntity) {
            return null;
        }

        $joinedKey = $this->getJoinedKeyByVpId($parentEntity, $vpId);

        if (!$joinedKey) {
            return null;
        }

        return $this->extractEntityFromParent($parentEntity, $joinedKey);
    }

    protected function extractEntityFromParentByName($parentEntity, $name) {
        if (!$parentEntity) {
            return null;
        }

        $joinedKey = $this->getJoinedKeyByName($parentEntity, $name);

        if (!$joinedKey) {
            return null;
        }

        return $this->extractEntityFromParent($parentEntity, $joinedKey);
    }

    private function extractEntityFromParent($parentEntity, $joinedKey) {
        $splittedKey = $this->splitJoinedKey($joinedKey);
        $entity = array(
            $this->keyName => $splittedKey[$this->keyName],
            $this->valueName => $parentEntity[$joinedKey],
            'vp_id' => $splittedKey['vp_id'],
            $this->parentReferenceName => $parentEntity['vp_id'],
        );

        return $entity;
    }

    function shouldBeSaved($data) {
        return isset($data[$this->parentReferenceName]) && $this->parentStorage->exists($data[$this->parentReferenceName], null);
    }

    function prepareStorage() {
    }
}