<?php
/**
 * Site Health integration class.
 *
 * @package PressAgent
 */

namespace PressAgent\Admin;

use PressAgent\Discovery\LlmsController;
use PressAgent\Discovery\Manifest;
use PressAgent\Discovery\McpStatus;

/**
 * Adds bounded PressAgent checks and debug information to Site Health.
 */
final class SiteHealth {

	/**
	 * Manifest instance.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * MCP status service.
	 *
	 * @var McpStatus
	 */
	private McpStatus $mcp_status;

	/**
	 * Create the Site Health integration.
	 *
	 * @param Manifest  $manifest   Manifest instance.
	 * @param McpStatus $mcp_status MCP status service.
	 */
	public function __construct( Manifest $manifest, McpStatus $mcp_status ) {
		$this->manifest   = $manifest;
		$this->mcp_status = $mcp_status;
	}

	/**
	 * Register Site Health hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
	}

	/**
	 * Register direct Site Health tests.
	 *
	 * @param array<string, mixed> $tests Registered Site Health tests.
	 * @return array<string, mixed> Filtered Site Health tests.
	 */
	public function add_tests( array $tests ): array {
		$tests['direct']['pressagent_discovery'] = array(
			'label' => __( 'PressAgent discovery', 'pressagent' ),
			'test'  => array( $this, 'test_discovery' ),
		);

		$tests['direct']['pressagent_authentication'] = array(
			'label' => __( 'PressAgent authentication transport', 'pressagent' ),
			'test'  => array( $this, 'test_authentication' ),
		);

		$tests['direct']['pressagent_mcp_integration'] = array(
			'label' => __( 'PressAgent MCP integration', 'pressagent' ),
			'test'  => array( $this, 'test_mcp_integration' ),
		);

		return $tests;
	}

	/**
	 * Test whether optional MCP integration is available.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_mcp_integration(): array {
		$status    = $this->mcp_status->get();
		$available = in_array( $status['state'], array( 'available', 'custom' ), true );

		if ( $available ) {
			$description = __( 'An MCP endpoint is advertised and requires the authentication configured by its provider.', 'pressagent' );
			$actions     = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( $status['endpoint'] ),
				esc_html__( 'View MCP endpoint', 'pressagent' )
			);
		} elseif ( 'inactive' === $status['state'] ) {
			$description = __( 'The WordPress MCP Adapter is installed but inactive or could not initialize. Activate it and confirm that its requirements are met.', 'pressagent' );
			$actions     = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'plugins.php' ) ),
				esc_html__( 'Manage plugins', 'pressagent' )
			);
		} elseif ( 'default-disabled' === $status['state'] ) {
			$description = __( 'The WordPress MCP Adapter is active, but no endpoint is advertised. Enable its default server or provide a custom endpoint through pressagent_discovery.', 'pressagent' );
			$actions     = '';
		} else {
			$description = __( 'Install and activate the optional WordPress MCP Adapter to expose eligible abilities to MCP clients. Other PressAgent discovery features are unaffected.', 'pressagent' );
			$actions     = sprintf(
				'<p><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
				esc_url( 'https://github.com/WordPress/mcp-adapter/releases/latest' ),
				esc_html__( 'View MCP Adapter installation options', 'pressagent' )
			);
		}

		return $this->result(
			$available ? __( 'PressAgent MCP integration is available', 'pressagent' ) : __( 'PressAgent MCP integration is unavailable', 'pressagent' ),
			$available ? 'good' : 'recommended',
			$description,
			'pressagent_mcp_integration',
			$actions
		);
	}

	/**
	 * Test whether required discovery resources can be generated.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_discovery(): array {
		$discovery = $this->manifest->get_discovery();
		$manifest  = $this->manifest->get();
		$healthy   = isset( $discovery['rest'], $discovery['pressagent'] )
			&& isset( $manifest['version'], $manifest['site'], $manifest['authentication'], $manifest['abilities'], $manifest['categories'] )
			&& is_array( $manifest['abilities'] )
			&& is_array( $manifest['categories'] );

		if ( isset( $discovery['abilities'] ) ) {
			$healthy = $healthy
				&& isset( $manifest['authentication']['abilities']['required'] )
				&& true === $manifest['authentication']['abilities']['required'];
		}
		$status = $healthy ? 'good' : 'critical';

		$description = $healthy
			? __( 'The manifest, REST discovery, and public ability projection can be generated.', 'pressagent' )
			: __( 'One or more required REST discovery endpoints are missing after filtering.', 'pressagent' );

		if ( ! isset( $discovery['mcp'] ) ) {
			$description .= ' ' . __( 'No optional MCP endpoint is currently advertised.', 'pressagent' );
		}

		if ( ! LlmsController::is_enabled() ) {
			$description .= ' ' . __( 'The llms.txt endpoint is disabled by a filter.', 'pressagent' );
		}

		return $this->result(
			$healthy ? __( 'PressAgent discovery is available', 'pressagent' ) : __( 'PressAgent discovery is incomplete', 'pressagent' ),
			$status,
			$description,
			'pressagent_discovery'
		);
	}

	/**
	 * Test whether the recommended WordPress authentication transport is available.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_authentication(): array {
		$https                 = is_ssl();
		$application_passwords = wp_is_application_passwords_available();
		$healthy               = $https && $application_passwords;

		return $this->result(
			$healthy ? __( 'Secure agent authentication is available', 'pressagent' ) : __( 'Secure agent authentication needs attention', 'pressagent' ),
			$healthy ? 'good' : 'recommended',
			$healthy
				? __( 'WordPress Application Passwords are available over HTTPS.', 'pressagent' )
				: __( 'Use HTTPS and enable WordPress Application Passwords before authenticated external agents connect.', 'pressagent' ),
			'pressagent_authentication'
		);
	}

	/**
	 * Add PressAgent values to Site Health debug information.
	 *
	 * @param array<string, mixed> $information Debug information sections.
	 * @return array<string, mixed> Filtered debug information sections.
	 */
	public function add_debug_information( array $information ): array {
		$discovery = $this->manifest->get_discovery();
		$mcp       = $this->mcp_status->get();

		$information['pressagent'] = array(
			'label'  => __( 'PressAgent', 'pressagent' ),
			'fields' => array(
				'manifest_url'          => $this->debug_field( __( 'Manifest URL', 'pressagent' ), home_url( '/.well-known/wordpress-agent.json' ) ),
				'llms_url'              => $this->debug_field( __( 'llms.txt URL', 'pressagent' ), home_url( '/llms.txt' ) ),
				'llms_enabled'          => $this->debug_field( __( 'llms.txt enabled', 'pressagent' ), LlmsController::is_enabled() ? __( 'Yes', 'pressagent' ) : __( 'No', 'pressagent' ) ),
				'public_abilities'      => $this->debug_field( __( 'Public abilities', 'pressagent' ), (string) count( $this->manifest->get_abilities() ) ),
				'mcp_endpoint'          => $this->debug_field( __( 'MCP endpoint', 'pressagent' ), $discovery['mcp'] ?? __( 'Not advertised', 'pressagent' ) ),
				'mcp_status'            => $this->debug_field( __( 'MCP status', 'pressagent' ), $mcp['state'] ),
				'https'                 => $this->debug_field( __( 'HTTPS', 'pressagent' ), is_ssl() ? __( 'Yes', 'pressagent' ) : __( 'No', 'pressagent' ) ),
				'application_passwords' => $this->debug_field( __( 'Application Passwords', 'pressagent' ), wp_is_application_passwords_available() ? __( 'Available', 'pressagent' ) : __( 'Unavailable', 'pressagent' ) ),
			),
		);

		return $information;
	}

	/**
	 * Build a Site Health result.
	 *
	 * @param string $label       Result label.
	 * @param string $status      Result status.
	 * @param string $description Result description.
	 * @param string $test        Test identifier.
	 * @param string $actions Optional result actions HTML.
	 * @return array<string, mixed> Site Health test result.
	 */
	private function result( string $label, string $status, string $description, string $test, string $actions = '' ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'PressAgent', 'pressagent' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', esc_html( $description ) ),
			'actions'     => $actions,
			'test'        => $test,
		);
	}

	/**
	 * Build a Site Health debug field.
	 *
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return array<string, mixed> Site Health debug field.
	 */
	private function debug_field( string $label, string $value ): array {
		return array(
			'label' => $label,
			'value' => $value,
			'debug' => $value,
		);
	}
}
