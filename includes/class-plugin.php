<?php
/**
 * Core plugin class.
 *
 * @package PressAgent
 */

namespace PressAgent;

use PressAgent\Abilities\AbilityProvider;
use PressAgent\Admin\SettingsPage;
use PressAgent\Admin\SiteHealth;
use PressAgent\Discovery\Advertiser;
use PressAgent\Discovery\DiscoveryService;
use PressAgent\Discovery\LlmsController;
use PressAgent\Discovery\Manifest;
use PressAgent\Discovery\ManifestController;
use PressAgent\Discovery\McpStatus;
use PressAgent\Discovery\RestController;
use PressAgent\Support\Cache;
use PressAgent\Support\Settings;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {

		$discovery  = new DiscoveryService();
		$manifest   = new Manifest( $discovery );
		$mcp_status = new McpStatus( $discovery );

		$manifest_controller = new ManifestController(
			$manifest
		);

		$manifest_controller->register_hooks();

		$llms_controller = new LlmsController(
			$manifest
		);

		$llms_controller->register_hooks();

		$rest_controller = new RestController(
			$manifest
		);

		$rest_controller->register_hooks();

		$ability_provider = new AbilityProvider(
			$manifest
		);

		$ability_provider->register_hooks();

		$advertiser = new Advertiser();

		$advertiser->register_hooks();

		$site_health = new SiteHealth( $manifest, $mcp_status );
		$site_health->register_hooks();
		add_action( 'pressagent_flush_cache', array( Cache::class, 'flush' ) );
		add_action( 'updated_option', array( $this, 'maybe_flush_cache' ), 10, 3 );
		add_action( 'activated_plugin', array( Cache::class, 'flush' ) );
		add_action( 'deactivated_plugin', array( Cache::class, 'flush' ) );
		add_action( 'switch_theme', array( Cache::class, 'flush' ) );

		if ( is_admin() ) {
			$settings_page = new SettingsPage( $manifest, $mcp_status );
			$settings_page->register_hooks();
		}

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Flush public artifacts after relevant option changes.
	 *
	 * @param string $option    Updated option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     Updated option value.
	 * @return void
	 */
	public function maybe_flush_cache( string $option, $old_value, $value ): void {
		if ( in_array( $option, array( Settings::OPTION, 'blogname', 'blogdescription', 'home', 'siteurl' ), true ) ) {
			Cache::flush();
		}

		if ( Settings::OPTION === $option ) {
			$old_settings = Settings::sanitize( $old_value );
			$new_settings = Settings::sanitize( $value );

			if ( $old_settings['llms_enabled'] !== $new_settings['llms_enabled'] ) {
				delete_option( 'rewrite_rules' );
			}
		}
	}

	/**
	 * Initialize plugin functionality.
	 *
	 * @return void
	 */
	public function init(): void {
		do_action( 'pressagent_init' );
	}

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$discovery = new DiscoveryService();
		$manifest  = new Manifest( $discovery );

		( new ManifestController( $manifest ) )->add_rewrite_rule();
		( new LlmsController( $manifest ) )->add_rewrite_rule();

		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		ManifestController::remove_rewrite_rule();
		LlmsController::remove_rewrite_rule();
		flush_rewrite_rules();
	}
}
