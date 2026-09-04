<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Pressagent
 */

$pressagent_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $pressagent_tests_dir ) {
	$pressagent_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$pressagent_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $pressagent_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $pressagent_phpunit_polyfills_path ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Required by the WordPress test suite.
}

if ( ! file_exists( "{$pressagent_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$pressagent_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$pressagent_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function pressagent_manually_load_plugin() {
	require dirname( __DIR__ ) . '/pressagent.php';
}

tests_add_filter( 'muplugins_loaded', 'pressagent_manually_load_plugin' );

// Start up the WP testing environment.
require "{$pressagent_tests_dir}/includes/bootstrap.php";
