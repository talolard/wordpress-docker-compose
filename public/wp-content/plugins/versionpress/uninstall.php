<?php

use VersionPress\Utils\FileSystem;
use VersionPress\Utils\SecurityUtils;
use VersionPress\Utils\UninstallationUtil;

defined('WP_UNINSTALL_PLUGIN') or die('Direct access not allowed');

require_once(dirname(__FILE__) . '/bootstrap.php');

if (UninstallationUtil::uninstallationShouldRemoveGitRepo()) {

    $backupsDir = WP_CONTENT_DIR . '/vpbackups';
    if (!file_exists($backupsDir)) {
        FileSystem::mkdir($backupsDir);
        file_put_contents($backupsDir . '/.gitignore', 'git-backup-*');
        SecurityUtils::protectDirectory($backupsDir);
    }

    $backupPath = $backupsDir . '/git-backup-' . date("YmdHis");

    FileSystem::rename(ABSPATH . '.git', $backupPath, true);

    $productionGitignore = ABSPATH . '.gitignore';
    $templateGitignore = __DIR__ . '/src/Initialization/.gitignore.tpl';

    if (FileSystem::filesHaveSameContents($productionGitignore, $templateGitignore)) {
        FileSystem::remove($productionGitignore);
    }

}

