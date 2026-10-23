<?php
declare(strict_types=1);

namespace Woorise\Embed;

defined('ABSPATH') || exit;

final class CPT {
	private const USER_META_DISMISS = '_woorise_embed_notice';

	public static function init(): void {
		add_action('init', [__CLASS__, 'register']);
		add_action('admin_notices', [__CLASS__, 'maybe_show_embed_notice']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_notice_script']);
		add_action('wp_ajax_woorise_dismiss_embed_notice', [__CLASS__, 'ajax_dismiss_embed_notice']);
	}

	public static function register(): void {
		$args = [
			'labels'             => self::labels(),
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'map_meta_cap'       => true,
			'capability_type'    => 'post',
			'supports'           => ['title'],
			'has_archive'        => false,
			'publicly_queryable' => false,
			'exclude_from_search'=> true,
			'rewrite'            => false,
			'query_var'          => false,
			'menu_icon'          => self::menu_icon(),
		];

		register_post_type('woorise_embed', $args);
	}

	private static function labels(): array {
		return [
			'name'               => __('Woorise Embeds', 'woorise'),
			'singular_name'      => __('Woorise Embed', 'woorise'),
			'menu_name'          => __('Woorise', 'woorise'),
			'name_admin_bar'     => __('Woorise Embed', 'woorise'),
			'add_new'            => __('Add New', 'woorise'),
			'add_new_item'       => __('Add New Embed', 'woorise'),
			'new_item'           => __('New Embed', 'woorise'),
			'edit_item'          => __('Edit Embed', 'woorise'),
			'view_item'          => __('View Embed', 'woorise'),
			'all_items'          => __('All Embeds', 'woorise'),
			'search_items'       => __('Search Embeds', 'woorise'),
			'not_found'          => __('No embeds found.', 'woorise'),
			'not_found_in_trash' => __('No embeds found in Trash.', 'woorise'),
		];
	}

	private static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="#a7aaad" d="M475.6 0c-42.3 0-55.7 26.4-57.4 38.5l.1 277.5c0 32.9-15.1 66.4-64.3 66.4-33.4 0-63.2-25.2-63.2-66.4V81.5s-11.5-4-24.4-3.9c-41.9 0-55.5 25.9-57.3 38.1v200c0 29.5-19.4 66.7-65.3 66.7-36.3 0-62.2-28-62.2-66.4l.1-156.5s-11.3-3.9-24.2-3.8C12.2 155.7 0 191.1 0 200.8V316c0 39.7 14.2 73.6 42.5 101.8C70.9 446 104.7 460 144.2 460c39.4 0 89.9-24.4 105.8-42.9 14.1 17.8 65 42.9 104.4 42.9 39.4 0 73.5-14.2 102.4-43.6C485.6 389 500 355.2 500 316V3.9S488.5 0 475.6 0z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	public static function maybe_show_embed_notice(): void {
		if (! current_user_can('edit_posts')) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || 'woorise_embed' !== $screen->post_type || ! in_array($screen->base, ['post', 'post-new', 'edit'], true)) {
			return;
		}
		if (get_user_meta(get_current_user_id(), self::USER_META_DISMISS, true)) {
			return;
		}

		$help_url = 'https://woorise.com/help/wordpress';

		$message = sprintf(
			__('Woorise Embeds are ideal for site-wide or multi-page placements. For a specific page or post, use the Woorise block or the shortcode. %s', 'woorise'),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url($help_url),
				esc_html__('Learn more', 'woorise')
			)
		);

		echo '<div class="notice notice-info is-dismissible woorise-embed-tip"><p>' . wp_kses($message, ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . '</p></div>';
	}

	public static function enqueue_notice_script(string $hook): void {
		if (! in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || 'woorise_embed' !== $screen->post_type) {
			return;
		}

		wp_register_script('woorise-embed-notice', '', [], \WOORISE_VER, true);
		wp_enqueue_script('woorise-embed-notice');

		wp_add_inline_script(
			'woorise-embed-notice',
			sprintf('const WooriseEmbedNotice = { nonce: "%s" };', esc_js(wp_create_nonce('woorise_embed_notice'))),
			'before'
		);

		wp_add_inline_script(
			'woorise-embed-notice',
			"(function(){
				document.addEventListener('click',function(e){
					var close=e.target.closest('.woorise-embed-tip .notice-dismiss');
					if(!close) return;
					var body=new URLSearchParams();
					body.append('action','woorise_dismiss_embed_notice');
					body.append('nonce',WooriseEmbedNotice.nonce);
					fetch(ajaxurl,{
						method:'POST',
						credentials:'same-origin',
						headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
						body:body.toString()
					});
				});
			})();"
		);
	}

	public static function ajax_dismiss_embed_notice(): void {
		check_ajax_referer('woorise_embed_notice', 'nonce');
		update_user_meta(get_current_user_id(), self::USER_META_DISMISS, 1);
		wp_send_json_success();
	}
}
