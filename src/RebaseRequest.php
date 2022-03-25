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

	/**
	 * @param string $originalCommand
	 * @param string $repoName
	 * @param string $changeRef
	 */
	public function __construct(
		string $originalCommand,
		string $repoName,
		string $changeRef
	) {
		// entire `git fetch ... FETCH_HEAD` command
		$this->originalCommand = $originalCommand;
		// eg `mediawiki/extensions/examples`
		$this->repoName = $repoName;
		// eg refs/changes/47/770047/1
		$this->changeRef = $changeRef;

		// Squash the repo name to replace subfolder slashes with underscores, so that
		// all cloned repos can go in the same folder here without worrying about
		// conflicts or nesting
		$this->squashedRepoName = str_replace( '/', '_', $repoName );
	}

	/**
	 * @return string
	 */
	public function getOriginalCommand(): string {
		return $this->originalCommand;
	}

	/**
	 * Clone command in case we don't already have a copy of the repo
	 *
	 * @return string
	 */
	public function getCloneCommand(): string {
		$gerritSource = "https://gerrit.wikimedia.org/r/{$this->repoName}";
		return "git --git-dir=repositories clone {$gerritSource} {$this->squashedRepoName}";
	}

	/**
	 * Pull command in case we already have a copy of the repo
	 *
	 * @return string
	 */
	public function getUpdateCommand(): string {
		return "git --git-dir=repositories/{$this->squashedRepoName}/.git pull";
	}

	/**
	 * Download command to fetch the actual change to rebase
	 *
	 * @return string
	 */
	public function getDownloadCommand(): string {
		$gitWithDir = "git --git-dir=repositories/{$this->squashedRepoName}/.git";
		$source = "https://gerrit.wikimedia.org/r/{$this->repoName} {$this->changeRef}";
		return "$gitWithDir fetch $source && $gitWithDir checkout -b to-rebase FETCH_HEAD";
	}

}
