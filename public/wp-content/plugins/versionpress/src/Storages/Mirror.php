<?php
namespace VersionPress\Storages;

use VersionPress\ChangeInfos\TrackedChangeInfo;
use VersionPress\Utils\AbsoluteUrlReplacer;

class Mirror {

    private $storageFactory;

    private $changeList = array();

    private $urlReplacer;

    function __construct(StorageFactory $storageFactory, AbsoluteUrlReplacer $urlReplacer) {
        $this->storageFactory = $storageFactory;
        $this->urlReplacer = $urlReplacer;
    }

    public function save($entityName, $data) {
        $storage = $this->storageFactory->getStorage($entityName);
        if ($storage == null) {
            return;
        }

        $data = $this->urlReplacer->replace($data);
        $changeInfo = $storage->save($data);
        if ($changeInfo) {
            $this->changeList[] = $changeInfo;
        }
    }

    public function delete($entityName, $restriction) {
        $storage = $this->storageFactory->getStorage($entityName);
        if ($storage == null) {
            return;
        }

        $changeInfo = $storage->delete($restriction);
        if ($changeInfo) {
            $this->changeList[] = $changeInfo;
        }
    }

    public function getChangeList() {
        return $this->changeList;
    }

    public function shouldBeSaved($entityName, $data) {
        $storage = $this->storageFactory->getStorage($entityName);
        if ($storage === null)
            return false;
        return $storage->shouldBeSaved($data);
    }

    public function flushChangeList() {
        $this->changeList = array();
    }

}
