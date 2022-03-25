<?php

/**
 * Entry point for web requests
 */

namespace MediaWiki\Tools\ForceRebase;

use stdClass;

class RequestHandler {

	/**
	 * Actually run everything
	 */
	public function run(): void {
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$action = $_GET['action'] ?? 'view';
		$authManager = AuthenticationManager::getInstance();
		if ( $action === 'callback' ) {
			$authManager->handleLoginCallback();
		} elseif ( $action === 'logout' ) {
			$this->handleLogout();
			return;
		}

		$currentName = $authManager->getAuthenticatedName();
		if ( $currentName === false ) {
			$this->showLogin();
			return;
		}
		$webOutput = new WebOutput();
		$webOutput->enableLogoutLink();

		$currentNameDisplay = '<div><p>Logged in as: '
			. htmlspecialchars( $currentName )
			. '</p></div>';

		$rebaseRequest = $this->getRequestInput();
		if ( !( $rebaseRequest instanceof RebaseRequest ) ) {
			// Not provided or invalid, show form
			$inputForm = $this->getFormOutput( $rebaseRequest );
			$webOutput->setContent( $currentNameDisplay . $inputForm );
		} else {
			// Provided and valid, show that it was accepted
			$stepsOutput = $this->getStepsDisplay( $rebaseRequest );
			$webOutput->setContent( $currentNameDisplay . $stepsOutput );
		}
		echo $webOutput->getHtmlOutput();
		if ( $rebaseRequest instanceof RebaseRequest ) {
			$changeHandler = new ChangeHandler( $rebaseRequest );
			$changeHandler->ensureUpdatedLocalClone();
			$changeHandler->ensureGitConfig();
			$changeHandler->downloadTargetPatch();
			$changeHandler->forceRebasePatch();
			$changeHandler->uploadRebase();
		}
	}

	/**
	 * Show the login link
	 */
	private function showLogin(): void {
		$authUrl = AuthenticationManager::getInstance()->startAuthenticationProcess();
		$webOutput = new WebOutput();
		$webOutput->setContent(
			"<a href=\"$authUrl\">Login with OAuth (beta cluster)</a>"
		);
		echo $webOutput->getHtmlOutput();
	}

	/**
	 * Logout and show success message
	 */
	private function handleLogout(): void {
		AuthenticationManager::getInstance()->logOut();
		$webOutput = new WebOutput();
		$webOutput->setContent(
			'<p>Successfully logged out</p>'
		);
		echo $webOutput->getHtmlOutput();
	}

	/**
	 * Get the form to show to get the copy snippet and target branch, pre-filling the last
	 * values and optionally showing error messages
	 *
	 * @param stdClass|false $maybePriorInput
	 * @return string
	 */
	private function getFormOutput( $maybePriorInput ): string {
		// If $maybeError isn't false, it should have the fields
		// copysnippetInput, copysnippetError, branchInput, branchError
		// with the inputs being strings and the errors being strings or false
		$defaultSnippet = '';
		$defaultBranch = 'master';
		$snippetError = '';
		$branchError = '';
		if ( $maybePriorInput !== false ) {
			$defaultSnippet = $maybePriorInput->copysnippetInput;
			$defaultBranch = $maybePriorInput->branchInput;

			if ( $maybePriorInput->copysnippetError !== false ) {
				$snippetError = "\n<br><span class=\"input-value-error\">"
					. htmlspecialchars( $maybePriorInput->copysnippetError )
					. '</span>';
			}
			if ( $maybePriorInput->branchError !== false ) {
				$branchError = "\n<br><span class=\"input-value-error\">"
					. htmlspecialchars( $maybePriorInput->branchError )
					. '</span>';
			}
		}
		$snippetField = '<input type="text" name="copysnippet" value="'
			. $defaultSnippet . '" >';
		$branchField = '<input type="text" name="targetbranch" value="'
			. $defaultBranch . '" >';
		$submitButton = '<input type="submit" name="submit" value="Submit" >';

		return '<form method="post" action="index.php" id="copy-snippet-form">'
			. "\nDownload snippet: $snippetField"
			. $snippetError
			. "\n<br>Target branch: $branchField"
			. $branchError
			. "<br>$submitButton"
			. '</form>';
	}

	/**
	 * Get an explanation of the steps that will be done to handle a rebase
	 *
	 * @param RebaseRequest $request
	 * @return string
	 */
	private function getStepsDisplay( RebaseRequest $request ): string {
		$inputCommand = htmlspecialchars( $request->getOriginalCommand() );
		$cloneCommand = htmlspecialchars( $request->getCloneCommand() );
		$updateCommand = htmlspecialchars( $request->getUpdateCommand() );
		$downloadCommand = htmlspecialchars( $request->getDownloadCommand() );

		$output = '';
		$output .= "<p>For the input command: <b>$inputCommand</b>:</p>\n";
		$output .= '<p>If there is not already a copy of the code, it will be cloned with '
			. "<b>$cloneCommand</b>, otherwise the existing clone will be updated with "
			. "<b>$updateCommand</b>.</p>\n";
		$output .= '<p>The patch to rebase will then be downloaded with '
			. "<b>$downloadCommand</b>.</p>\n";
		return "<div>$output</div>";
	}

	/**
	 * Retrieve the RebaseRequest for the posted parameters 'copysnippet' and 'targetbranch'
	 * if present and valid, or an stdClass with error messages and the inputs to prefill,
	 * false if not provided at all
	 *
	 * @return stdClass|false|RebaseRequest
	 */
	private function getRequestInput() {
		if ( $_SERVER["REQUEST_METHOD"] !== "POST" ) {
			return false;
		}
		$inputs = (object)[
			'copysnippetInput' => '',
			'copysnippetError' => false,
			'branchInput' => '',
			'branchError' => false,
		];
		// Do branch first so that we can early return easier for the snippet errors
		// Need to use super globals
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$branchParam = $_POST['targetbranch'] ?? '';
		$branch = trim( $branchParam );
		$branch = stripslashes( $branch );
		$inputs->branchInput = $branch;
		if ( $branch === '' ) {
			$inputs->branchError = 'Missing target branch';
		} elseif ( $branch !== 'master' && $branch !== 'main' ) {
			$inputs->branchError = "Only branches 'master' and 'main' are supported, "
				. "got '$branch'";
		}

		// Need to use super globals
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$snippetParam = $_POST['copysnippet'] ?? '';
		$snippet = trim( $snippetParam );
		$snippet = stripslashes( $snippet );
		$inputs->copysnippetInput = $snippet;

		if ( $snippet === '' ) {
			$inputs->copysnippetError = 'Missing required snippet';
			return $inputs;
		}

		// Test the input, should be in the form of:
		// git fetch https://gerrit.wikimedia.org/r/{repo} refs/changes/{#1}/{#2}/{#3} &
		//   & git checkout -b change-{#2} FETCH_HEAD
		// (with no space between &&, split for phpcs)
		// where {repo} is a known repository name (see below)
		$expectReg = '/^git fetch https:\/\/gerrit\.wikimedia\.org\/r\/(\S+) '
			. '(refs\/changes\/\d+\/(\d+)\/\d+) && '
			. 'git checkout -b change-(\d+) FETCH_HEAD$/';
		$matches = [];
		if ( preg_match( $expectReg, $snippet, $matches ) !== 1 ) {
			$inputs->copysnippetError = 'Does not match regex';
			return $inputs;
		}
		// should refer to the correct change id in the branch
		if ( $matches[3] !== $matches[4] ) {
			$inputs->copysnippetError = 'Does not have correct change-# number';
			return $inputs;
		}
		$repoName = $matches[1];
		// Validate known repos
		// For now, only recognize:
		// * mediawiki/extensions/examples
		// * design/codex
		if (
			$repoName !== 'mediawiki/extensions/examples'
			&& $repoName !== 'design/codex'
		) {
			$inputs->copysnippetError = "Unknown repo: $repoName";
			return $inputs;
		}
		// After validating snippet, still error from branch
		if ( $inputs->branchError !== false ) {
			return $inputs;
		}

		return new RebaseRequest(
			$snippet,
			$repoName,
			$matches[2],
			$branch
		);
	}

}
