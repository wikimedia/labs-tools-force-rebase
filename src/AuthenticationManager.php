<?php

/**
 * Integration with mediawiki/oauthclient library for authentication
 *
 * Currently integrates with the beta cluster, eventually will use production wikis
 */

namespace MediaWiki\Tools\ForceRebase;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use RuntimeException;

class AuthenticationManager {

	/** @var string */
	private const OAUTH_URL = 'https://meta.wikimedia.beta.wmflabs.org/w/index.php?title=Special:OAuth';

	/** @var string */
	private const OAUTH_CONSUMER_KEY = '5701f35729f0d8c3f3514fd56522bd7c';

	/** @var string */
	private const OAUTH_USER_AGENT = 'ForceRebase 0.1 dev';

	/** @var Client|null */
	private $client = null;

	/**
	 * @return AuthenticationManager
	 */
	public static function getInstance(): AuthenticationManager {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new AuthenticationManager();
		}
		return $instance;
	}

	/**
	 * @return Client
	 */
	public function getClient(): Client {
		if ( $this->client !== null ) {
			return $this->client;
		}
		$clientConfig = new ClientConfig( self::OAUTH_URL );
		$consumer = new Consumer(
			self::OAUTH_CONSUMER_KEY,
			Configuration::getSecret( 'oauthConsumerSecret' )
		);
		$clientConfig->setConsumer( $consumer );
		$clientConfig->setUserAgent( self::OAUTH_USER_AGENT );
		$this->client = new Client( $clientConfig );
		return $this->client;
	}

	/**
	 * Get the logged in user identity if authenticated, or false if not
	 *
	 * @return string|false
	 */
	public function getAuthenticatedName() {
		if ( !isset( $_SESSION['access_key'] )
			|| !isset( $_SESSION['access_secret'] )
		) {
			return false;
		}
		// create access Token from the session
		$accessToken = new Token(
			$_SESSION['access_key'],
			$_SESSION['access_secret']
		);
		$identity = $this->getClient()->identify( $accessToken );
		return $identity->username;
	}

	/**
	 * Start the process of authentication with OAuth and get the wiki url for
	 * enabling
	 *
	 * @return string
	 */
	public function startAuthenticationProcess(): string {
		// Initiate request with the client, store the token in the session for later,
		// and return the authorization url
		[ $authorizationUrl, $requestToken ] = $this->getClient()->initiate();
		$_SESSION['request_key'] = $requestToken->key;
		$_SESSION['request_secret'] = $requestToken->secret;
		return $authorizationUrl;
	}

	/**
	 * Triggered when returning to the callback url after authorization
	 */
	public function handleLoginCallback(): void {
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		if ( !isset( $_GET['oauth_verifier'] ) ) {
			throw new RuntimeException( 'oauth_verifier not set' );
		}
		// create request Token from the session
		$sessionToken = new Token(
			$_SESSION['request_key'],
			$_SESSION['request_secret']
		);
		// Get the access token from the wiki
		$accessToken = $this->getClient()->complete(
			$sessionToken,
			// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
			$_GET['oauth_verifier']
		);
		// Have successfully authenticated, save the result in the session and clear the
		// unneeded request_ session values
		$_SESSION['access_key'] = $accessToken->key;
		$_SESSION['access_secret'] = $accessToken->secret;
		unset( $_SESSION['request_key'] );
		unset( $_SESSION['request_secret'] );
		// Redirect back to base url so that any refresh attempts don't break things
		header( 'Location: https://force-rebase.toolforge.org/index.php' );
		die();
	}

	/**
	 * Log out of the existing authenticated session
	 */
	public function logOut(): void {
		session_destroy();
	}

}
