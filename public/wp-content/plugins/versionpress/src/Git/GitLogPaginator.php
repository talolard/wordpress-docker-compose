<?php
namespace VersionPress\Git;

class GitLogPaginator {

    private $repository;
    private $commitsPerPage = 25;
    private $isLastPage = false;
    private $numberOfCommits;

    function __construct(GitRepository $repository) {
        $this->repository = $repository;
    }

    public function getPage($pageNumber) {
        $this->numberOfCommits = $this->repository->getNumberOfCommits();

        $firstCommitIndex = $pageNumber * $this->commitsPerPage;
        $lastCommitIndex = ($pageNumber + 1) * $this->commitsPerPage;

        if ($lastCommitIndex >= $this->numberOfCommits) {
            $range = sprintf("HEAD~%s", $firstCommitIndex);
            $this->isLastPage = true;
        } else {
            $range = sprintf("HEAD~%s..HEAD~%s", $lastCommitIndex, $firstCommitIndex);
            $this->isLastPage = false;
        }

        return $this->repository->log($range);
    }

    public function isLastPage() {
        return $this->isLastPage;
    }

    public function setCommitsPerPage($commitsPerPage) {
        $this->commitsPerPage = $commitsPerPage;
    }

    public function getPrettySteps($currentPage) {
        $page = $currentPage;
        $pageCount = ceil($this->numberOfCommits / (double)$this->commitsPerPage);

        if ($pageCount < 2) {
            return array();
        }

        $arr = range(max(0, $page - 3), min($pageCount - 1, $page + 3));
        $count = 4;
        $quotient = ($pageCount - 1) / $count;
        for ($i = 0; $i <= $count; $i++) {
            $arr[] = round($quotient * $i);
        }
        sort($arr);
        $steps = array_values(array_unique($arr));

        return $steps;
    }
}