<?php
/**
 * Manifest controller class.
 *
 * @package PressAgent
 */

namespace PressAgent\Discovery;

use PressAgent\Support\Settings;

/**
 * Handles the public agent discovery manifest endpoint.
 */
final class ManifestController {
	/**
	 * Rewrite rule for the manifest endpoint.
	 *
	 * @var string
	 */
	private const REWRITE_RULE = '^\.well-known/wordpress-agent\.json$';

	/**
	 * Query variable used for the manifest endpoint.
	 *
	 * @var string
	 */
	private const QUERY_VAR = 'pressagent_manifest';

	/**
	 * Manifest instance.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Create the controller.
	 *
	 * @param Manifest $manifest Manifest instance.
	 */
	public function __construct( Manifest $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );

		/*
		 * Serve before redirect_canonical (priority 10) so the
		 * extension-like well-known path is not 301-redirected.
		 */
		add_action( 'template_redirect', array( $this, 'serve_manifest' ), 1 );
	}

	/**
	 * Register the public manifest rewrite rule.
	 *
	 * @return void
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule(
			self::REWRITE_RULE,
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Remove the manifest rewrite rule.
	 *
	 * @return void
	 */
	public static function remove_rewrite_rule(): void {
		remove_rewrite_rule( self::REWRITE_RULE );
	}

	/**
	 * Register the manifest query variable.
	 *
	 * @param string[] $query_vars Public query variables.
	 * @return string[]
	 */
	public function add_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	/**
	 * Serve the public agent manifest.
	 *
	 * @return void
	 */
	public function serve_manifest(): void {
		if ( '1' !== get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$manifest = $this->manifest->get();
		$etag     = '"' . hash( 'sha256', wp_json_encode( $manifest ) ) . '"';
		header( 'ETag: ' . $etag );
		header( 'Cache-Control: public, max-age=' . Settings::get_cache_ttl() . ', must-revalidate' );
		header( 'X-Content-Type-Options: nosniff' );

		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) === $etag ) {
			status_header( 304 );
			exit;
		}

		header(
			'Content-Type: application/json; charset=' . get_bloginfo( 'charset' )
		);

		echo wp_json_encode(
			$manifest,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		exit;
	}
}
