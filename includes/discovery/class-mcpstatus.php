<?php
/**
 * MCP integration status service.
 *
 * @package PressAgent
 */

namespace PressAgent\Discovery;

/**
 * Determines the administrative status of MCP discovery.
 */
final class McpStatus {

	/**
	 * Official MCP Adapter plugin basename.
	 *
	 * @var string
	 */
	private const PLUGIN = 'mcp-adapter/mcp-adapter.php';

	/**
	 * Discovery service instance.
	 *
	 * @var DiscoveryService
	 */
	private DiscoveryService $discovery;

	/**
	 * Create the status service.
	 *
	 * @param DiscoveryService $discovery Discovery service instance.
	 */
	public function __construct( DiscoveryService $discovery ) {
		$this->discovery = $discovery;
	}

	/**
	 * Get the current MCP integration status.
	 *
	 * @return array{state:string,endpoint:string,source:string} MCP status data.
	 */
	public function get(): array {
		$endpoints        = $this->discovery->get();
		$default_endpoint = rest_url( 'mcp/mcp-adapter-default-server' );

		if ( isset( $endpoints['mcp'] ) ) {
			$status = array(
				'state'    => $default_endpoint === $endpoints['mcp'] ? 'available' : 'custom',
				'endpoint' => $endpoints['mcp'],
				'source'   => $default_endpoint === $endpoints['mcp'] ? 'official-adapter' : 'discovery-filter',
			);
		} elseif ( $this->runtime_available() ) {
			$status = array(
				'state'    => 'default-disabled',
				'endpoint' => '',
				'source'   => 'official-adapter',
			);
		} elseif ( $this->plugin_installed() ) {
			$status = array(
				'state'    => 'inactive',
				'endpoint' => '',
				'source'   => 'official-adapter',
			);
		} else {
			$status = array(
				'state'    => 'not-installed',
				'endpoint' => '',
				'source'   => '',
			);
		}

		/**
		 * Filters the administrative MCP integration status.
		 *
		 * @since 0.1.0
		 *
		 * @param array{state:string,endpoint:string,source:string} $status MCP status data.
		 */
		$filtered = apply_filters( 'pressagent_mcp_status', $status );

		return $this->normalize( is_array( $filtered ) ? $filtered : $status, $status );
	}

	/**
	 * Determine whether the official MCP Adapter runtime initialized.
	 *
	 * @return bool Whether the runtime is available.
	 */
	private function runtime_available(): bool {
		return class_exists( 'WP\\MCP\\Core\\McpAdapter' )
			|| ( defined( 'WP_MCP_VERSION' ) && did_action( 'mcp_adapter_init' ) );
	}

	/**
	 * Determine whether the official MCP Adapter plugin is installed.
	 *
	 * This inspection is used only by administrative diagnostics.
	 *
	 * @return bool Whether the plugin is installed.
	 */
	private function plugin_installed(): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_key_exists( self::PLUGIN, get_plugins() );
	}

	/**
	 * Normalize filtered MCP status data.
	 *
	 * @param array<mixed>                                      $candidate Filtered status data.
	 * @param array{state:string,endpoint:string,source:string} $fallback  Unfiltered status data.
	 * @return array{state:string,endpoint:string,source:string} Normalized status data.
	 */
	private function normalize( array $candidate, array $fallback ): array {
		$states = array( 'not-installed', 'inactive', 'default-disabled', 'available', 'custom' );
		$state  = isset( $candidate['state'] ) && is_string( $candidate['state'] ) && in_array( $candidate['state'], $states, true )
			? $candidate['state']
			: $fallback['state'];
		$url    = isset( $candidate['endpoint'] ) && is_string( $candidate['endpoint'] )
			? esc_url_raw( $candidate['endpoint'], array( 'http', 'https' ) )
			: $fallback['endpoint'];
		$source = isset( $candidate['source'] ) && is_string( $candidate['source'] )
			? sanitize_key( $candidate['source'] )
			: $fallback['source'];

		if ( in_array( $state, array( 'available', 'custom' ), true ) && '' === $url ) {
			return $fallback;
		}

		return array(
			'state'    => $state,
			'endpoint' => $url,
			'source'   => $source,
		);
	}
}
