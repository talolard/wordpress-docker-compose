<?php
namespace VersionPress\ChangeInfos;

use VersionPress\Git\CommitMessage;

class UserMetaChangeInfo extends EntityChangeInfo {

    const USER_LOGIN = "VP-User-Login";
    const USER_META_KEY = "VP-UserMeta-Key";
    const USER_VPID_TAG = "VP-User-Id";

    private $userLogin;

    private $userMetaKey;

    private $userVpId;

    public function __construct($action, $entityId, $userLogin, $userMetaKey, $userVpId) {
        parent::__construct("usermeta", $action, $entityId);
        $this->userLogin = $userLogin;
        $this->userMetaKey = $userMetaKey;
        $this->userVpId = $userVpId;
    }

    public function getChangeDescription() {
        if ($this->getAction() === "create") {
            return "New user-meta '{$this->userMetaKey}' for user '{$this->userLogin}'";
        }

        if ($this->getAction() === "delete") {
            return "Deleted user-meta '{$this->userMetaKey}' for user '{$this->userLogin}'";
        }

        return "Edited user-meta '{$this->userMetaKey}' for user '{$this->userLogin}'";
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $tags = $commitMessage->getVersionPressTags();
        $actionTag = $tags[TrackedChangeInfo::ACTION_TAG];
        $userMetaKey = $tags[self::USER_META_KEY];
        $userLogin = $tags[self::USER_LOGIN];
        $userVpId = $tags[self::USER_VPID_TAG];
        list(, $action, $entityId) = explode("/", $actionTag);
        return new self($action, $entityId, $userLogin, $userMetaKey, $userVpId);
    }

    public function getCustomTags() {
        return array(
            self::USER_LOGIN => $this->userLogin,
            self::USER_META_KEY => $this->userMetaKey,
            self::USER_VPID_TAG => $this->userVpId
        );
    }

    public function getParentId() {
        return $this->userVpId;
    }
}
