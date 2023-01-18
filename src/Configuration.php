<?php

/**
 * Class for configuration values (including secrets retrieved from secrets.php)
 */

namespace MediaWiki\Tools\ForceRebase;

use InvalidArgumentException;
use LogicException;

class Configuration {

	/** @var array|false */
	private static $secretValues = false;

	/**
	 * Configuration settings that at the moment cannot be changed, but might be useful to
	 * override at some point (specifically if someone else wants to fork this repo).
	 *
	 * Keys are known settings, values are the setting value to use
	 *
	 * @var array<string, mixed>
	 */
	private const CONFIG_SETTINGS = [
		// name and email to configure in git for the commits done by this tool
		'git-config-name' => 'ForceRebase',
		'git-config-email' => 'dannys712.wiki+bot@gmail.com',
		// gerrit HTTP credentials username
		'gerrit-account-name' => 'd712-bot',
		// the HTTP password is in secrets as `gerritAccountPassword`
	];

	/**
	 * @param string $settingName
	 * @return mixed
	 */
	public static function getSetting( string $settingName ) {
		// Not using isset in case the value is null
		if ( !array_key_exists( $settingName, self::CONFIG_SETTINGS ) ) {
			throw new InvalidArgumentException( "Unknown setting '$settingName'" );
		}
		return self::CONFIG_SETTINGS[ $settingName ];
	}

	/**
	 * @param string $secretName
	 * @return mixed
	 */
	public static function getSecret( string $secretName ) {
		if ( self::$secretValues === false ) {
			self::loadSecrets();
		}
		// Not using isset in case the value is null
		if ( !array_key_exists( $secretName, self::$secretValues ) ) {
			throw new InvalidArgumentException( "Unknown secret '$secretName'" );
		}
		return self::$secretValues[ $secretName ];
	}

	/**
	 * Load the secrets from force-rebase/secrets.php, which returns an array
	 */
	private static function loadSecrets(): void {
		// new in force-rebase/src/Configuration.php, want force-rebase/secrets.php
		$secretFile = dirname( __DIR__ ) . '/secrets.php';
		if ( !file_exists( $secretFile ) ) {
			throw new LogicException( "Missing secrets file '$secretFile'" );
		}
		$secretValues = require $secretFile;
		if ( !is_array( $secretValues ) ) {
			throw new LogicException(
				"Secrets file '$secretFile' did not return an array"
			);
		}
		self::$secretValues = $secretValues;
	}

}
