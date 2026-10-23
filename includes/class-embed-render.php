<?php
declare(strict_types=1);

namespace Woorise\Embed;

defined('ABSPATH') || exit;

final class Render {
	private const SCRIPT_HANDLE_EMBED  = 'woorise-embed';
	private const SCRIPT_HANDLE_IFRAME = 'woorise-iframe';
	private const SCRIPT_SRC_EMBED     = 'https://woorise.com/e.js';

	private const SHORTCODE_TAG = 'woorise';
	private const IFRAME_CLASS  = 'e-woorise';
	private const OEMBED_REGEX  = '#https://(www\.)?woorise\.com/([^/]+)/((?:c/([0-9]+))|([^/]+))#i';

	private static bool $queue_built = false;

	/** @var array<string,array<int,array{id:string}>> */
	private static array $queue = [];

	public static function init(): void {
		add_action('init', [__CLASS__, 'register_oembed']);
		add_action('init', [__CLASS__, 'register_embed_block']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'register_scripts']);
		add_shortcode(self::SHORTCODE_TAG, [__CLASS__, 'shortcode']);

		add_action('wp_head', [__CLASS__, 'output_wp_head']);
		add_action('wp_footer', [__CLASS__, 'output_wp_footer']);
		add_filter('the_content', [__CLASS__, 'filter_the_content'], 9999);
	}

	private static function should_bail_now(): bool {
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return true;
		}
		if (is_feed() || is_robots() || is_trackback()) {
			return true;
		}
		return false;
	}

	private static function ensure_queue(): void {
		if (self::$queue_built || self::should_bail_now()) {
			return;
		}
		self::$queue_built = true;

		$index = Settings::get_index();
		if (empty($index)) {
			return;
		}

		foreach ($index as $item) {
			$embed_id  = isset($item['embed_id']) ? (string) $item['embed_id'] : '';
			$placement = isset($item['placement']) ? (string) $item['placement'] : 'wp_footer';
			$rules     = isset($item['rules']) && is_array($item['rules']) ? $item['rules'] : [];

			if ($embed_id === '') {
				continue;
			}
			if (! Settings::rules_match($rules)) {
				continue;
			}

			self::$queue[$placement][] = ['id' => $embed_id];
		}

		if (! empty(self::$queue) && ! wp_script_is(self::SCRIPT_HANDLE_EMBED, 'enqueued')) {
			wp_enqueue_script(self::SCRIPT_HANDLE_EMBED);
		}
	}

	public static function register_scripts(): void {
		wp_register_script(
			self::SCRIPT_HANDLE_IFRAME,
			plugins_url('assets/public/iframe-resizer.parent.js', \WOORISE_FILE),
			[],
			'5.5.7',
			true
		);

		wp_add_inline_script(
			self::SCRIPT_HANDLE_IFRAME,
			'document.addEventListener("DOMContentLoaded",function(){try{if(window.iFrameResize){iFrameResize({checkOrigin:false,license:"GPLv3"},".' . esc_js(self::IFRAME_CLASS) . '");}}catch(e){}});'
		);

		wp_register_script(self::SCRIPT_HANDLE_EMBED, self::SCRIPT_SRC_EMBED, [], \WOORISE_VER, true);
	}

	public static function register_oembed(): void {
		wp_embed_register_handler('woorise', self::OEMBED_REGEX, [__CLASS__, 'oembed_handler']);
	}

	public static function oembed_handler($matches, $attr, $url, $rawattr): string {
		$host = (string) wp_parse_url((string) $url, PHP_URL_HOST);
		if ('woorise.com' !== $host && 'www.woorise.com' !== $host) {
			return '';
		}
		return self::get_embed_by_url((string) $url);
	}

	public static function shortcode($atts): string {
		$atts = shortcode_atts(
			[
				'url' => '',
				'id'  => '',
			],
			$atts,
			self::SHORTCODE_TAG
		);

		$id  = trim(sanitize_text_field((string) $atts['id']));
		$url = trim((string) $atts['url']);
		if ($url !== '') {
			$url = esc_url_raw($url) ?: '';
		}

		return ($id !== '')
			? self::render_embed($id)
			: ($url !== '' ? self::get_embed_by_url($url) : '');
	}

	public static function render_embed_block($atts): string {
		$wrapper_attributes = get_block_wrapper_attributes();
		$embed_id = trim(sanitize_text_field((string) ($atts['embed_id'] ?? '')));

		if ($embed_id === '' && empty($atts['url'])) {
			return '';
		}

		$html = ($embed_id !== '')
			? self::render_embed($embed_id)
			: self::get_embed_by_url((string) $atts['url']);

		return sprintf('<div %1$s>%2$s</div>', $wrapper_attributes, $html);
	}

	public static function register_embed_block(): void {
		$block_dir = \WOORISE_PATH . 'src';

		register_block_type_from_metadata(
			$block_dir,
			[
				'render_callback' => [__CLASS__, 'render_embed_block'],
			]
		);
	}

	public static function filter_the_content(string $content): string {
		self::ensure_queue();
		$before = self::$queue['content_top']    ?? [];
		$after  = self::$queue['content_bottom'] ?? [];
		if (empty($before) && empty($after)) {
			return $content;
		}
		return self::render_embeds($before) . $content . self::render_embeds($after);
	}

	public static function output_wp_head(): void {
		self::ensure_queue();
		self::output_for_placement('wp_head');
	}

	public static function output_wp_footer(): void {
		self::ensure_queue();
		self::output_for_placement('wp_footer');
	}

	private static function output_for_placement(string $placement): void {
		$items = self::$queue[$placement] ?? [];
		if (empty($items)) {
			return;
		}
		echo self::render_embeds($items, $placement);
	}

	private static function render_embeds(array $items, string $placement = ''): string {
		$out = '';
		foreach ($items as $item) {
			$id = isset($item['id']) ? (string) $item['id'] : '';
			if ($id !== '') {
				$out .= self::render_embed($id, $placement);
			}
		}
		return $out;
	}

	public static function build_attributes(string $embed_id, string $placement = ''): array {
		$attrs = ['data-wr-embed' => $embed_id];

		if (in_array($placement, ['wp_footer', 'content_bottom'], true)) {
			$attrs['data-wr-lazy'] = '';
		}

		return (array) apply_filters('woorise/embed/attributes', $attrs, $embed_id);
	}

	public static function render_embed(string $embed_id, string $placement = ''): string {
		$embed_id = trim(sanitize_text_field($embed_id));
		if ($embed_id === '') {
			return '';
		}
		if (! wp_script_is(self::SCRIPT_HANDLE_EMBED, 'enqueued')) {
			wp_enqueue_script(self::SCRIPT_HANDLE_EMBED);
		}
		$attrs = self::build_attributes($embed_id, $placement);
		return sprintf('<div %s></div>', self::html_attributes($attrs));
	}

	public static function get_embed_by_url(string $url): string {
		$url = esc_url_raw($url) ?: '';
		if ($url === '') {
			return '';
		}

		$current_url   = self::current_request_url();
		$args          = [];
		$current_query = wp_parse_url($current_url, PHP_URL_QUERY);

		if ($current_query) {
			parse_str($current_query, $current_pieces);
			if (is_array($current_pieces)) {
				unset($current_pieces['p'], $current_pieces['page_id'], $current_pieces['_wpnonce']);
				foreach (array_keys($current_pieces) as $k) {
					if (str_starts_with((string) $k, 'utm_') || in_array($k, ['gclid', 'fbclid'], true)) {
						unset($current_pieces[$k]);
					}
				}
				$args = array_merge($args, $current_pieces);
			}
		}

		$args['woorise-source']   = $current_url;
		$args['woorise-embed-id'] = (string) round(microtime(true) * 1000);

		$src = add_query_arg($args, $url);

		if (! wp_script_is(self::SCRIPT_HANDLE_IFRAME, 'enqueued')) {
			wp_enqueue_script(self::SCRIPT_HANDLE_IFRAME);
		}

		return sprintf(
			'<iframe class="%1$s" src="%2$s" style="border:none;width:1px;min-width:100%%;" scrolling="no"></iframe>',
			esc_attr(self::IFRAME_CLASS),
			esc_url($src)
		);
	}

	private static function current_request_url(): string {
		return esc_url_raw(add_query_arg(null, null, home_url(add_query_arg([]))));
	}

	private static function html_attributes(array $attrs): string {
		$out = [];
		foreach ($attrs as $name => $value) {
			if (! is_string($name) || ! preg_match('/^(data|aria)-[A-Za-z0-9:._-]+$/', $name)) {
				continue;
			}
			if ($value === '') {
				$out[] = esc_attr($name);
			} else {
				$out[] = sprintf('%1$s="%2$s"', esc_attr($name), esc_attr((string) $value));
			}
		}
		return implode(' ', $out);
	}
}
