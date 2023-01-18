<?php

namespace MediaWiki\Tools\ForceRebase\Test;

use InvalidArgumentException;
use MediaWiki\Tools\ForceRebase\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Tools\ForceRebase\Configuration
 */
class ConfigurationTest extends TestCase {

	public function testValidSetting() {
		$this->assertSame( 'd712-bot', Configuration::getSetting( 'gerrit-account-name' ) );
	}

	public function testInvalidSetting() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( "Unknown setting 'missing'" );
		Configuration::getSetting( 'missing' );
	}

}
