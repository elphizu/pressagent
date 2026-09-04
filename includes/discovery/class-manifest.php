<?php
/**
 * Manifest class.
 *
 * @package PressAgent
 */

namespace PressAgent\Discovery;

use PressAgent\Support\Cache;

/**
 * Builds the PressAgent discovery manifest.
 */
final class Manifest {

	/**
	 * Manifest schema version.
	 *
	 * @var string
	 */
	public const VERSION = '1.0';

	/**
	 * Discovery service instance.
	 *
	 * @var DiscoveryService
	 */
	private DiscoveryService $discovery;

	/**
	 * Create the manifest builder.
	 *
	 * @param DiscoveryService $discovery Discovery service instance.
	 */
	public function __construct( DiscoveryService $discovery ) {
		$this->discovery = $discovery;
	}

	/**
	 * Get the public discovery manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$discovery = $this->get_discovery();
		$context   = self::discovery_context( $discovery );
		$cached    = Cache::get_manifest( $context );
		if ( false !== $cached ) {
			return $cached;
		}
		$abilities   = $this->get_abilities();
		$credentials = $this->get_authentication( $discovery );

		$manifest = array(
			'version'        => self::VERSION,
			'site'           => self::site_info(),
			'discovery'      => $discovery,
			'authentication' => $credentials,
			'abilities'      => $abilities,
			'categories'     => $this->get_categories_for_abilities( $abilities ),
		);

		/**
		 * Filters the PressAgent discovery manifest.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $manifest Discovery manifest.
		 */
		$filtered = apply_filters( 'pressagent_manifest', $manifest );

		$manifest = $this->normalize_manifest( is_array( $filtered ) ? $filtered : $manifest, $manifest );
		Cache::set_manifest( $manifest, $context );
		return $manifest;
	}

	/**
	 * Get the normalized public discovery endpoints.
	 *
	 * @return array<string, string> Discovery endpoints keyed by protocol.
	 */
	public function get_discovery(): array {
		return $this->discovery->get();
	}

	/**
	 * Get the plain-text LLMS summary.
	 *
	 * @return string
	 */
	public function get_llms(): string {
		$discovery = $this->discovery->get();
		$context   = self::discovery_context( $discovery );
		$cached    = Cache::get_llms( $context );
		if ( false !== $cached ) {
			return $cached;
		}
		$description = self::single_line_text( get_bloginfo( 'description' ) );
		$lines       = array( '# ' . self::single_line_text( get_bloginfo( 'name' ) ) );

		if ( '' !== $description ) {
			$lines[] = '';
			$lines[] = '> ' . $description;
		}

		$lines[] = '';
		$lines[] = '## ' . __( 'Agent discovery', 'pressagent' );
		$lines[] = '';
		$lines[] = self::markdown_link(
			__( 'Website', 'pressagent' ),
			esc_url_raw( home_url( '/' ) ),
			__( 'Canonical public site.', 'pressagent' )
		);
		$lines[] = self::markdown_link(
			__( 'Agent manifest', 'pressagent' ),
			esc_url_raw( home_url( '/.well-known/wordpress-agent.json' ) ),
			__( 'Machine-readable WordPress agent discovery metadata.', 'pressagent' )
		);

		if ( isset( $discovery['rest'] ) ) {
			$lines[] = self::markdown_link(
				__( 'WordPress REST API', 'pressagent' ),
				$discovery['rest'],
				__( 'WordPress REST API index.', 'pressagent' )
			);
		}

		if ( isset( $discovery['pressagent'] ) ) {
			$lines[] = self::markdown_link(
				__( 'PressAgent REST manifest', 'pressagent' ),
				$discovery['pressagent'],
				__( 'REST representation of the agent manifest.', 'pressagent' )
			);
		}

		if ( isset( $discovery['abilities'] ) ) {
			$lines[] = self::markdown_link(
				__( 'Abilities API', 'pressagent' ),
				$discovery['abilities'],
				__( 'Requires WordPress authentication and the read capability.', 'pressagent' )
			);
		}

		if ( isset( $discovery['mcp'] ) ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Optional', 'pressagent' );
			$lines[] = '';
			$lines[] = self::markdown_link(
				__( 'MCP endpoint', 'pressagent' ),
				$discovery['mcp'],
				__( 'Model Context Protocol endpoint contributed by an adapter.', 'pressagent' )
			);
		}

		$llms = implode( "\n", $lines ) . "\n";

		/**
		 * Filters the PressAgent LLMS summary.
		 *
		 * @since 0.1.0
		 *
		 * @param string $llms LLMS summary.
		 */
		$filtered = apply_filters( 'pressagent_llms', $llms );

		$llms = is_string( $filtered ) ? $filtered : $llms;
		Cache::set_llms( $llms, $context );
		return $llms;
	}

	/**
	 * Create a stable cache context for the current discovery map.
	 *
	 * @param array<string, string> $discovery Discovery endpoints.
	 * @return string Discovery context hash.
	 */
	private static function discovery_context( array $discovery ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'discovery' => $discovery,
					'locale'    => determine_locale(),
				)
			)
		);
	}

	/**
	 * Preserve required manifest fields while allowing optional extensions.
	 *
	 * @param array<string, mixed> $candidate Filtered manifest.
	 * @param array<string, mixed> $defaults  Required defaults.
	 * @return array<string, mixed>
	 */
	private function normalize_manifest( array $candidate, array $defaults ): array {
		foreach ( array( 'version', 'site', 'discovery', 'authentication', 'abilities', 'categories' ) as $key ) {
			if ( ! array_key_exists( $key, $candidate ) || gettype( $candidate[ $key ] ) !== gettype( $defaults[ $key ] ) ) {
				$candidate[ $key ] = $defaults[ $key ];
			}
		}
		return $candidate;
	}

	/**
	 * Format one llms.txt file-list item as a Markdown link.
	 *
	 * @param string $label       Link label.
	 * @param string $url         Absolute URL.
	 * @param string $description Link description.
	 * @return string Formatted Markdown list item.
	 */
	private static function markdown_link( string $label, string $url, string $description ): string {
		return sprintf(
			'- [%1$s](%2$s): %3$s',
			self::single_line_text( $label ),
			$url,
			self::single_line_text( $description )
		);
	}

	/**
	 * Get authentication requirements for advertised discovery endpoints.
	 *
	 * Authentication identifies a WordPress user. Individual ability permission
	 * callbacks remain responsible for authorizing every execution request.
	 *
	 * @param array<string, string> $discovery Advertised discovery endpoints.
	 * @return array<string, array<string, mixed>> Authentication metadata keyed by endpoint.
	 */
	private function get_authentication( array $discovery ): array {
		$authentication = array();

		if ( isset( $discovery['abilities'] ) ) {
			$methods = array(
				'cookie' => array(
					'nonce_action' => 'wp_rest',
				),
			);

			if ( wp_is_application_passwords_available() ) {
				$methods['application-passwords'] = array(
					'transport'      => 'basic',
					'https_required' => true,
					'authorization'  => admin_url( 'authorize-application.php' ),
				);
			}

			$authentication['abilities'] = array(
				'required'   => true,
				'capability' => 'read',
				'methods'    => $methods,
			);
		}

		if (
			isset( $discovery['mcp'] )
			&& rest_url( 'mcp/mcp-adapter-default-server' ) === $discovery['mcp']
		) {
			$methods = array(
				'cookie' => array(
					'nonce_action' => 'wp_rest',
				),
			);

			if ( wp_is_application_passwords_available() ) {
				$methods['application-passwords'] = array(
					'transport'      => 'basic',
					'https_required' => true,
					'authorization'  => admin_url( 'authorize-application.php' ),
				);
			}

			$authentication['mcp'] = array(
				'required'   => true,
				'capability' => 'read',
				'methods'    => $methods,
			);
		}

		/**
		 * Filters authentication metadata for advertised discovery endpoints.
		 *
		 * Entries should use the same keys as the pressagent_discovery endpoint
		 * map. Integrations that add authenticated endpoints should also add the
		 * corresponding authentication requirements here.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array<string, mixed>> $authentication Authentication metadata.
		 * @param array<string, string>               $discovery      Advertised discovery endpoints.
		 */
		$filtered = apply_filters( 'pressagent_authentication', $authentication, $discovery );

		return is_array( $filtered ) ? $filtered : $authentication;
	}

	/**
	 * Get the abilities advertised for this site.
	 *
	 * Lists descriptive metadata only. Ability execution remains gated by
	 * each ability's own permission callback.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = array();

		foreach ( wp_get_abilities() as $name => $ability ) {
			if ( true !== $ability->get_meta_item( 'public' ) ) {
				continue;
			}

			$abilities[ $name ] = array(
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'category'    => $ability->get_category(),
			);
		}

		/**
		 * Filters the abilities advertised in the PressAgent discovery manifest.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array<string, string>> $abilities Advertised abilities keyed by slug.
		 */
		$filtered = apply_filters( 'pressagent_manifest_abilities', $abilities );

		return $this->normalize_abilities( is_array( $filtered ) ? $filtered : $abilities );
	}

	/**
	 * Normalize registered and synthetic public ability metadata.
	 *
	 * @param array<mixed> $abilities Raw ability projection.
	 * @return array<string, array<string, string>>
	 */
	private function normalize_abilities( array $abilities ): array {
		$normalized = array();
		foreach ( $abilities as $name => $ability ) {
			if ( ! is_string( $name ) || ! is_array( $ability ) || ! isset( $ability['label'], $ability['description'], $ability['category'] ) ) {
				continue;
			}
			if ( ! is_string( $ability['label'] ) || ! is_string( $ability['description'] ) || ! is_string( $ability['category'] ) ) {
				continue;
			}
			$normalized[ $name ] = array(
				'label'       => $ability['label'],
				'description' => $ability['description'],
				'category'    => $ability['category'],
			);
		}
		return $normalized;
	}

	/**
	 * Get the ability categories advertised for this site.
	 *
	 * Categories are the legend for the category slugs referenced by each
	 * advertised ability.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_categories(): array {
		return $this->get_categories_for_abilities( $this->get_abilities() );
	}

	/**
	 * Get categories referenced by a projected ability collection.
	 *
	 * @param array<string, array<string, string>> $abilities Projected abilities.
	 * @return array<string, array<string, string>> Projected ability categories.
	 */
	private function get_categories_for_abilities( array $abilities ): array {
		if ( ! function_exists( 'wp_get_ability_categories' ) ) {
			return array();
		}

		$categories     = array();
		$category_slugs = array_unique( wp_list_pluck( $abilities, 'category' ) );

		foreach ( wp_get_ability_categories() as $slug => $category ) {
			if ( ! in_array( $slug, $category_slugs, true ) ) {
				continue;
			}

			$categories[ $slug ] = array(
				'label'       => $category->get_label(),
				'description' => $category->get_description(),
			);
		}

		/**
		 * Filters the ability categories advertised in the PressAgent discovery manifest.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array<string, string>> $categories Advertised categories keyed by slug.
		 */
		$filtered = apply_filters( 'pressagent_manifest_categories', $categories );

		return is_array( $filtered ) ? $filtered : $categories;
	}

	/**
	 * Normalize a value for use on a single line of plain-text output.
	 *
	 * Not suitable for multi-line content.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function single_line_text( string $value ): string {
		return trim( wp_strip_all_tags( $value, true ) );
	}

	/**
	 * Get public site information.
	 *
	 * @return array<string, string>
	 */
	private static function site_info(): array {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'description' => get_bloginfo( 'description' ),
			'wordpress'   => get_bloginfo( 'version' ),
		);
	}
}
