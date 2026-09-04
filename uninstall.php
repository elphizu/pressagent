<?php
/**
 * PressAgent uninstall cleanup.
 *
 * @package PressAgent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pressagent_settings' );
delete_transient( 'pressagent_manifest_v1' );
delete_transient( 'pressagent_llms_v1' );
