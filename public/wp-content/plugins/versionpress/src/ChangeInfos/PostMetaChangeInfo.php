<?php
namespace VersionPress\ChangeInfos;

use Nette\Utils\Strings;
use VersionPress\Git\CommitMessage;
use VersionPress\Utils\StringUtils;

class PostMetaChangeInfo extends EntityChangeInfo {

    const POST_TITLE_TAG = "VP-Post-Title";
    const POST_TYPE_TAG = "VP-Post-Type";
    const POST_META_KEY = "VP-PostMeta-Key";
    const POST_VPID_TAG = "VP-Post-Id";

    private $postType;

    private $postTitle;

    private $postVpId;

    private $metaKey;

    public function __construct($action, $entityId, $postType, $postTitle, $postVpId, $metaKey) {
        parent::__construct("postmeta", $action, $entityId);
        $this->postType = $postType;
        $this->postTitle = $postTitle;
        $this->postVpId = $postVpId;
        $this->metaKey = $metaKey;
    }

    public static function buildFromCommitMessage(CommitMessage $commitMessage) {
        $tags = $commitMessage->getVersionPressTags();
        $actionTag = $tags[TrackedChangeInfo::ACTION_TAG];
        list(, $action, $entityId) = explode("/", $actionTag, 3);
        $titleTag = isset($tags[self::POST_TITLE_TAG]) ? $tags[self::POST_TITLE_TAG] : $entityId;
        $type = $tags[self::POST_TYPE_TAG];
        $metaKey = $tags[self::POST_META_KEY];
        $postVpId = $tags[self::POST_VPID_TAG];
        return new self($action, $entityId, $type, $titleTag, $postVpId, $metaKey);
    }

    public function getChangeDescription() {
        $verb = "Edited";
        $subject = "post-meta '{$this->metaKey}'";
        $rest = "for {$this->postType} '{$this->postTitle}'";

        if ($this->metaKey === "_thumbnail_id") { 
            $verb = "Changed";
            $subject = "featured image";

            if ($this->getAction() === "create")
                $verb = "Set";
            if ($this->getAction() === "delete")
                $verb = "Removed";
        } elseif ($this->getAction() === "create" || $this->getAction() === "delete") {
            $verb = Strings::firstUpper(StringUtils::verbToPastTense($this->getAction()));
        }

        return sprintf("%s %s %s", $verb, $subject, $rest);
    }

    public function getCustomTags() {
        return array(
            self::POST_TITLE_TAG => $this->postTitle,
            self::POST_TYPE_TAG => $this->postType,
            self::POST_META_KEY => $this->metaKey,
            self::POST_VPID_TAG => $this->postVpId,
        );
    }

    public function getParentId() {
        return $this->postVpId;
    }
}
