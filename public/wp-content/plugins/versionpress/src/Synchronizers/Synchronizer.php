<?php

namespace VersionPress\Synchronizers;

interface Synchronizer {

    const SYNCHRONIZE_EVERYTHING = 'everything';

    function synchronize($task, $entitiesToSynchronize = null);
}
