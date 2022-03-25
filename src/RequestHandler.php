<?php

/**
 * Entry point for web requests
 */

namespace MediaWiki\Tools\ForceRebase;

class RequestHandler {

	/**
	 * Actually run everything
	 */
	public function run(): void {
		$webOutput = new WebOutput();

		$rebaseRequest = $this->getRequestInput();
		if ( !( $rebaseRequest instanceof RebaseRequest ) ) {
			// Not provided or invalid, show form
			$inputForm = $this->getFormOutput( $rebaseRequest );
			$webOutput->setContent( $inputForm );
		} else {
			// Provided and valid, show that it was accepted
			$stepsOutput = $this->getStepsDisplay( $rebaseRequest );
			$webOutput->setContent( $stepsOutput );
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
	 * Get the form to show to get the copy snippet, with an optional error message for
	 * a prior input that was invalid
	 *
	 * @param string|false $maybeError
	 * @return string
	 */
	private function getFormOutput( $maybeError ): string {
		$errorMessage = '';
		if ( $maybeError !== false ) {
			$errorMessage = "\n<br><span class=\"input-value-error\">"
				. htmlspecialchars( $maybeError )
				. '</span>';
		}
		$inputField = '<input type="text" name="copysnippet" value="" >';
		$submitButton = '<input type="submit" name="submit" value="Submit" >';
		return '<form method="post" action="index.php" id="copy-snippet-form">'
			. "\nDownload snippet: $inputField"
			. $errorMessage
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
	 * Retrieve the RebaseRequest for the posted parameter 'copysnippet' if present and valid,
	 * or an error message, or false if not provided at all
	 *
	 * @return string|false|RebaseRequest
	 */
	private function getRequestInput() {
		if ( $_SERVER["REQUEST_METHOD"] !== "POST" ) {
			return false;
		}
		// Need to use super globals
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$parameter = $_POST['copysnippet'] ?? false;
		if ( $parameter === false ) {
			return false;
		}
		$snippet = trim( $parameter );
		$snippet = stripslashes( $snippet );

		if ( $snippet === '' ) {
			return 'Missing required snippet';
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
			return 'Does not match regex';
		}
		// should refer to the correct change id in the branch
		if ( $matches[3] !== $matches[4] ) {
			return 'Does not have correct change-# number';
		}
		$repoName = $matches[1];
		// Validate known repos
		// For now, only recognize:
		// * mediawiki/extensions/examples
		if (
			$repoName !== 'mediawiki/extensions/examples'
		) {
			return "Unknown repo: $repoName";
		}
		return new RebaseRequest(
			$snippet,
			$repoName,
			$matches[2]
		);
	}

}
