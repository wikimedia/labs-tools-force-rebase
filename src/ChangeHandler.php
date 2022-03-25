<?php

/**
 * Logic to actually download and rebase a patch
 */

namespace MediaWiki\Tools\ForceRebase;

// This class uses shell_exec to actually do git stuff
// phpcs:disable MediaWiki.Usage.ForbiddenFunctions.shell_exec

class ChangeHandler {

	/** @var RebaseRequest */
	private $rebaseRequest;

	/** @var string */
	private $gitWithDir;

	/**
	 * @param RebaseRequest $rebaseRequest
	 */
	public function __construct(
		RebaseRequest $rebaseRequest
	) {
		$this->rebaseRequest = $rebaseRequest;
		$this->gitWithDir = $rebaseRequest->getGitWithDir();
	}

	/**
	 * Show a result from the shell commands
	 *
	 * @param string $rawLabel
	 * @param string|null $rawResult
	 */
	private function outputCommandResult(
		string $rawLabel,
		?string $rawResult
	): void {
		if ( $rawResult === '' || $rawResult === null ) {
			$label = HTML::rawElement(
				'p',
				[],
				HTML::element( 'b', [], $rawLabel ) . ': -nothing-'
			);
			$result = '';
		} else {
			$label = HTML::rawElement(
				'p',
				[],
				HTML::element( 'b', [], $rawLabel ) . ':'
			);
			$result = HTML::element( 'pre', [], $rawResult );
		}
		$updateWrapper = HTML::rawElement(
			'div',
			[ 'class' => 'console-update' ],
			$label . $result
		);
		echo $updateWrapper;
	}

	/**
	 * Step 1: ensure that we have an up-to-date local clone, either creating a new clone
	 * or running `git pull` for the existing one
	 */
	public function ensureUpdatedLocalClone(): void {
		if ( $this->haveLocalClone() ) {
			// Update
			$command = $this->rebaseRequest->getUpdateCommand();
		} else {
			// Fresh clone
			$command = $this->rebaseRequest->getCloneCommand();
		}
		$result = shell_exec( $command );
		$this->outputCommandResult( __METHOD__, $result );
	}

	/**
	 * Step 2: ensure git config is set correctly
	 */
	public function ensureGitConfig(): void {
		// TODO make these configurable
		shell_exec( "{$this->gitWithDir} config user.email dannys712.wiki+bot@gmail.com" );
		shell_exec( "{$this->gitWithDir} config user.name ForceRebase" );
	}

	/**
	 * Step 3: download and checkout the patch to rebase
	 */
	public function downloadTargetPatch(): void {
		$command = $this->rebaseRequest->getDownloadCommand();
		$result = shell_exec( $command );
		$this->outputCommandResult( __METHOD__, $result );
	}

	/**
	 * Step 4: do the rebase against target branch
	 */
	public function forceRebasePatch(): void {
		$targetBranch = $this->rebaseRequest->getTargetBranch();
		$result = shell_exec( "{$this->gitWithDir} rebase {$targetBranch}" );
		$this->outputCommandResult( __METHOD__, $result );
		if ( strpos( $result, "Resolve all conflicts manually" ) !== false ) {
			$result = shell_exec( "{$this->gitWithDir} add ." );
			$this->outputCommandResult( 'git add .', $result );
			$result = shell_exec(
				"GIT_EDITOR=true {$this->gitWithDir} rebase --continue"
			);
			$this->outputCommandResult( 'git rebase --continue', $result );
		}
	}

	/**
	 * Step 5: upload the changed version
	 */
	public function uploadRebase(): void {
		// Just need `git push`, the change id present means that the existing patch
		// is updated
		// false = fake password for logging
		$this->outputCommandResult( "Push command", $this->getPushCommand( false ) );
		// true = real password for executing
		$pushCmd = $this->getPushCommand( true );
		$result = shell_exec( $pushCmd );
		$this->outputCommandResult( __METHOD__, $result );
	}

	/**
	 * Check if there is already a local clone for the target repo
	 *
	 * @return bool
	 */
	private function haveLocalClone(): bool {
		$repoName = $this->rebaseRequest->getSquashedRepoName();
		// Currently in force-rebase/src/ChangeHandler.php,
		// need to check force-rebase/repositories/$repoName and
		// force-rebase/repositories/$repoName.git
		// __DIR__ is force-rebase/src, use dirname() to go up to force-rebase
		$reposBase = dirname( __DIR__ ) . "/repositories";
		// file_exists also accepts directories
		return file_exists( "$reposBase/$repoName" )
			&& file_exists( "$reposBase/$repoName/.git" );
	}

	/**
	 * Get the push command, with either the real password, for executing, or a placeholder,
	 * for showing the user
	 *
	 * @param bool $useRealPassword
	 * @return string
	 */
	private function getPushCommand( bool $useRealPassword ): string {
		// TODO CONFIGURATION
		$gerritAccountName = "d712-bot";
		// HTTP authentication code, not normal account password
		$gerritAccountPass = "<secret password>";
		if ( $useRealPassword ) {
			$gerritAccountPass = Configuration::getSecret( 'gerritAccountPassword' );
			$gerritAccountPass = urlencode( $gerritAccountPass );
		}
		$gerritAuth = "{$gerritAccountName}:{$gerritAccountPass}";
		$targetRepo = $this->rebaseRequest->getRepoName();
		$gerritTarget = "https://{$gerritAuth}@gerrit.wikimedia.org/r/a/{$targetRepo}";

		$targetBranch = $this->rebaseRequest->getTargetBranch();
		return "{$this->gitWithDir} push $gerritTarget HEAD:refs/for/{$targetBranch}";
	}

}
