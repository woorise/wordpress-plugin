<?php
/**
 * Campaign rendering and automatic frontend placements.
 *
 * @package Woorise
 */

declare( strict_types = 1 );

namespace Woorise\Embed;

defined( 'ABSPATH' ) || exit;

/**
 * Render campaigns and queue automatic placements.
 */
final class Render {
	private const SCRIPT_HANDLE_EMBED  = 'woorise-embed';
	private const SCRIPT_HANDLE_IFRAME = 'woorise-iframe';
	private const SCRIPT_SRC_EMBED     = 'https://woorise.com/e.js';

	private const SHORTCODE_TAG = 'woorise';
	private const IFRAME_CLASS  = 'e-woorise';
	private const OEMBED_REGEX  = '#https://(www\.)?woorise\.com/([^/]+)/((?:c/([0-9]+))|([^/]+))#i';

	/**
	 * Whether the placement queue is ready.
	 *
	 * @var bool
	 */
	private static bool $queue_built = false;

	/**
	 * Embeds grouped by placement.
	 *
	 * @var array<string,array<int,array{id:string}>>
	 */
	private static array $queue = array();

	/**
	 * Posts whose automatic content placements were rendered.
	 *
	 * @var array<int,bool>
	 */
	private static array $rendered_posts = array();

	/**
	 * Register WordPress hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_scripts' ) );
		add_action( 'init', array( __CLASS__, 'register_oembed' ) );
		add_action( 'init', array( __CLASS__, 'register_embed_block' ) );
		add_shortcode( self::SHORTCODE_TAG, array( __CLASS__, 'shortcode' ) );

		add_action( 'wp_body_open', array( __CLASS__, 'output_wp_body_open' ) );
		add_action( 'wp_footer', array( __CLASS__, 'output_wp_footer' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_the_content' ), 9999 );
	}

	/**
	 * Check whether automatic placements should be skipped for this request.
	 *
	 * @return bool Result.
	 */
	private static function should_bail_now(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}
		if ( is_feed() || is_robots() || is_trackback() ) {
			return true;
		}
		return false;
	}

	/**
	 * Build the automatic placement queue once per request.
	 */
	private static function ensure_queue(): void {
		if ( self::$queue_built || self::should_bail_now() ) {
			return;
		}
		self::$queue_built = true;

		$index = Settings::get_index();
		if ( empty( $index ) ) {
			return;
		}

		foreach ( $index as $item ) {
			$embed_id  = isset( $item['embed_id'] ) ? (string) $item['embed_id'] : '';
			$placement = isset( $item['placement'] ) ? (string) $item['placement'] : 'wp_footer';
			// Keep existing head placements working without emitting body markup in the head.
			if ( 'wp_head' === $placement ) {
				$placement = 'wp_body_open';
			}
			$rules = isset( $item['rules'] ) && is_array( $item['rules'] ) ? $item['rules'] : array();

			if ( '' === $embed_id ) {
				continue;
			}
			if ( ! Settings::rules_match( $rules ) ) {
				continue;
			}

			self::$queue[ $placement ][] = array( 'id' => $embed_id );
		}

		if ( ! empty( self::$queue ) && ! wp_script_is( self::SCRIPT_HANDLE_EMBED, 'enqueued' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE_EMBED );
		}
	}

	/**
	 * Register frontend assets before rendering blocks or shortcodes.
	 */
	public static function register_scripts(): void {
		wp_register_script(
			'woorise-iframe-messages',
			plugins_url( 'assets/public/iframe-messages.js', \WOORISE_FILE ),
			array(),
			\WOORISE_VER,
			true
		);
		wp_register_script(
			self::SCRIPT_HANDLE_IFRAME,
			plugins_url( 'assets/public/iframe-resizer.parent.js', \WOORISE_FILE ),
			array(),
			'5.5.9',
			true
		);

		wp_register_script(
			'woorise-iframe-init',
			plugins_url( 'assets/public/iframe-init.js', \WOORISE_FILE ),
			array( 'woorise-iframe-messages', self::SCRIPT_HANDLE_IFRAME ),
			\WOORISE_VER,
			true
		);

		wp_register_script( self::SCRIPT_HANDLE_EMBED, self::SCRIPT_SRC_EMBED, array(), \WOORISE_VER, true );
	}

	/**
	 * Register automatic embedding of Woorise campaign URLs.
	 */
	public static function register_oembed(): void {
		wp_embed_register_handler( 'woorise', self::OEMBED_REGEX, array( __CLASS__, 'oembed_handler' ) );
	}

	/**
	 * Render an automatically embedded Woorise campaign URL.
	 *
	 * @param array  $matches Regular expression matches.
	 * @param array  $attr Embed attributes.
	 * @param string $url Campaign URL.
	 * @return string Result.
	 */
	public static function oembed_handler( $matches, $attr, $url ): string {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( 'woorise.com' !== $host && 'www.woorise.com' !== $host ) {
			return '';
		}
		return self::get_embed_by_url( (string) $url );
	}

	/**
	 * Render a Woorise shortcode using its ID or campaign URL.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string Result.
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'url' => '',
				'id'  => '',
			),
			$atts,
			self::SHORTCODE_TAG
		);

		$id  = trim( sanitize_text_field( (string) $atts['id'] ) );
		$url = trim( (string) $atts['url'] );

		return ( '' !== $id )
			? self::render_embed( $id )
			: ( '' !== $url ? self::get_embed_by_url( $url ) : '' );
	}

	/**
	 * Render the Woorise block using its registered attributes.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string Result.
	 */
	public static function render_embed_block( $atts ): string {
		$wrapper_attributes = get_block_wrapper_attributes();
		$embed_id           = trim( sanitize_text_field( (string) ( $atts['embedId'] ?? '' ) ) );

		if ( '' === $embed_id && empty( $atts['url'] ) ) {
			return '';
		}

		$html = ( '' !== $embed_id )
			? self::render_embed( $embed_id )
			: self::get_embed_by_url( (string) $atts['url'] );

		return sprintf( '<div %1$s>%2$s</div>', $wrapper_attributes, $html );
	}

	/**
	 * Register the block and translated editor assets.
	 */
	public static function register_embed_block(): void {
		$block_dir = \WOORISE_PATH . 'src';

		register_block_type_from_metadata(
			$block_dir,
			array(
				'render_callback' => array( __CLASS__, 'render_embed_block' ),
			)
		);
		wp_set_script_translations( 'woorise-embed-editor-script', 'woorise', \WOORISE_PATH . 'languages' );
		wp_add_inline_script(
			'woorise-embed-editor-script',
			'window.WooriseEmbedSettings = ' . wp_json_encode( array( 'trustedOrigins' => self::get_trusted_origins() ) ) . ';',
			'before'
		);
	}

	/**
	 * Add automatic placements once to each main-loop post.
	 *
	 * @param string $content Current post content.
	 * @return string Result.
	 */
	public static function filter_the_content( string $content ): string {
		global $wp_query;

		$post_id = get_the_ID();
		// Older block themes can render the singular post without entering the loop.
		if ( self::should_bail_now() || ! is_main_query() || ( ! in_the_loop() && ! is_singular() ) || ! $post_id || ! isset( $wp_query->post->ID ) || $post_id !== (int) $wp_query->post->ID || isset( self::$rendered_posts[ $post_id ] ) ) {
			return $content;
		}
		self::ensure_queue();
		$before = self::$queue['content_top'] ?? array();
		$after  = self::$queue['content_bottom'] ?? array();
		if ( empty( $before ) && empty( $after ) ) {
			return $content;
		}
		self::$rendered_posts[ $post_id ] = true;
		return self::render_embeds( $before, 'content_top' ) . $content . self::render_embeds( $after, 'content_bottom' );
	}

	/**
	 * Render beginning-of-page placements inside the body.
	 */
	public static function output_wp_body_open(): void {
		self::ensure_queue();
		self::output_for_placement( 'wp_body_open' );
	}

	/**
	 * Render footer placements and the body-open fallback.
	 */
	public static function output_wp_footer(): void {
		self::ensure_queue();
		if ( ! did_action( 'wp_body_open' ) ) {
			self::output_for_placement( 'wp_body_open' );
		}
		self::output_for_placement( 'wp_footer' );
	}

	/**
	 * Output escaped HTML for an automatic placement.
	 *
	 * @param string $placement Automatic placement name.
	 */
	private static function output_for_placement( string $placement ): void {
		$items = self::$queue[ $placement ] ?? array();
		if ( empty( $items ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_embed() escapes every generated attribute.
		echo self::render_embeds( $items, $placement );
	}

	/**
	 * Build embed HTML for one placement.
	 *
	 * @param array  $items Embeds assigned to the placement.
	 * @param string $placement Automatic placement name.
	 * @return string Result.
	 */
	private static function render_embeds( array $items, string $placement = '' ): string {
		$out = '';
		foreach ( $items as $item ) {
			$id = isset( $item['id'] ) ? (string) $item['id'] : '';
			if ( '' !== $id ) {
				$out .= self::render_embed( $id, $placement );
			}
		}
		return $out;
	}

	/**
	 * Build supported data and ARIA attributes for an embed.
	 *
	 * @param string $embed_id Public Woorise Embed ID.
	 * @param string $placement Automatic placement name.
	 * @return array Result.
	 */
	public static function build_attributes( string $embed_id, string $placement = '' ): array {
		$attrs = array( 'data-wr-embed' => $embed_id );

		if ( in_array( $placement, array( 'wp_footer', 'content_bottom' ), true ) ) {
			$attrs['data-wr-lazy'] = '';
		}

		/**
		 * Filters data and ARIA attributes for an ID-based embed.
		 *
		 * @param array  $attrs    Attribute names and values; escaped when rendered.
		 * @param string $embed_id Embed ID.
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Preserve the existing public filter name.
		return (array) apply_filters( 'woorise/embed/attributes', $attrs, $embed_id );
	}

	/**
	 * Render an ID-based embed container.
	 *
	 * @param string $embed_id Public Woorise Embed ID.
	 * @param string $placement Automatic placement name.
	 * @return string Result.
	 */
	public static function render_embed( string $embed_id, string $placement = '' ): string {
		$embed_id = trim( sanitize_text_field( $embed_id ) );
		if ( '' === $embed_id ) {
			return '';
		}
		if ( ! wp_script_is( self::SCRIPT_HANDLE_EMBED, 'enqueued' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE_EMBED );
		}
		$attrs = self::build_attributes( $embed_id, $placement );
		return sprintf( '<div %s></div>', self::html_attributes( $attrs ) );
	}

	/**
	 * Render a URL-based campaign iframe and forward page parameters.
	 *
	 * @param string $url Campaign URL.
	 * @return string Result.
	 */
	public static function get_embed_by_url( string $url ): string {
		$url = self::sanitize_embed_url( $url );
		if ( '' === $url ) {
			return '';
		}

		$current_url   = self::current_request_url();
		$args          = array();
		$current_query = wp_parse_url( $current_url, PHP_URL_QUERY );

		if ( $current_query ) {
			parse_str( $current_query, $current_pieces );
			if ( is_array( $current_pieces ) ) {
				$current_pieces = self::filter_query_args( $current_pieces );
				unset( $current_pieces['p'], $current_pieces['page_id'] );
				foreach ( array_keys( $current_pieces ) as $k ) {
					if ( str_starts_with( (string) $k, 'utm_' ) || in_array( $k, array( 'gclid', 'fbclid' ), true ) ) {
						unset( $current_pieces[ $k ] );
					}
				}
				$args = array_merge( $args, $current_pieces );
			}
		}

		/**
		 * Filters the customer page parameters forwarded to a URL-based embed.
		 *
		 * @param array  $args Page parameters after sensitive parameters are removed.
		 * @param string $url  Campaign URL, including any custom domain.
		 */
		$args = (array) apply_filters( 'woorise_embed_query_args', $args, $url );

		$args['woorise-source']   = $current_url;
		$args['woorise-embed-id'] = (string) round( microtime( true ) * 1000 );
		// Activate the content-sized campaign layout instead of viewport-height sections.
		$args['woorise-embed-type'] = 'standard';

		$src = add_query_arg( urlencode_deep( $args ), $url );

		wp_enqueue_script( 'woorise-iframe-init' );

		return sprintf(
			'<iframe class="%1$s" src="%2$s" title="%3$s" data-wr-origins="%4$s" style="border:none;width:1px;min-width:100%%;" scrolling="no"></iframe>',
			esc_attr( self::IFRAME_CLASS ),
			esc_url( $src ),
			esc_attr__( 'Woorise campaign', 'woorise' ),
			esc_attr( wp_json_encode( self::get_trusted_origins( $url ) ) )
		);
	}

	/**
	 * Build the source URL without duplicating the home path.
	 *
	 * @return string Result.
	 */
	private static function current_request_url(): string {
		$home        = wp_parse_url( home_url( '/' ) );
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );
		$origin      = $home['scheme'] . '://' . $home['host'];
		if ( isset( $home['port'] ) ) {
			$origin .= ':' . $home['port'];
		}
		$args = array();
		if ( is_string( $query ) ) {
			parse_str( $query, $args );
		}
		return esc_url_raw( add_query_arg( urlencode_deep( self::filter_query_args( $args ) ), $origin . '/' . ltrim( (string) $path, '/' ) ) );
	}

	/**
	 * Accept explicitly entered HTTP(S) campaign URLs, including custom domains.
	 *
	 * @param string $url Campaign URL.
	 * @return string Sanitized URL, or an empty string for invalid input.
	 */
	public static function sanitize_embed_url( string $url ): string {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! isset( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return esc_url_raw( trim( $url ), array( 'https', 'http' ) );
	}

	/**
	 * Get the iframe's own origin and any explicitly trusted redirect origins.
	 *
	 * @param string $url Campaign URL. Empty when registering editor settings.
	 * @return string[] Allowed origins.
	 */
	public static function get_trusted_origins( string $url = '' ): array {
		/**
		 * Filters additional trusted origins for campaign redirects.
		 *
		 * @param string[] $origins Extra trusted origins.
		 * @param string   $url     Campaign URL, or an empty string for the editor.
		 */
		$origins = (array) apply_filters( 'woorise_embed_trusted_origins', array(), $url );
		if ( '' !== $url ) {
			$origins[] = $url;
		}
		$allowed = array();
		foreach ( $origins as $origin ) {
			$parts = is_string( $origin ) ? wp_parse_url( self::sanitize_embed_url( $origin ) ) : false;
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				continue;
			}
			$origin = strtolower( $parts['scheme'] . '://' . $parts['host'] );
			if ( isset( $parts['port'] ) && ! ( ( 'https' === strtolower( $parts['scheme'] ) && 443 === $parts['port'] ) || ( 'http' === strtolower( $parts['scheme'] ) && 80 === $parts['port'] ) ) ) {
				$origin .= ':' . $parts['port'];
			}
			$allowed[] = $origin;
		}
		return array_values( array_unique( $allowed ) );
	}

	/**
	 * Remove known security parameters, including nested values, before forwarding.
	 *
	 * @param array $args Customer page query parameters.
	 * @return array Filtered parameters.
	 */
	private static function filter_query_args( array $args ): array {
		/**
		 * Filters parameter names excluded from automatic forwarding and the source URL.
		 *
		 * @param string[] $excluded Case-insensitive parameter names.
		 */
		$excluded = (array) apply_filters( 'woorise_embed_excluded_query_args', array( '_wpnonce', '_ajax_nonce', '_wp_http_referer', 'nonce', 'password', 'pass', 'pwd', 'token', 'access_token', 'refresh_token', 'id_token', 'auth_token', 'authorization', 'client_secret', 'secret', 'key', 'reset_key', 'activation_key' ) );
		$excluded = array_map( 'strtolower', $excluded );
		foreach ( $args as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $excluded, true ) ) {
				unset( $args[ $key ] );
			} elseif ( is_array( $value ) ) {
				$args[ $key ] = self::filter_query_args( $value );
			}
		}
		return $args;
	}

	/**
	 * Escape and serialize supported data and ARIA attributes.
	 *
	 * @param array $attrs Attribute names and values.
	 * @return string Result.
	 */
	private static function html_attributes( array $attrs ): string {
		$out = array();
		foreach ( $attrs as $name => $value ) {
			if ( ! is_string( $name ) || ! preg_match( '/^(data|aria)-[A-Za-z0-9:._-]+$/', $name ) ) {
				continue;
			}
			if ( '' === $value ) {
				$out[] = esc_attr( $name );
			} else {
				$out[] = sprintf( '%1$s="%2$s"', esc_attr( $name ), esc_attr( (string) $value ) );
			}
		}
		return implode( ' ', $out );
	}
}
