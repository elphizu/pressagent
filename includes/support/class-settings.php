<?php
/**
 * Plugin settings.
 *
 * @package PressAgent
 */

namespace PressAgent\Support;

/**
 * Provides validated PressAgent settings.
 */
final class Settings {
	public const OPTION = 'pressagent_settings';
	public const GROUP  = 'pressagent';

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Get defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'llms_enabled' => true,
			'cache_ttl'    => HOUR_IN_SECONDS,
		);
	}

	/**
	 * Check whether llms.txt is enabled.
	 *
	 * @return bool Whether llms.txt is enabled.
	 */
	public static function is_llms_enabled(): bool {
		$settings = self::get();
		return ! empty( $settings['llms_enabled'] );
	}

	/**
	 * Get the cache lifetime.
	 *
	 * @return int Cache lifetime in seconds.
	 */
	public static function get_cache_ttl(): int {
		$settings = self::get();
		return min( DAY_IN_SECONDS, max( MINUTE_IN_SECONDS, absint( $settings['cache_ttl'] ) ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		return array(
			'llms_enabled' => ! empty( $input['llms_enabled'] ),
			'cache_ttl'    => min( DAY_IN_SECONDS, max( MINUTE_IN_SECONDS, absint( $input['cache_ttl'] ?? HOUR_IN_SECONDS ) ) ),
		);
	}
}
