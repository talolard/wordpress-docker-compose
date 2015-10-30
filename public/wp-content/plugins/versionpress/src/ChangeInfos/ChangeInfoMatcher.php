<?php

namespace VersionPress\ChangeInfos;

use Exception;
use VersionPress\Git\CommitMessage;

class ChangeInfoMatcher {

    private static $changeInfoMap = array(

        "versionpress/(?!(undo|rollback)).*" => 'VersionPress\ChangeInfos\VersionPressChangeInfo',
        "versionpress/(undo|rollback)/.*" => 'VersionPress\ChangeInfos\RevertChangeInfo',

        "translation/.*" => 'VersionPress\ChangeInfos\TranslationChangeInfo',
        "plugin/.*" => 'VersionPress\ChangeInfos\PluginChangeInfo',
        "theme/.*" => 'VersionPress\ChangeInfos\ThemeChangeInfo',
        "wordpress/update/.*" => 'VersionPress\ChangeInfos\WordPressUpdateChangeInfo',

        "post/.*" => 'VersionPress\ChangeInfos\PostChangeInfo',
        "postmeta/.*" => 'VersionPress\ChangeInfos\PostMetaChangeInfo',
        "comment/.*" => 'VersionPress\ChangeInfos\CommentChangeInfo',
        "option/.*" => 'VersionPress\ChangeInfos\OptionChangeInfo',
        "term/.*" => 'VersionPress\ChangeInfos\TermChangeInfo',
        "usermeta/.*" => 'VersionPress\ChangeInfos\UserMetaChangeInfo',
        "user/.*" => 'VersionPress\ChangeInfos\UserChangeInfo',

        "" => 'VersionPress\ChangeInfos\UntrackedChangeInfo',

    );

    public static function buildChangeInfo(CommitMessage $commitMessage) {
        if (self::findMatchingChangeInfo($commitMessage) === 'VersionPress\ChangeInfos\UntrackedChangeInfo') {
            return UntrackedChangeInfo::buildFromCommitMessage($commitMessage);
        }

        return ChangeInfoEnvelope::buildFromCommitMessage($commitMessage);
    }

    public static function findMatchingChangeInfo(CommitMessage $commitMessage) {

        if (substr_count($commitMessage->getBody(), TrackedChangeInfo::ACTION_TAG) > 1) {
            return "VersionPress\ChangeInfos\ChangeInfoEnvelope";
        }

        $actionTagValue = $commitMessage->getVersionPressTag(TrackedChangeInfo::ACTION_TAG); 

        foreach (self::$changeInfoMap as $actionTagExpression => $changeInfoType) {
            $regex = "~^" . $actionTagExpression . "$~";
            if (preg_match($regex, $actionTagValue)) {
                return $changeInfoType;
            }
        }

        throw new Exception("Matching ChangeInfo type not found");
    }

    public static function matchesChangeInfo(CommitMessage $commitMessage, $changeInfoClass) {
        return self::findMatchingChangeInfo($commitMessage) == $changeInfoClass;
    }

}
