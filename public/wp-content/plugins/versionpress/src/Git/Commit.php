<?php

namespace VersionPress\Git;
use DateTime;

class Commit {

    private $hash;

    private $date;

    private $relativeDate;

    private $authorName;

    private $authorEmail;

    private $message;

    private $isMerge;

    private $changedFiles = array();

    public static function buildFromString($rawCommit, $rawStatus) {
        list($hash, $date, $relativeDate, $authorName, $authorEmail, $parentHashes, $messageHead, $messageBody) = explode(chr(30), $rawCommit);
        $commit = new Commit();
        $commit->hash = $hash;
        $commit->date = new DateTime($date);
        $commit->relativeDate = $relativeDate;
        $commit->authorName = $authorName;
        $commit->authorEmail = $authorEmail;
        $commit->isMerge = strpos($parentHashes, ' ') !== false;
        $commit->message = new CommitMessage($messageHead, $messageBody);

        if ($rawStatus === "") {
            return $commit;
        }

        foreach (explode("\n", $rawStatus) as $line) {
            list($status, $path) = explode("\t", $line);
            $commit->changedFiles[] = array("status" => $status, "path" => $path);
        }

        return $commit;
    }

    public function getHash() {
        return $this->hash;
    }

    public function getShortHash() {
        return substr($this->hash, 0, 7);
    }

    public function getDate() {
        return $this->date;
    }

    public function getRelativeDate() {
        return $this->relativeDate;
    }

    public function getAuthorName() {
        return $this->authorName;
    }

    public function getAuthorEmail() {
        return $this->authorEmail;
    }

    public function getMessage() {
        return $this->message;
    }

    public function isMerge() {
        return $this->isMerge;
    }

    public function getChangedFiles() {
        return $this->changedFiles;
    }
}
