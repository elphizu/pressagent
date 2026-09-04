<?php
/**
 * Plugin Name:       PressAgent
 * Plugin URI:        https://github.com/elphizu/pressagent
 * Description:       AI agent discovery and interoperability for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Suryakant Upadhyay
 * Author URI:        https://elphizu.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressagent
 * Domain Path:       /languages
 *
 * @package PressAgent
 */

namespace PressAgent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRESSAGENT_VERSION', '0.1.0' );
define( 'PRESSAGENT_FILE', __FILE__ );
define( 'PRESSAGENT_DIR', plugin_dir_path( __FILE__ ) );

$pressagent_autoload = PRESSAGENT_DIR . 'vendor/autoload.php';

if ( is_readable( $pressagent_autoload ) ) {
	require_once $pressagent_autoload;
}

require_once PRESSAGENT_DIR . 'includes/autoloader.php';

$pressagent = new Plugin();
$pressagent->register_hooks();

register_activation_hook(
	PRESSAGENT_FILE,
	array( Plugin::class, 'activate' )
);

register_deactivation_hook(
	PRESSAGENT_FILE,
	array( Plugin::class, 'deactivate' )
);
