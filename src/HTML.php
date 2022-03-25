<?php

/**
 * Utility for building HTML elements, inspired by mediawiki core's Html class
 */

namespace MediaWiki\Tools\ForceRebase;

class HTML {

	/**
	 * Contents are escaped if needed
	 *
	 * @param string $tag
	 * @param array $attributes
	 * @param string|null $contents Null contents means self-closing tag
	 * @return string
	 */
	public static function element(
		string $tag,
		array $attributes = [],
		?string $contents = null
	): string {
		return self::rawElement(
			$tag,
			$attributes,
			( $contents === null ? null : htmlspecialchars( $contents ) )
		);
	}

	/**
	 * Raw element, contents used as-is
	 *
	 * @param string $tag
	 * @param array $attributes
	 * @param string|null $contents Null contents means self-closing tag
	 * @return string
	 */
	public static function rawElement(
		string $tag,
		array $attributes = [],
		?string $contents = null
	): string {
		$res = "<$tag";
		foreach ( $attributes as $name => $rawValue ) {
			$useValue = htmlspecialchars( $rawValue, ENT_QUOTES );
			$res .= " $name=\"$useValue\"";
		}
		if ( $contents === null ) {
			return $res . "/>";
		}
		return "{$res}>{$contents}</{$tag}>";
	}

}
