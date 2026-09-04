<?php
/**
 * Agent discovery advertiser class.
 *
 * @package PressAgent
 */

namespace PressAgent\Discovery;

/**
 * Advertises agent discovery resources to clients and crawlers.
 */
final class Advertiser {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'send_headers', array( $this, 'send_discovery_headers' ) );
		add_action( 'wp_head', array( $this, 'print_discovery_links' ) );
	}

	/**
	 * Send agent discovery HTTP headers.
	 *
	 * @return void
	 */
	public function send_discovery_headers(): void {
		if ( is_admin() ) {
			return;
		}

		header(
			sprintf(
				'X-PressAgent-Manifest: %s',
				home_url( '/.well-known/wordpress-agent.json' )
			)
		);

		if ( LlmsController::is_enabled() ) {
			$llms_url = esc_url_raw( home_url( '/llms.txt' ) );

			header( 'X-PressAgent-LLMS: ' . $llms_url );
			header(
				sprintf(
					'Link: <%s>; rel="describedby"; type="text/markdown"',
					$llms_url
				),
				false
			);
		}
	}

	/**
	 * Print agent discovery links in the document head.
	 *
	 * @return void
	 */
	public function print_discovery_links(): void {
		printf(
			'<link rel="alternate" type="application/json" href="%s" />' . "\n",
			esc_url( home_url( '/.well-known/wordpress-agent.json' ) )
		);

		if ( LlmsController::is_enabled() ) {
			printf(
				'<link rel="describedby" type="text/markdown" href="%s" />' . "\n",
				esc_url( home_url( '/llms.txt' ) )
			);
		}
	}
}
