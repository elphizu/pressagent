<?php
/**
 * Plugin autoloader.
 *
 * Maps PressAgent classes to their files under includes/ following the
 * WordPress naming convention, e.g.:
 * PressAgent\Plugin -> includes/class-plugin.php
 * PressAgent\Discovery\Manifest -> includes/discovery/class-manifest.php
 * PressAgent\Abilities\AbilityProvider -> includes/abilities/class-abilityprovider.php
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoload PressAgent classes.
 *
 * @param string $class_name Fully-qualified class name.
 * @return void
 */
function pressagent_autoload( $class_name ) {
	$prefix = 'PressAgent\\';

	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$parts    = explode( '\\', $relative );
	$name     = strtolower( array_pop( $parts ) );

	$path = PRESSAGENT_DIR . 'includes/';

	if ( ! empty( $parts ) ) {
		$path .= strtolower( implode( '/', $parts ) ) . '/';
	}

	$file = $path . 'class-' . $name . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

spl_autoload_register( 'pressagent_autoload' );
