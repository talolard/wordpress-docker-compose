<?php
namespace VersionPress\Storages;

use VersionPress\ChangeInfos\ChangeInfo;
use VersionPress\Database\EntityInfo;

abstract class Storage {

    protected $isTransaction;

    public abstract function save($data);

    public abstract function delete($restriction);

    public abstract function loadEntity($id, $parentId);

    public abstract function loadAll();

    public abstract function shouldBeSaved($data);

    public abstract function prepareStorage();

    public abstract function getEntityFilename($id, $parentId);

    protected abstract function createChangeInfo($oldEntity, $newEntity, $action);

    public abstract function exists($id, $parentId);

    public abstract function saveLater($data);

    public abstract function commit();
}