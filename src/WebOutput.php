<?php

/**
 * Output handler for a request
 */

namespace MediaWiki\Tools\ForceRebase;

class WebOutput {

	/** @var string */
	private $pageTitle = 'Force rebase';

	/** @var string */
	private $content = '';

	/**
	 * @param string $newTitle
	 */
	public function setPageTitle( string $newTitle ): void {
		$this->pageTitle = $newTitle;
	}

	/**
	 * @param string $htmlContent
	 */
	public function setContent( string $htmlContent ): void {
		$this->content = $htmlContent;
	}

	/**
	 * Get the overall <html> contents for the page
	 *
	 * @return string
	 */
	public function getHtmlOutput(): string {
		$head = $this->getHeadElement();
		$body = $this->getBodyElement();
		return "<html>{$head}{$body}</html>";
	}

	/**
	 * Get the <head> element and contents, for now just includes the page title
	 *
	 * @return string
	 */
	private function getHeadElement(): string {
		$title = "<title>{$this->pageTitle}</title>";
		$css = '<link rel="stylesheet" href="/styles.css">';
		return "<head>{$title}{$css}</head>";
	}

	/**
	 * Get the <body> element and contents
	 *
	 * @return string
	 */
	private function getBodyElement(): string {
		$pageContent = $this->getPageContent();
		$footer = $this->getFooter();
		return "<body>{$pageContent}<hr />{$footer}</body>";
	}

	/**
	 * Get the main content div and contents
	 *
	 * @return string
	 */
	private function getPageContent(): string {
		return "<div id=\"main-content\">{$this->content}</div>";
	}

	/**
	 * Get the footer div and contents, including links to this source code
	 *
	 * @return string
	 */
	private function getFooter(): string {
		$sourceCode = "https://gerrit.wikimedia.org/r/plugins/gitiles/labs/tools/force-rebase/";
		$sourceCodeLink = "<a href=\"$sourceCode\">Source code</a>";
		$footer = "<div id=\"site-footer\">$sourceCodeLink</div>";
		return $footer;
	}

}
