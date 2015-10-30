<?php

namespace VersionPress\Git;
use Nette\Utils\Strings;

class CommitMessage {

    private $subject;

    private $body;

    private $tags;

    function __construct($subject, $body = null) {
        $this->subject = $subject;
        $this->body = $body;
    }

    public function getSubject() {
        return $this->subject;
    }

    public function getBody() {
        return $this->body;
    }

    public function getVersionPressTags() {
        if (!$this->tags) {
            $tagLines = array_filter(
                array_map("trim", explode("\n", $this->getBody())),
                function ($line) {
                    return Strings::startsWith($line, "VP-") || Strings::startsWith($line, "X-VP-");
                }
            );
            $tags = array();
            foreach ($tagLines as $line) {
                list($key, $value) = array_map("trim", explode(":", $line, 2));
                $tags[$key] = $value;
            }

            $this->tags = $tags;
        }
        return $this->tags;
    }

    public function getVersionPressTag($tagName) {
        $tags = $this->getVersionPressTags();
        return isset($tags[$tagName]) ? $tags[$tagName] : "";
    }
}