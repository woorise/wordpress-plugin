<?php
/**
 * Plugin Name: Woorise
 * Plugin URI: https://woorise.com/
 * Description: Create beautiful forms, surveys, quizzes & viral giveaways, then embed them anywhere.
 * Version: 1.5.1
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Woorise
 * Author URI: https://woorise.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woorise
 * Domain Path: /languages
 *
 * @package Woorise
 */

defined( 'ABSPATH' ) || exit;

define( 'WOORISE_FILE', __FILE__ );
define( 'WOORISE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOORISE_VER', '1.5.1' );

require_once WOORISE_PATH . 'includes/class-cpt.php';
require_once WOORISE_PATH . 'includes/class-settings.php';
require_once WOORISE_PATH . 'includes/class-render.php';

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'woorise', false, dirname( plugin_basename( WOORISE_FILE ) ) . '/languages' );
	},
	0
);

add_action(
	'plugins_loaded',
	static function (): void {
		\Woorise\Embed\CPT::init();
		\Woorise\Embed\Settings::init();
		\Woorise\Embed\Render::init();
	}
);
