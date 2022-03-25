<?php

/**
 * Class representing a request to rebase a specific change based on a download command from
 * gerrit
 */

namespace MediaWiki\Tools\ForceRebase;

class RebaseRequest {

	/** @var string */
	private $originalCommand;

	/** @var string */
	private $repoName;

	/** @var string */
	private $quashedRepoName;

	/** @var string */
	private $changeRef;

	/** @var string */
	private $gitWithDir;

	/** @var string */
	private $targetBranch;

	/**
	 * @param string $originalCommand
	 * @param string $repoName
	 * @param string $changeRef
	 * @param string $targetBranch
	 */
	public function __construct(
		string $originalCommand,
		string $repoName,
		string $changeRef,
		string $targetBranch
	) {
		// entire `git fetch ... FETCH_HEAD` command
		$this->originalCommand = $originalCommand;
		// eg `mediawiki/extensions/examples`
		$this->repoName = $repoName;
		// eg refs/changes/47/770047/1
		$this->changeRef = $changeRef;
		// 'master' or 'main'
		$this->targetBranch = $targetBranch;

		// Squash the repo name to replace subfolder slashes with underscores, so that
		// all cloned repos can go in the same folder here without worrying about
		// conflicts or nesting
		$this->squashedRepoName = str_replace( '/', '_', $repoName );

		// For running git commands in the directory of the repo this is for
		// now in force-rebase/src, want to use force-rebase/repositories/squashedRepoName
		$this->gitWithDir = "git -C ../repositories/{$this->squashedRepoName}";
	}

	/**
	 * @return string
	 */
	public function getOriginalCommand(): string {
		return $this->originalCommand;
	}

	/**
	 * @return string
	 */
	public function getRepoName(): string {
		return $this->repoName;
	}

	/**
	 * @return string
	 */
	public function getSquashedRepoName(): string {
		return $this->squashedRepoName;
	}

	/**
	 * @return string
	 */
	public function getGitWithDir(): string {
		return $this->gitWithDir;
	}

	/**
	 * @return string
	 */
	public function getTargetBranch(): string {
		return $this->targetBranch;
	}

	/**
	 * Clone command in case we don't already have a copy of the repo
	 *
	 * @return string
	 */
	public function getCloneCommand(): string {
		$gerritSource = "https://gerrit.wikimedia.org/r/{$this->repoName}";
		// now in force-rebase/src, want to use force-rebase/repositories
		return "git -C ../repositories clone {$gerritSource} {$this->squashedRepoName}";
	}

	/**
	 * Pull command in case we already have a copy of the repo
	 *
	 * @return string
	 */
	public function getUpdateCommand(): string {
		// ensure we are on target branch and delete any to-rebase branch if one exists,
		// put last because we don't care about any failure
		return "{$this->gitWithDir} checkout {$this->targetBranch}"
			. " && {$this->gitWithDir} pull"
			. " && {$this->gitWithDir} branch -D to-rebase";
	}

	/**
	 * Download command to fetch the actual change to rebase
	 *
	 * @return string
	 */
	public function getDownloadCommand(): string {
		$source = "https://gerrit.wikimedia.org/r/{$this->repoName} {$this->changeRef}";
		return "{$this->gitWithDir} fetch $source "
			. "&& {$this->gitWithDir} checkout -b to-rebase FETCH_HEAD";
	}

}
