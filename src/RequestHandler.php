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

		$currentNameDisplay = HTML::element( 'p', [], "Logged in as: $currentName" );
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
			HTML::element(
				'a',
				[ 'href' => $authUrl ],
				'Login with OAuth (beta cluster)'
			)
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
			HTML::element( 'p', [], 'Successfully logged out' )
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
				$snippetError = "\n"
					. HTML::element( 'br' )
					. HTML::element(
						'span',
						[ 'class' => 'input-value-error' ],
						$maybePriorInput->copysnippetError
					);
			}
			if ( $maybePriorInput->branchError !== false ) {
				$branchError = "\n"
					. HTML::element( 'br' )
					. HTML::element(
						'span',
						[ 'class' => 'input-value-error' ],
						$maybePriorInput->branchError
					);
			}
		}
		$snippetField = HTML::element(
			'input',
			[ 'type' => 'text', 'name' => 'copysnippet', 'value' => $defaultSnippet, ]
		);
		$branchField = HTML::element(
			'input',
			[ 'type' => 'text', 'name' => 'targetbranch', 'value' => $defaultBranch, ]
		);
		$submitButton = HTML::element(
			'input',
			[ 'type' => 'submit', 'name' => 'submit', 'value' => 'Submit', ]
		);
		$form = HTML::rawElement(
			'form',
			[
				'method' => 'post',
				'action' => 'index.php',
				'id' => 'copy-snippet-form',
			],
			"\nDownload snippet: {$snippetField}{$snippetError}"
				. "\n" . HTML::element( 'br' )
				. "Target branch: {$branchField}{$branchError}"
				. "\n" . HTML::element( 'br' )
				. $submitButton
		);
		return $form;
	}

	/**
	 * Get an explanation of the steps that will be done to handle a rebase
	 *
	 * @param RebaseRequest $request
	 * @return string
	 */
	private function getStepsDisplay( RebaseRequest $request ): string {
		$inputCommand = HTML::element( 'b', [], $request->getOriginalCommand() );
		$cloneCommand = HTML::element( 'b', [], $request->getCloneCommand() );
		$updateCommand = HTML::element( 'b', [], $request->getUpdateCommand() );
		$downloadCommand = HTML::element( 'b', [], $request->getDownloadCommand() );

		$output = '';
		$output .= HTML::rawElement(
			'p',
			[],
			"For the input command: $inputCommand:"
		) . "\n";
		$output .= HTML::rawElement(
			'p',
			[],
			'If there is not already a copy of the code, it will be cloned with '
				. $cloneCommand . ', otherwise the existing clone will be updated '
				. "with $updateCommand."
		) . "\n";
		$output .= HTML::rawElement(
			'p',
			[],
			"The patch to rebase will then be downloaded with $downloadCommand."
		) . "\n";
		return HTML::rawElement( 'div', [], $output );
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
		// * mediawiki/core
		// * mediawiki/extensions/examples
		// * mediawiki/extensions/AbuseFilter
		// * mediawiki/extensions/Scribunto
		// * design/codex
		if (
			$repoName !== 'mediawiki/core'
			&& $repoName !== 'mediawiki/extensions/examples'
			&& $repoName !== 'mediawiki/extensions/AbuseFilter'
			&& $repoName !== 'mediawiki/extensions/Scribunto'
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
