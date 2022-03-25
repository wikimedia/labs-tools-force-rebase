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

	/** @var bool */
	private $includeLogoutLink = false;

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
	 * Include the logout link in the header
	 */
	public function enableLogoutLink(): void {
		$this->includeLogoutLink = true;
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
		$heading = $this->getHeading();
		return "<body>{$heading}{$pageContent}</body>";
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
	 * Get the heading div and contents, including links to this source code
	 *
	 * @return string
	 */
	private function getHeading(): string {
		$baseUrl = "https://force-rebase.toolforge.org/index.php";
		$baseLink = "<a href=\"$baseUrl\">Force rebase</a>";
		$sourceCode = "https://gerrit.wikimedia.org/r/plugins/gitiles/labs/tools/force-rebase/";
		$sourceCodeLink = "<a href=\"$sourceCode\">Source code</a>";
		$logoutLink = '';
		if ( $this->includeLogoutLink ) {
			$logoutLink = ' | <a href="/index.php?action=logout">Log out</a>';
		}
		$heading = '<div id="site-heading">'
			. $baseLink
			. ' | ' . $sourceCodeLink
			. $logoutLink
			. '<hr /></div>';
		return $heading;
	}

}
