<?php
/**
 * Ability provider class.
 *
 * @package PressAgent
 */

namespace PressAgent\Abilities;

use PressAgent\Discovery\Manifest;

/**
 * Registers PressAgent abilities with the WordPress Abilities API.
 */
final class AbilityProvider {

	/**
	 * Manifest instance.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Create the provider.
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
		add_action(
			'wp_abilities_api_init',
			array( $this, 'register_abilities' )
		);
	}

	/**
	 * Register PressAgent abilities.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		$this->register_ability(
			'pressagent/get-manifest',
			__( 'Get PressAgent Manifest', 'pressagent' ),
			__( 'Returns the public PressAgent discovery manifest for this site.', 'pressagent' ),
			array( $this, 'get_manifest' ),
			array(
				'type'                 => 'object',
				'additionalProperties' => true,
			)
		);

		$this->register_ability(
			'pressagent/get-discovery',
			__( 'Get PressAgent Discovery Endpoints', 'pressagent' ),
			__( 'Returns the agent discovery endpoints advertised by this site.', 'pressagent' ),
			array( $this, 'get_discovery' ),
			array(
				'type'                 => 'object',
				'additionalProperties' => true,
			)
		);

		$this->register_ability(
			'pressagent/get-llms',
			__( 'Get PressAgent LLMS Summary', 'pressagent' ),
			__( 'Returns a plain-text summary of this site for AI agents (llms.txt).', 'pressagent' ),
			array( $this, 'get_llms' ),
			array(
				'type' => 'string',
			)
		);

		$this->register_ability(
			'pressagent/get-abilities',
			__( 'Get Site Abilities', 'pressagent' ),
			__( 'Returns the abilities advertised by this site, including core and plugin abilities.', 'pressagent' ),
			array( $this, 'get_abilities' ),
			array(
				'type'                 => 'object',
				'additionalProperties' => true,
			)
		);

		$this->register_ability(
			'pressagent/get-categories',
			__( 'Get Ability Categories', 'pressagent' ),
			__( 'Returns the ability categories advertised by this site.', 'pressagent' ),
			array( $this, 'get_categories' ),
			array(
				'type'                 => 'object',
				'additionalProperties' => true,
			)
		);
	}

	/**
	 * Register a single PressAgent ability.
	 *
	 * @param string               $slug          Ability slug.
	 * @param string               $label         Ability label.
	 * @param string               $description   Ability description.
	 * @param callable             $callback      Execute callback.
	 * @param array<string, mixed> $output_schema JSON Schema for the ability output.
	 * @return void
	 */
	private function register_ability( string $slug, string $label, string $description, callable $callback, array $output_schema ): void {
		wp_register_ability(
			$slug,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'site',
				'output_schema'       => $output_schema,
				'permission_callback' => '__return_true',
				'execute_callback'    => $callback,
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Return the public discovery manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function get_manifest(): array {
		return $this->manifest->get();
	}

	/**
	 * Return the agent discovery endpoints.
	 *
	 * @return array<string, mixed>
	 */
	public function get_discovery(): array {
		return $this->manifest->get_discovery();
	}

	/**
	 * Return the plain-text LLMS summary.
	 *
	 * @return string
	 */
	public function get_llms(): string {
		return $this->manifest->get_llms();
	}

	/**
	 * Return the abilities advertised for this site.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_abilities(): array {
		return $this->manifest->get_abilities();
	}

	/**
	 * Return the ability categories advertised for this site.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_categories(): array {
		return $this->manifest->get_categories();
	}
}
