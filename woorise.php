<?php

/**
 * Plugin Name: Woorise
 * Plugin URI: https://woorise.com/
 * Description: Create viral giveaways & contests, engaging forms, landing pages, surveys, quizzes, collect payments and more.
 * Version: 1.3.4
 * Requires at least: 5.6
 * Author: Woorise
 * Author URI: https://woorise.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woorise
 *
 * @package woorise
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/class-woorise-embed.php' );

function woorise_embed_block_init() {
	register_block_type_from_metadata( __DIR__ . '/src', [
		'render_callback' => 'woorise_embed_block_handler',
	] );
}
add_action( 'init', 'woorise_embed_block_init' );

function woorise_embed_block_handler( $atts ) {

  $woorise_embed = new Woorise_Embed();

  $class = isset( $atts['className'] ) ? 'wp-block-woorise ' . $atts['className'] : 'wp-block-woorise';

  return sprintf(
    '<div class="%1$s">%2$s</div>',
    esc_attr( $class ),
    $woorise_embed->get_embed( $atts['url'] )
  );
}
