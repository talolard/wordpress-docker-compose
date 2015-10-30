<?php

namespace VersionPress\Cli;

use Symfony\Component\Process\Process;

class VPCommandUtils {
    public static function runWpCliCommand($command, $subcommand, $args = array(), $cwd = null) {

        $cliCommand = "wp $command";

        if ($subcommand) {
            $cliCommand .= " $subcommand";
        }

        foreach ($args as $name => $value) {
            if (is_int($name)) { 
                $cliCommand .= " " . escapeshellarg($value);
            } elseif ($value !== null) {
                $cliCommand .= " --$name=" . escapeshellarg($value);
            } else {
                $cliCommand .= " --$name";
            }
        }

        return self::exec($cliCommand, $cwd);
    }

    public static function exec($command, $cwd = null) {
        
        
        
        if (isset($_SERVER["XDEBUG_CONFIG"])) {
            $env = $_SERVER;
            unset($env["XDEBUG_CONFIG"]);
        } else {
            $env = null;
        }

        $process = new Process($command, $cwd, $env);
        $process->run();
        return $process;
    }
}