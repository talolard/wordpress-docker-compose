<?php
namespace VersionPress\Utils;

use VersionPress\ChangeInfos\ChangeInfoMatcher;
use VersionPress\DI\VersionPressServices;
use VersionPress\Git\GitRepository;

class UninstallationUtil {

    public static function uninstallationShouldRemoveGitRepo() {
        global $versionPressContainer;
        

        $repository = $versionPressContainer->resolve(VersionPressServices::REPOSITORY);
        $initialCommit = $repository->getInitialCommit();
        return $initialCommit && ChangeInfoMatcher::matchesChangeInfo($initialCommit->getMessage(), 'VersionPress\ChangeInfos\VersionPressChangeInfo');
    }
}