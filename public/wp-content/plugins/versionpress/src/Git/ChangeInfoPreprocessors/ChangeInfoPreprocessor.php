<?php

namespace VersionPress\Git\ChangeInfoPreprocessors;

use VersionPress\ChangeInfos\ChangeInfo;

interface ChangeInfoPreprocessor {
    

    function process($changeInfoList);
}