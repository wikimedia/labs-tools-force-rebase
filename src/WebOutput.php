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
		return Html::rawElement(
			'html',
			[],
			$this->getHeadElement() . $this->getBodyElement()
		);
	}

	/**
	 * Get the <head> element and contents, for now just includes the page title
	 *
	 * @return string
	 */
	private function getHeadElement(): string {
		$title = Html::element( 'title', [], $this->pageTitle );
		$css = Html::element(
			'link',
			[ 'rel' => 'stylesheet', 'href' => '/styles.css' ]
		);
		return Html::rawElement( 'head', [], $title . $css );
	}

	/**
	 * Get the <body> element and contents
	 *
	 * @return string
	 */
	private function getBodyElement(): string {
		return Html::rawElement(
			'body',
			[],
			$this->getHeading() . $this->getPageContent()
		);
	}

	/**
	 * Get the main content div and contents
	 *
	 * @return string
	 */
	private function getPageContent(): string {
		return Html::rawElement(
			'div',
			[ 'id' => 'main-content' ],
			$this->content
		);
	}

	/**
	 * Get the heading div and contents, including links to this source code
	 *
	 * @return string
	 */
	private function getHeading(): string {
		$baseLink = Html::element(
			'a',
			[ 'href' => 'https://force-rebase.toolforge.org/index.php' ],
			'Force rebase'
		);
		$sourceCodeLink = Html::element(
			'a',
			[ 'href' => 'https://gerrit.wikimedia.org/g/labs/tools/force-rebase/' ],
			'Source code'
		);
		$headingLinks = $baseLink . ' | ' . $sourceCodeLink;
		if ( $this->includeLogoutLink ) {
			$headingLinks .= ' | ' . Html::element(
				'a',
				[ 'href' => '/index.php?action=logout' ],
				'Log out'
			);
		}
		$heading = Html::rawElement(
			'div',
			[ 'id' => 'site-heading' ],
			$headingLinks . Html::element( 'hr' )
		);
		return $heading;
	}

}
