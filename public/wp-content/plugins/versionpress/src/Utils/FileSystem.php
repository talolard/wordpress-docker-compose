<?php

namespace VersionPress\Utils;
use FilesystemIterator;
use Nette\Utils\Strings;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Traversable;

class FileSystem {

    public static function rename($origin, $target, $overwrite = false) {

        self::possiblyFixGitPermissions($origin);

        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->rename($origin, $target, $overwrite);
    }

    public static function remove($path) {

        self::possiblyFixGitPermissions($path);

        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->remove($path);
    }

    public static function removeContent($path) {

        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $item) {
            if ($item->isDir() && Strings::endsWith($iterator->key(), ".git")) {
                self::possiblyFixGitPermissions($iterator->key());
            }
        }

        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->remove($iterator);
    }

    public static function copy($origin, $target, $override = false) {
        $fs = new \Symfony\Component\Filesystem\Filesystem();

        if (!$override && $fs->exists($target))
            return;

        $fs->copy($origin, $target, $override);
    }

    public static function copyDir($origin, $target) {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->mirror($origin, $target);
    }

    public static function mkdir($dir, $mode = 0777) {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->mkdir($dir, $mode);
    }

    private static function possiblyFixGitPermissions($path) {

        $gitDir = null;
        if (is_dir($path)) {
            if (basename($path) == '.git') {
                $gitDir = $path;
            } else if (is_dir($path . '/.git')) {
                $gitDir = $path . '/.git';
            }
        }

        if ($gitDir) {

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gitDir));

            foreach ($iterator as $item) {
                chmod($item, 0777);
            }

        }

    }

    public static function filesHaveSameContents($file1, $file2) {
        return sha1_file($file1) == sha1_file($file2);
    }
}
