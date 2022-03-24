<?php

/**
 * Current entry point for web requests, launched by index.php
 */

namespace MediaWiki\Tools\ForceRebase;

class WebOutput {

	public function run() {
		echo $this->getHelloWorldOutput();
	}

	/**
	 * Temporary placeholder output content
	 *
	 * @return string
	 */
	private function getHelloWorldOutput(): string {
		$head = "<head><title>Force rebase</title></head>";
		$body = "<body><p>Hello, world!</p></body>";
		$html = "<html>$head$body</html>";
		return $html;
	}

}
