<?php

namespace VersionPress\ChangeInfos;
use VersionPress\Git\CommitMessage;

interface ChangeInfo {

    function getCommitMessage();

    function getChangeDescription();

    static function buildFromCommitMessage(CommitMessage $commitMessage);
}