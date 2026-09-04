<?php
/**
 * Discovery service class.
 *
 * @package PressAgent
 */

namespace PressAgent\Discovery;

/**
 * Builds and normalizes the agent discovery endpoint map.
 */
final class DiscoveryService {

	/**
	 * Get the public discovery endpoints available for this site.
	 *
	 * Other plugins can add optional integrations, such as an MCP endpoint,
	 * through the pressagent_discovery filter. Invalid keys, non-string values,
	 * relative URLs, and non-HTTP(S) URLs are omitted from the result.
	 *
	 * @return array<string, string> Discovery endpoints keyed by protocol.
	 */
	public function get(): array {
		$discovery = array(
			'rest'       => rest_url(),
			'pressagent' => rest_url( 'pressagent/v1/manifest' ),
		);

		if ( function_exists( 'wp_get_abilities' ) ) {
			$discovery['abilities'] = rest_url( 'wp-abilities/v1/abilities' );
		}

		$mcp_adapter_available = class_exists( 'WP\\MCP\\Core\\McpAdapter' )
			|| ( defined( 'WP_MCP_VERSION' ) && did_action( 'mcp_adapter_init' ) );

		if ( $mcp_adapter_available ) {
			// This is the official MCP Adapter filter, not a PressAgent hook.
			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$default_server_enabled = (bool) apply_filters(
				'mcp_adapter_create_default_server',
				true
			);
			// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			if ( $default_server_enabled ) {
				$discovery['mcp'] = rest_url( 'mcp/mcp-adapter-default-server' );
			}
		}

		/**
		 * Filters the endpoints included in PressAgent discovery.
		 *
		 * Integrations can add endpoints, remove endpoints, or replace endpoint
		 * URLs. Values must be absolute HTTP(S) URLs.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, string> $discovery Discovery endpoints keyed by protocol.
		 */
		$filtered = apply_filters( 'pressagent_discovery', $discovery );

		return $this->normalize(
			is_array( $filtered ) ? $filtered : $discovery
		);
	}

	/**
	 * Normalize a discovery endpoint map.
	 *
	 * @param array<mixed> $discovery Raw discovery endpoint map.
	 * @return array<string, string> Normalized discovery endpoint map.
	 */
	private function normalize( array $discovery ): array {
		$normalized = array();

		foreach ( $discovery as $key => $url ) {
			if ( ! is_string( $key ) || ! is_string( $url ) ) {
				continue;
			}

			$key = sanitize_key( $key );
			$url = esc_url_raw( $url, array( 'http', 'https' ) );

			if ( '' === $key || ! $this->is_absolute_http_url( $url ) ) {
				continue;
			}

			$normalized[ $key ] = $url;
		}

		return $normalized;
	}

	/**
	 * Determine whether a URL is an absolute HTTP(S) URL.
	 *
	 * @param string $url URL to inspect.
	 * @return bool Whether the URL is an absolute HTTP(S) URL.
	 */
	private function is_absolute_http_url( string $url ): bool {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $scheme )
			&& in_array( strtolower( $scheme ), array( 'http', 'https' ), true )
			&& is_string( $host )
			&& '' !== $host;
	}
}
