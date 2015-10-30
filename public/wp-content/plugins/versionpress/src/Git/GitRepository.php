<?php

namespace VersionPress\Git;

use Nette\Utils\Strings;
use Symfony\Component\Process\Process;
use VersionPress\Utils\FileSystem;

class GitRepository {

    private $workingDirectoryRoot;
    private $authorName = "";
    private $authorEmail = "";
    private $tempDirectory;
    private $commitMessagePrefix;
    private $gitBinary;
    private $gitProcessTimeout = 60;

    function __construct($workingDirectoryRoot, $tempDirectory = ".", $commitMessagePrefix = "[VP] ", $gitBinary = "git") {
        $this->workingDirectoryRoot = $workingDirectoryRoot;
        $this->tempDirectory = $tempDirectory;
        $this->commitMessagePrefix = $commitMessagePrefix;
        $this->gitBinary = $gitBinary;
    }

    public function stageAll($path = null) {
        $path = $path ? $path : $this->workingDirectoryRoot;
        $this->runShellCommand("git add -A %s", $path);
    }

    public function commit($message, $authorName, $authorEmail) {
        $this->authorName = $authorName;
        $this->authorEmail = $authorEmail;

        if (is_string($message)) {
            $commitMessage = $message;
            $body = null;
        } else {
            $subject = $message->getSubject();
            $body = $message->getBody();
            $commitMessage = $this->commitMessagePrefix . $subject;
        }

        if ($body != null) $commitMessage .= "\n\n" . $body;

        $tempCommitMessageFilename = md5(rand());
        $tempCommitMessagePath = $this->tempDirectory . '/' . $tempCommitMessageFilename;
        file_put_contents($tempCommitMessagePath, $commitMessage);

        $this->runShellCommand("git config user.name %s", $this->authorName);
        $this->runShellCommand("git config user.email %s", $this->authorEmail);
        $this->runShellCommand("git commit -F %s", $tempCommitMessagePath);
        FileSystem::remove($tempCommitMessagePath);
    }

    public function isVersioned() {
        return file_exists($this->workingDirectoryRoot . "/.git");
    }

    public function init() {
        $this->runShellCommand("git init");
    }

    public function getLastCommitHash() {
        $result = $this->runShellCommand("git rev-parse HEAD");
        if ($result["stderr"]) {
            return "";
        } else {
            return $result["stdout"];
        }
    }

    public function getInitialCommit() {
        $initialCommitHash = $this->runShellCommandWithStandardOutput("git rev-list --max-parents=0 HEAD");
        return $this->getCommit($initialCommitHash);
    }

    public function log($gitrevisions = "") {

        $commitDelimiter = chr(29);
        $dataDelimiter = chr(30);
        $statusDelimiter = chr(31);

        $logCommand = "git log --pretty=format:\"|begin|%%H|delimiter|%%aD|delimiter|%%ar|delimiter|%%an|delimiter|%%ae|delimiter|%%P|delimiter|%%s|delimiter|%%b|end|\" --name-status";
        if (!empty($gitrevisions)) {
            $logCommand .= " " . escapeshellarg($gitrevisions);
        }

        $logCommand = str_replace("|begin|", $commitDelimiter, $logCommand);
        $logCommand = str_replace("|delimiter|", $dataDelimiter, $logCommand);
        $logCommand = str_replace("|end|", $statusDelimiter, $logCommand);
        $log = trim($this->runShellCommandWithStandardOutput($logCommand), $commitDelimiter);

        if ($log == "") {
            $commits = array();
        } else {
            $commits = explode($commitDelimiter, $log);
        }
        
        return array_map(function ($rawCommitAndStatus) use ($statusDelimiter) {
            list($rawCommit, $rawStatus) = explode($statusDelimiter, $rawCommitAndStatus);
            return Commit::buildFromString(trim($rawCommit), trim($rawStatus));
        }, $commits);

    }

    public function getModifiedFiles($gitrevisions) {
        $result = $this->runShellCommandWithStandardOutput("git diff --name-only %s", $gitrevisions);
        $files = explode("\n", $result);
        return $files;
    }

    public function getModifiedFilesWithStatus($gitrevisions) {
        $command = 'git diff --name-status %s';
        $output = $this->runShellCommandWithStandardOutput($command, $gitrevisions);
        $result = array();

        foreach (explode("\n", $output) as $line) {
            list($status, $path) = explode("\t", $line);
            $result[] = array("status" => $status, "path" => $path);
        }

        return $result;

    }

    public function revertAll($commitHash) {
        $this->runShellCommand("git checkout %s .", $commitHash);
    }

    public function revert($commitHash) {
        $output = $this->runShellCommandWithErrorOutput("git revert -n %s", $commitHash);

        if ($output !== null) { 
            $this->abortRevert();
            return false;
        }

        return true;
    }

    public function abortRevert() {
        $this->runShellCommand("git revert --abort");
    }

    public function wasCreatedAfter($commitHash, $afterWhichCommitHash) {
        $range = sprintf("%s..%s", $afterWhichCommitHash, $commitHash);
        $cmd = "git log %s --oneline";
        return $this->runShellCommandWithStandardOutput($cmd, $range) != null;
    }

    public function getChildCommit($commitHash) {
        $range = "$commitHash..";
        $cmd = "git log --reverse --ancestry-path --format=%%H %s";
        $result = $this->runShellCommandWithStandardOutput($cmd, $range);
        list($childHash) = explode("\n", $result);
        return $childHash;
    }

    public function getNumberOfCommits($startRevision = null, $endRevision = "HEAD") {
        $revRange = empty($startRevision) ? $endRevision : "$startRevision..$endRevision";
        return intval($this->runShellCommandWithStandardOutput("git rev-list %s --count", $revRange));
    }

    public function willCommit() {
        $status = $this->runShellCommandWithStandardOutput("git status -s");
        return Strings::match($status, "~^[AMD].*~") !== null;
    }

    public function getCommit($commitHash) {
        $logWithInitialCommit = $this->log($commitHash);
        return $logWithInitialCommit[0];
    }

    public function getStatus($array = false) {
        $gitCmd = "git status --porcelain -uall";
        $output = $this->runShellCommandWithStandardOutput($gitCmd);
        if($array) {
            if ($output === null) {
                return array();
            }

            $output = explode("\n", $output); 
            foreach ($output as $k => $line) {
                $output[$k] = explode(" ", trim($line), 2);
            }
        }
        return $output;
    }

    public function isCleanWorkingDirectory() {
        $status = $this->getStatus();
        return empty($status);
    }

    public function getDiff($hash = null) {
        if ($hash === null) {
            $status = $this->getStatus(true);
            $this->runShellCommand("git add -AN");
            $diff = $this->runShellCommandWithStandardOutput("git diff HEAD");
            $filesToReset = array_map(function ($file) {
                return $file[1];
            }, array_filter($status, function ($file) {
                return $file[0] === '??'; 
            }));

            if (count($filesToReset) > 0) {
                $this->runShellCommand(sprintf("git reset HEAD %s", join(" ", array_map('escapeshellarg', $filesToReset))));
            }

            return $diff;
        }

        if ($this->getInitialCommit()->getHash() === $hash) {
            
            $emptyTreeHash = "4b825dc642cb6eb9a060e54bf8d69288fbee4904";
            $gitCmd = "git diff-tree -p $emptyTreeHash $hash";
        } else {
            $escapedHash = escapeshellarg($hash);
            $gitCmd = "git diff $escapedHash~1 $escapedHash";
        }

        $output = $this->runShellCommandWithStandardOutput($gitCmd);
        return $output;
    }

    public function clearWorkingDirectory() {
        $this->runShellCommand("git clean -f");
        $this->runShellCommand("git reset --hard");
        return $this->isCleanWorkingDirectory();
    }

    private function runShellCommandWithStandardOutput($command, $args = '') {
        $result = call_user_func_array(array($this, 'runShellCommand'), func_get_args());
        return $result['stdout'];
    }

    private function runShellCommandWithErrorOutput($command, $args = '') {
        $result = call_user_func_array(array($this, 'runShellCommand'), func_get_args());
        return $result['stderr'];
    }

    private function runShellCommand($command, $args = '') {

        $command = Strings::startsWith($command, "git ") ? substr($command, 4) : $command;
        $command = escapeshellarg($this->gitBinary) . " " . $command;

        $functionArgs = func_get_args();
        array_shift($functionArgs); 
        $escapedArgs = @array_map("escapeshellarg", $functionArgs);
        $commandWithArguments = vsprintf($command, $escapedArgs);

        $result = $this->runProcess($commandWithArguments);
        return $result;
    }

    private function runProcess($cmd) {
        

        $dyldLibraryPath = getenv("DYLD_LIBRARY_PATH");
        if ($dyldLibraryPath != "") {
            putenv("DYLD_LIBRARY_PATH=");
        }

        $process = new Process($cmd, $this->workingDirectoryRoot);
        $process->setTimeout($this->gitProcessTimeout);
        $process->run();

        $result = array(
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput()
        );

        putenv("DYLD_LIBRARY_PATH=$dyldLibraryPath");

        if ($result['stdout'] !== null) $result['stdout'] = trim($result['stdout']);
        if ($result['stderr'] !== null) $result['stderr'] = trim($result['stderr']);

        return $result;
    }

    public function setGitProcessTimeout($gitProcessTimeout) {
        $this->gitProcessTimeout = $gitProcessTimeout;
    }
}
