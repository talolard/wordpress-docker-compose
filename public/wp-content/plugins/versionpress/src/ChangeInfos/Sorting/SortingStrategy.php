<?php

namespace ChangeInfos\Sorting;

use Nette\Utils\Strings;
use VersionPress\ChangeInfos\BulkChangeInfo;
use VersionPress\ChangeInfos\EntityChangeInfo;
use VersionPress\ChangeInfos\OptionChangeInfo;
use VersionPress\ChangeInfos\PostChangeInfo;
use VersionPress\ChangeInfos\TermChangeInfo;
use VersionPress\ChangeInfos\ThemeChangeInfo;
use VersionPress\ChangeInfos\TrackedChangeInfo;
use VersionPress\ChangeInfos\TranslationChangeInfo;
use VersionPress\Utils\ArrayUtils;

class SortingStrategy {

    private $priorityOrder = array(
        'VersionPress\ChangeInfos\WordPressUpdateChangeInfo',
        'VersionPress\ChangeInfos\VersionPressChangeInfo',
        'VersionPress\ChangeInfos\UserChangeInfo',
        'VersionPress\ChangeInfos\PostChangeInfo',
        'VersionPress\ChangeInfos\CommentChangeInfo',
        'VersionPress\ChangeInfos\RevertChangeInfo',
        'VersionPress\ChangeInfos\PluginChangeInfo',
        'VersionPress\ChangeInfos\ThemeChangeInfo',
        'VersionPress\ChangeInfos\TermChangeInfo',
        'VersionPress\ChangeInfos\TranslationChangeInfo',
        'VersionPress\ChangeInfos\OptionChangeInfo',
        'VersionPress\ChangeInfos\PostMetaChangeInfo',
        'VersionPress\ChangeInfos\UserMetaChangeInfo',
    );

    function sort($changeInfoList) {
        ArrayUtils::stablesort($changeInfoList, array($this, 'compareChangeInfo'));
        return $changeInfoList;
    }

    public function compareChangeInfo($changeInfo1, $changeInfo2) {
        $class1 = get_class($changeInfo1);
        $class2 = get_class($changeInfo2);

        $class1 = $this->stripBulkFromClassName($class1);
        $class2 = $this->stripBulkFromClassName($class2);

        $priority1 = array_search($class1, $this->priorityOrder);
        $priority2 = array_search($class2, $this->priorityOrder);

        if ($priority1 < $priority2) {
            return -1;
        }

        if ($priority1 > $priority2) {
            return 1;
        }

        if ($changeInfo1 instanceof ThemeChangeInfo && $changeInfo2 instanceof ThemeChangeInfo) {
            return $this->compareThemeChangeInfo($changeInfo1, $changeInfo2);
        }

        if ($changeInfo1 instanceof OptionChangeInfo && $changeInfo2 instanceof OptionChangeInfo) {
            return $this->compareOptionChangeInfo($changeInfo1, $changeInfo2);
        }

        if ($changeInfo1 instanceof TermChangeInfo && $changeInfo2 instanceof TermChangeInfo) {
            return $this->compareTermChangeInfo($changeInfo1, $changeInfo2);
        }

        if ($changeInfo1 instanceof PostChangeInfo && $changeInfo2 instanceof PostChangeInfo) {
            return $this->comparePostChangeInfo($changeInfo1, $changeInfo2);
        }

        if ($changeInfo1 instanceof TranslationChangeInfo && $changeInfo2 instanceof TranslationChangeInfo) {
            return $this->compareTranslationChangeInfo($changeInfo1, $changeInfo2);
        }

        if (($changeInfo1 instanceof EntityChangeInfo && $changeInfo2 instanceof EntityChangeInfo)
         || ($changeInfo1 instanceof EntityChangeInfo && $changeInfo2 instanceof BulkChangeInfo)
         || ($changeInfo1 instanceof BulkChangeInfo && $changeInfo2 instanceof EntityChangeInfo)
         || ($changeInfo1 instanceof BulkChangeInfo && $changeInfo2 instanceof BulkChangeInfo)) {
            
            if ($changeInfo1->getAction() === "create") {
                return -1;
            }

            if ($changeInfo2->getAction() === "create") {
                return 1;
            }
            
            if ($changeInfo1->getAction() === "delete") {
                return -1;
            }

            if ($changeInfo2->getAction() === "delete") {
                return 1;
            }

            return 0;
        }

        return 0;
    }

    private function compareThemeChangeInfo($changeInfo1, $changeInfo2) {
        
        if ($changeInfo1->getAction() == "switch") {
            return -1;
        } else if ($changeInfo2->getAction() == "switch") {
            return 1;
        }

        return 0;
    }

    private function compareOptionChangeInfo($changeInfo1, $changeInfo2) {

        if ($changeInfo1->getEntityId() == "WPLANG") {
            return 1;
        } else if ($changeInfo2->getEntityId() == "WPLANG") {
            return -1;
        }

        if ($changeInfo1->getAction() === "create" && $changeInfo2->getAction() !== "create") {
            return -1;
        }

        if ($changeInfo2->getAction() === "create" && $changeInfo1->getAction() !== "create") {
            return 1;
        }

        return strcmp($changeInfo1->getEntityId(), $changeInfo2->getEntityId());
    }

    private function compareTermChangeInfo($changeInfo1, $changeInfo2) {
        
        if ($changeInfo1->getAction() == "delete") {
            return -1;
        } else if ($changeInfo2->getAction() == "delete") {
            return 1;
        }

        return 0;
    }

    private function comparePostChangeInfo($changeInfo1, $changeInfo2) {
        

        if ($changeInfo1->getAction() == "create") {
            return -1;
        } else if ($changeInfo2->getAction() == "create") {
            return 1;
        } else if ($changeInfo1->getAction() == "delete") {
            return -1;
        } else if ($changeInfo2->getAction() == "delete") {
            return 1;
        } else if ($changeInfo1->getAction() == "draft") {
            return -1;
        } else if ($changeInfo2->getAction() == "draft") {
            return 1;
        } else if ($changeInfo1->getAction() == "edit") {
            return -1;
        } else if ($changeInfo2->getAction() == "edit") {
            return 1;
        }

        return 0;
    }

    private function compareTranslationChangeInfo($changeInfo1, $changeInfo2) {
        
        if ($changeInfo1->getAction() == "activate") {
            return -1;
        } else if ($changeInfo2->getAction() == "activate") {
            return 1;
        }

        return 0;
    }

    private function stripBulkFromClassName($className) {
        if (Strings::contains($className, "Bulk")) {
            $className = Strings::replace($className, "~Bulk~");
        }
        return $className;
    }
}
