<?php
/**
 * Public artifact cache.
 *
 * @package PressAgent
 */

namespace PressAgent\Support;

/**
 * Caches site-public, user-independent PressAgent artifacts.
 */
final class Cache {
	private const MANIFEST = 'pressagent_manifest_v1';
	private const LLMS     = 'pressagent_llms_v1';

	/**
	 * Get the cached manifest.
	 *
	 * @param string $context Discovery context hash.
	 * @return array<string, mixed>|false Cached manifest or false.
	 */
	public static function get_manifest( string $context ) {
		$value = get_transient( self::MANIFEST );

		if (
			! is_array( $value )
			|| ! isset( $value['context'], $value['value'] )
			|| ! is_string( $value['context'] )
			|| ! hash_equals( $context, $value['context'] )
			|| ! is_array( $value['value'] )
		) {
			return false;
		}

		return $value['value'];
	}

	/**
	 * Cache the manifest.
	 *
	 * @param array<string, mixed> $value   Manifest value.
	 * @param string               $context Discovery context hash.
	 * @return void
	 */
	public static function set_manifest( array $value, string $context ): void {
		set_transient(
			self::MANIFEST,
			array(
				'context' => $context,
				'value'   => $value,
			),
			Settings::get_cache_ttl()
		);
	}

	/**
	 * Get cached llms.txt.
	 *
	 * @param string $context Discovery context hash.
	 * @return string|false Cached llms.txt or false.
	 */
	public static function get_llms( string $context ) {
		$value = get_transient( self::LLMS );

		if (
			! is_array( $value )
			|| ! isset( $value['context'], $value['value'] )
			|| ! is_string( $value['context'] )
			|| ! hash_equals( $context, $value['context'] )
			|| ! is_string( $value['value'] )
		) {
			return false;
		}

		return $value['value'];
	}

	/**
	 * Cache llms.txt.
	 *
	 * @param string $value   llms.txt value.
	 * @param string $context Discovery context hash.
	 * @return void
	 */
	public static function set_llms( string $value, string $context ): void {
		set_transient(
			self::LLMS,
			array(
				'context' => $context,
				'value'   => $value,
			),
			Settings::get_cache_ttl()
		);
	}

	/**
	 * Flush all public artifact caches.
	 *
	 * @return void
	 */
	public static function flush(): void {
		delete_transient( self::MANIFEST );
		delete_transient( self::LLMS );
	}
}
