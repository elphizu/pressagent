<?php
/**
 * REST API controller for the discovery manifest.
 *
 * @package PressAgent
 */

namespace PressAgent\Discovery;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use PressAgent\Support\Settings;

/**
 * Exposes PressAgent discovery data through the WordPress REST API.
 */
final class RestController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'pressagent/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/manifest',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_manifest' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( $this, 'get_schema' ),
			)
		);
	}

	/**
	 * Return the public discovery manifest.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_manifest( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->manifest->get();
		$etag = '"' . hash( 'sha256', wp_json_encode( $data ) ) . '"';

		if ( $etag === $request->get_header( 'if-none-match' ) ) {
			$response = new WP_REST_Response( null, 304 );
		} else {
			$response = rest_ensure_response( $data );
		}

		$response->header( 'ETag', $etag );
		$response->header( 'Cache-Control', 'public, max-age=' . Settings::get_cache_ttl() . ', must-revalidate' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	/**
	 * Get the public manifest schema.
	 *
	 * @return array<string, mixed> REST response schema.
	 */
	public function get_schema(): array {
		$schema = json_decode(
			(string) file_get_contents( PRESSAGENT_DIR . 'docs/manifest-schema.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a bundled local schema.
			true
		);

		return is_array( $schema ) ? $schema : array();
	}
}
