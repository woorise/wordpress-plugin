<?php
/**
 * Plugin Name: Woorise
 * Plugin URI: https://woorise.com/
 * Description: Create beautiful landing pages, forms, surveys, quizzes, viral giveaways & popups, then embed them anywhere.
 * Version: 1.5.0
 * Requires at least: 5.6
 * Requires PHP: 8.0
 * Author: Woorise
 * Author URI: https://woorise.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woorise
 *
 * @package woorise
 */

defined( 'ABSPATH' ) || exit;

define( 'WOORISE_FILE', __FILE__ );
define( 'WOORISE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOORISE_VER', '1.5.0' );

require_once WOORISE_PATH . 'includes/class-embed-cpt.php';
require_once WOORISE_PATH . 'includes/class-embed-settings.php';
require_once WOORISE_PATH . 'includes/class-embed-render.php';

add_action( 'plugins_loaded', static function (): void {
	\Woorise\Embed\CPT::init();
	\Woorise\Embed\Settings::init();
	\Woorise\Embed\Render::init();
} );

register_activation_hook( __FILE__, static function (): void {
	\Woorise\Embed\CPT::register();
	flush_rewrite_rules( false );
} );

register_deactivation_hook( __FILE__, static function (): void {
	flush_rewrite_rules( false );
} );
