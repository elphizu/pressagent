<?php
/**
 * Settings page class.
 *
 * @package PressAgent
 */

namespace PressAgent\Admin;

use PressAgent\Discovery\LlmsController;
use PressAgent\Discovery\Manifest;
use PressAgent\Discovery\McpStatus;
use PressAgent\Support\Settings;

/**
 * Renders the read-only PressAgent diagnostics screen.
 */
final class SettingsPage {

	/**
	 * Required capability for the settings screen.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'pressagent';

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
	 * Create the settings page.
	 *
	 * @param Manifest  $manifest   Manifest instance.
	 * @param McpStatus $mcp_status MCP status service.
	 */
	public function __construct( Manifest $manifest, McpStatus $mcp_status ) {
		$this->manifest   = $manifest;
		$this->mcp_status = $mcp_status;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PRESSAGENT_FILE ), array( $this, 'add_action_link' ) );
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Settings::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'default'           => Settings::defaults(),
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
			)
		);
	}

	/**
	 * Add the PressAgent settings page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_options_page(
			esc_html__( 'PressAgent', 'pressagent' ),
			esc_html__( 'PressAgent', 'pressagent' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a Settings link to the Plugins screen.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[] Filtered plugin action links.
	 */
	public function add_action_link( array $links ): array {
		$url = add_query_arg(
			'page',
			self::PAGE_SLUG,
			admin_url( 'options-general.php' )
		);

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'pressagent' )
			)
		);

		return $links;
	}

	/**
	 * Render the PressAgent diagnostics screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view PressAgent diagnostics.', 'pressagent' ) );
		}

		$discovery = $this->manifest->get_discovery();
		$abilities = $this->manifest->get_abilities();
		$settings  = Settings::get();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'PressAgent', 'pressagent' ); ?></h1>
			<p><?php echo esc_html__( 'Public AI-agent discovery and WordPress interoperability diagnostics.', 'pressagent' ); ?></p>

			<h2><?php echo esc_html__( 'Discovery resources', 'pressagent' ); ?></h2>
			<table class="widefat striped" role="presentation">
				<tbody>
					<?php $this->render_resource_row( __( 'Agent manifest', 'pressagent' ), home_url( '/.well-known/wordpress-agent.json' ), true ); ?>
					<?php $this->render_resource_row( __( 'llms.txt', 'pressagent' ), home_url( '/llms.txt' ), LlmsController::is_enabled() ); ?>
					<?php foreach ( $discovery as $name => $url ) : ?>
						<?php $this->render_resource_row( ucwords( str_replace( array( '-', '_' ), ' ', $name ) ), $url, true ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Security and authentication', 'pressagent' ); ?></h2>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Public abilities', 'pressagent' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( count( $abilities ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'HTTPS', 'pressagent' ); ?></th>
						<td><?php echo is_ssl() ? esc_html__( 'Enabled', 'pressagent' ) : esc_html__( 'Not detected', 'pressagent' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Application Passwords', 'pressagent' ); ?></th>
						<td><?php echo wp_is_application_passwords_available() ? esc_html__( 'Available', 'pressagent' ) : esc_html__( 'Unavailable', 'pressagent' ); ?></td>
					</tr>
				</tbody>
			</table>

			<p class="description">
				<?php echo esc_html__( 'PressAgent does not provide authentication. External clients should use WordPress Application Passwords over HTTPS. Every sensitive ability must also perform an explicit capability check.', 'pressagent' ); ?>
			</p>

			<?php $this->render_mcp_status(); ?>

			<h2><?php echo esc_html__( 'Settings', 'pressagent' ); ?></h2>
			<form action="options.php" method="post">
				<?php settings_fields( Settings::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'llms.txt', 'pressagent' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[llms_enabled]" value="1" <?php checked( ! empty( $settings['llms_enabled'] ) ); ?> /> <?php echo esc_html__( 'Enable the llms.txt endpoint', 'pressagent' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="pressagent-cache-ttl"><?php echo esc_html__( 'Cache lifetime', 'pressagent' ); ?></label></th>
						<td><input id="pressagent-cache-ttl" type="number" min="60" max="86400" step="60" name="<?php echo esc_attr( Settings::OPTION ); ?>[cache_ttl]" value="<?php echo esc_attr( (string) $settings['cache_ttl'] ); ?>" /> <?php echo esc_html__( 'seconds', 'pressagent' ); ?></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><?php echo esc_html__( 'Public discovery is always enabled. Ability discovery is fixed to abilities explicitly marked public.', 'pressagent' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the optional MCP integration diagnostic.
	 *
	 * @return void
	 */
	private function render_mcp_status(): void {
		$status = $this->mcp_status->get();
		?>
		<h2><?php echo esc_html__( 'MCP integration', 'pressagent' ); ?></h2>
		<div class="notice inline <?php echo esc_attr( in_array( $status['state'], array( 'available', 'custom' ), true ) ? 'notice-success' : 'notice-info' ); ?>">
			<p><strong><?php echo esc_html( $this->mcp_status_label( $status['state'] ) ); ?></strong></p>
			<p><?php echo esc_html( $this->mcp_status_description( $status['state'] ) ); ?></p>
			<?php if ( '' !== $status['endpoint'] ) : ?>
				<p><a href="<?php echo esc_url( $status['endpoint'] ); ?>"><?php echo esc_html( $status['endpoint'] ); ?></a></p>
			<?php elseif ( 'inactive' === $status['state'] ) : ?>
				<p><a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php echo esc_html__( 'Manage plugins', 'pressagent' ); ?></a></p>
			<?php elseif ( 'not-installed' === $status['state'] ) : ?>
				<p><a href="https://github.com/WordPress/mcp-adapter/releases/latest" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'View MCP Adapter installation options', 'pressagent' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the MCP diagnostic label.
	 *
	 * @param string $state MCP status state.
	 * @return string Diagnostic label.
	 */
	private function mcp_status_label( string $state ): string {
		return in_array( $state, array( 'available', 'custom' ), true )
			? __( 'PressAgent MCP integration is available', 'pressagent' )
			: __( 'PressAgent MCP integration is unavailable', 'pressagent' );
	}

	/**
	 * Get the MCP diagnostic description.
	 *
	 * @param string $state MCP status state.
	 * @return string Diagnostic description.
	 */
	private function mcp_status_description( string $state ): string {
		$descriptions = array(
			'available'        => __( 'The official WordPress MCP Adapter is active. The endpoint requires WordPress authentication.', 'pressagent' ),
			'custom'           => __( 'A custom MCP endpoint is advertised through PressAgent discovery. Verify its authentication requirements with the integration provider.', 'pressagent' ),
			'inactive'         => __( 'The WordPress MCP Adapter is installed but inactive or could not initialize. Activate it and confirm that its requirements are met.', 'pressagent' ),
			'default-disabled' => __( 'The WordPress MCP Adapter is active, but no MCP endpoint is advertised. Enable its default server or provide a custom endpoint through pressagent_discovery.', 'pressagent' ),
			'not-installed'    => __( 'Install and activate the optional WordPress MCP Adapter to expose eligible abilities to MCP clients. Other PressAgent discovery features are unaffected.', 'pressagent' ),
		);

		return $descriptions[ $state ] ?? $descriptions['not-installed'];
	}

	/**
	 * Render one discovery resource row.
	 *
	 * @param string $label   Resource label.
	 * @param string $url     Resource URL.
	 * @param bool   $enabled Whether the resource is enabled.
	 * @return void
	 */
	private function render_resource_row( string $label, string $url, bool $enabled ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a></td>
			<td><?php echo $enabled ? esc_html__( 'Advertised', 'pressagent' ) : esc_html__( 'Disabled', 'pressagent' ); ?></td>
		</tr>
		<?php
	}
}
