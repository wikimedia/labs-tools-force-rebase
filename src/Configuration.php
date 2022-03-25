<?php

/**
 * Class for configuration values retrieved from secrets.php
 */

namespace MediaWiki\Tools\ForceRebase;

use InvalidArgumentException;
use LogicException;

class Configuration {

	/** @var array|false */
	private static $secretValues = false;

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
			throw new InvalidArgumentException( "Unknown secret $secretName" );
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
