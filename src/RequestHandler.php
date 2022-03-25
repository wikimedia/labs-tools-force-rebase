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
		$webOutput->setPageTitle( 'Force rebase | RequestHandler' );
		$webOutput->setContent(
			'<p>Hello from RequestHandler</p>'
		);
		echo $webOutput->getHtmlOutput();
	}

}
