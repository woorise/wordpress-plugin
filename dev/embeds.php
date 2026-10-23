<?php
/**
 * Isolated rendering regressions using real WordPress URL and escaping functions.
 * Run: php dev/embeds.php C:/path/to/wordpress C:/path/to/woorise
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

error_reporting( E_ALL );
define( 'ABSPATH', rtrim( $argv[1] ?? getenv( 'WORDPRESS_PATH' ), '/\\' ) . '/' );
define( 'WPINC', 'wp-includes' );
$plugin_path = $argv[2] ?? getenv( 'WOORISE_PLUGIN_PATH' );
if ( ! $plugin_path || ! is_file( rtrim( $plugin_path, '/\\' ) . '/woorise.php' ) ) {
	fwrite( STDERR, "Supply the final plugin directory as the second argument or WOORISE_PLUGIN_PATH.\n" );
	exit( 1 );
}
define( 'WOORISE_PATH', rtrim( $plugin_path, '/\\' ) . '/' );
define( 'WOORISE_FILE', WOORISE_PATH . 'woorise.php' );
define( 'WOORISE_VER', '1.5.1' );
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/http.php';
require ABSPATH . WPINC . '/shortcodes.php';

$fixture = array( 'home' => 'https://parent.example/blog', 'post_id' => 42, 'post_type' => 'page' );
$options = array( 'woorise_embed_index_v1' => array(), 'blog_charset' => 'UTF-8' );
$scripts = array();
$checks = 0;

function get_option( $key, $default = false ) { global $fixture, $options; return 'home' === $key ? $fixture['home'] : ( $options[ $key ] ?? $default ); }
function is_multisite() { return false; }
function is_ssl() { return true; }
function is_utf8_charset() { return true; }
function wp_is_valid_utf8( $value ) { return 1 === preg_match( '//u', $value ); }
function __( $text, $domain = 'default' ) { return $text; }
function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); }
function plugins_url( $path, $file = '' ) { return 'https://parent.example/wp-content/plugins/woorise/' . $path; }
function wp_register_script( $handle, $src, $deps = array(), $version = false, $footer = false ) { global $scripts; $scripts[ $handle ] = compact( 'src', 'deps', 'version', 'footer' ); return true; }
function wp_add_inline_script( $handle, $script, $position = 'after' ) { return true; }
function wp_set_script_translations( $handle, $domain, $path ) { return true; }
function wp_embed_register_handler( $name, $regex, $callback ) { return true; }
function register_block_type_from_metadata( $path, $args ) { return true; }
function wp_enqueue_script( $handle ) { global $scripts; $scripts[ $handle ]['enqueued'] = true; }
function wp_script_is( $handle, $status ) { global $scripts; return 'enqueued' === $status ? ! empty( $scripts[ $handle ]['enqueued'] ) : isset( $scripts[ $handle ] ); }
function get_block_wrapper_attributes() { return 'class="wp-block-woorise-embed"'; }
function get_post_meta( $id, $key, $single = false ) { return ''; }
function get_post_type( $id = null ) { global $fixture; return $fixture['post_type']; }
function get_post_types( $args = array(), $output = 'names' ) { return array( 'post' => 'post', 'page' => 'page', 'product' => 'product' ); }
function get_queried_object_id() { global $fixture; return $fixture['post_id']; }
function get_queried_object() { global $fixture; return (object) ( $fixture['queried_object'] ?? array() ); }
function get_taxonomy( $name ) { return (object) array( 'object_type' => 'product_cat' === $name ? array( 'product' ) : array( 'post' ) ); }
function get_query_var( $key ) { global $fixture; return $fixture['query'][ $key ] ?? ''; }
function get_the_ID() { global $fixture; return $fixture['post_id']; }
function absint( $value ) { return abs( (int) $value ); }
function fixture_flag( $name ) { global $fixture; return ! empty( $fixture[ $name ] ); }
function is_singular() { return fixture_flag( 'singular' ); }
function is_archive() { return fixture_flag( 'archive' ); }
function is_post_type_archive() { return fixture_flag( 'post_type_archive' ); }
function is_tax() { return fixture_flag( 'tax' ); }
function is_category() { return fixture_flag( 'category' ); }
function is_tag() { return fixture_flag( 'tag' ); }
function is_author() { return fixture_flag( 'author' ); }
function is_date() { return fixture_flag( 'date' ); }
function is_home() { return fixture_flag( 'home_query' ); }
function is_user_logged_in() { return fixture_flag( 'logged_in' ); }
function is_main_query() { return fixture_flag( 'main_query' ); }
function in_the_loop() { return fixture_flag( 'in_loop' ); }
function is_admin() { return fixture_flag( 'admin' ); }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_trackback() { return false; }

// Load the unmodified WordPress query-building functions without its database API.
$tokens = array_merge( token_get_all( file_get_contents( ABSPATH . WPINC . '/functions.php' ) ), token_get_all( file_get_contents( ABSPATH . WPINC . '/link-template.php' ) ) );
$wanted = array( 'add_query_arg', 'build_query', '_http_build_query', 'wp_allowed_protocols', 'home_url', 'get_home_url', 'set_url_scheme', 'wp_json_encode', '_canonical_charset' );
for ( $i = 0, $count = count( $tokens ); $i < $count; $i++ ) {
	if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) { continue; }
	$j = $i + 1;
	while ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) { $j++; }
	if ( ! is_array( $tokens[ $j ] ) || ! in_array( $tokens[ $j ][1], $wanted, true ) ) { continue; }
	$code = ''; $depth = 0; $opened = false;
	for ( $k = $i; $k < $count; $k++ ) {
		$token = $tokens[ $k ]; $code .= is_array( $token ) ? $token[1] : $token;
		if ( '{' === $token ) { $depth++; $opened = true; }
		if ( '}' === $token && 0 === --$depth && $opened ) { break; }
	}
	eval( $code );
}
require WOORISE_PATH . 'includes/class-settings.php';
require WOORISE_PATH . 'includes/class-render.php';

use Woorise\Embed\Render;
use Woorise\Embed\Settings;

function expect( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { throw new RuntimeException( $message ); } }
function iframe_query( $html ) {
	expect( 1 === preg_match( '/src="([^"]+)"/', $html, $match ), 'Missing iframe source.' );
	parse_str( wp_parse_url( html_entity_decode( $match[1] ), PHP_URL_QUERY ), $args );
	return $args;
}
function reset_queue() {
	foreach ( array( 'queue_built' => false, 'queue' => array(), 'rendered_posts' => array() ) as $name => $value ) {
		$property = new ReflectionProperty( Render::class, $name ); $property->setAccessible( true ); $property->setValue( null, $value );
	}
}

Render::init();
do_action( 'init' );
expect( isset( $scripts['woorise-embed'] ), 'Assets must register before early block rendering.' );
expect( '5.5.9' === $scripts['woorise-iframe']['version'], 'Parent resizer version must match the release.' );
expect( false !== strpos( Render::render_embed_block( array( 'embedId' => 'ABC123456' ) ), 'data-wr-embed="ABC123456"' ), 'ID-only block is empty.' );
expect( false === strpos( Render::render_embed_block( array( 'embedId' => 'ABC123456', 'url' => 'https://mapped.example/form' ) ), '<iframe' ), 'Block did not prefer its Embed ID.' );
expect( false !== strpos( Render::shortcode( array( 'id' => 'ABC123456' ) ), 'data-wr-embed' ), 'ID shortcode is empty.' );

$_SERVER['REQUEST_URI'] = '/blog/offer/?name=Tom%20%26%20Sue&email=user%2Btag%40example.test&custom=%E2%82%AC%25&items%5B0%5D=one%26two&nested%5Bnonce%5D=HIDDEN&_wpnonce=HIDDEN&access_token=HIDDEN&utm_source=newsletter';
$html = Render::get_embed_by_url( 'https://mapped.example/form?existing=keep&woorise-embed-type=popup' );
$args = iframe_query( $html );
expect( 'Tom & Sue' === $args['name'], 'Ampersands were split into extra arguments.' );
expect( 'user+tag@example.test' === $args['email'], 'Plus signs were corrupted.' );
expect( '€%' === $args['custom'], 'Unicode or percent signs were corrupted.' );
expect( array( 'one&two' ) === $args['items'], 'Array parameters were corrupted.' );
expect( 'keep' === $args['existing'], 'Campaign URL parameters were lost.' );
expect( 'standard' === $args['woorise-embed-type'], 'URL embeds must activate the content-sized campaign layout.' );
expect( ! isset( $args['_wpnonce'], $args['access_token'] ), 'Known security parameters were forwarded.' );
expect( false === strpos( $html, 'HIDDEN' ), 'Security parameter leaked through the source URL.' );
expect( 0 === strpos( $args['woorise-source'], 'https://parent.example/blog/offer/?' ), 'Subdirectory source URL was duplicated.' );
parse_str( wp_parse_url( $args['woorise-source'], PHP_URL_QUERY ), $source_args );
expect( 'Tom & Sue' === $source_args['name'], 'Nested source query did not round-trip.' );
expect( 'newsletter' === $source_args['utm_source'] && ! isset( $args['utm_source'] ), 'Existing tracking parameter behavior changed.' );
expect( false !== strpos( $html, 'title="Woorise campaign"' ), 'Iframe is missing an accessible title.' );
expect( '' === Render::get_embed_by_url( 'javascript:alert(1)' ), 'Executable iframe URL was accepted.' );
expect( '' === Render::get_embed_by_url( 'https://user:password@mapped.example/form' ), 'Credential-bearing URL was accepted.' );
expect( '' === Render::get_embed_by_url( '//mapped.example/form' ), 'Implicit scheme was accepted.' );
expect( array( 'https://mapped.example' ) === Render::get_trusted_origins( 'https://mapped.example:443/form' ), 'Mapped origin or default port normalization failed.' );
expect( false !== strpos( Render::render_embed_block( array( 'url' => 'https://mapped.example/form' ) ), '<iframe' ), 'Existing URL blocks no longer render.' );

$allow_redirect = static function( $origins ) { return array_merge( $origins, array( 'https://redirect.example/path', 'javascript:invalid' ) ); };
add_filter( 'woorise_embed_trusted_origins', $allow_redirect );
expect( array( 'https://redirect.example', 'https://mapped.example' ) === Render::get_trusted_origins( 'https://mapped.example/form' ), 'Explicit redirect origin filter failed.' );
remove_filter( 'woorise_embed_trusted_origins', $allow_redirect );
$allow_token = static function( $excluded ) { return array_diff( $excluded, array( 'access_token' ) ); };
add_filter( 'woorise_embed_excluded_query_args', $allow_token );
expect( 'HIDDEN' === iframe_query( Render::get_embed_by_url( 'https://mapped.example/form' ) )['access_token'], 'Forwarding exceptions cannot be configured.' );
remove_filter( 'woorise_embed_excluded_query_args', $allow_token );

$baseline = $fixture;
foreach ( array( 'category', 'tag', 'author', 'date', 'home_query' ) as $scope ) {
	$fixture = array_merge( $baseline, array( $scope => true, 'archive' => 'home_query' !== $scope, 'queried_object' => array( 'taxonomy' => $scope ) ) );
	expect( Settings::rules_match( array( array( array( 'field' => 'archive', 'operator' => 'is', 'value' => array( 'post' ) ) ) ) ), $scope . ' archive failed to match Posts.' );
}
$fixture = array_merge( $baseline, array( 'archive' => true, 'tax' => true, 'queried_object' => array( 'taxonomy' => 'product_cat' ) ) );
expect( Settings::rules_match( array( array( array( 'field' => 'archive', 'operator' => 'is', 'value' => array( 'product' ) ) ) ) ), 'Custom taxonomy archive stopped matching.' );
$fixture['query']['post_type'] = array( 'post', 'product' );
expect( Settings::rules_match( array( array( array( 'field' => 'archive', 'operator' => 'is', 'value' => array( 'post' ) ) ) ) ), 'Explicit mixed archive types were ignored.' );

$fixture = array_merge( $baseline, array( 'singular' => true, 'main_query' => true, 'in_loop' => true ) );
$wp_query = (object) array( 'post' => (object) array( 'ID' => 42 ) );
$options['woorise_embed_index_v1'] = array( array( 'embed_id' => 'ABC123456', 'placement' => 'content_bottom', 'rules' => array() ) );
reset_queue();
expect( false !== strpos( Render::filter_the_content( '<p>Main</p>' ), 'data-wr-lazy' ), 'After-content lazy placement marker was lost.' );
expect( '<p>Repeated</p>' === Render::filter_the_content( '<p>Repeated</p>' ), 'The same post received duplicate automatic placements.' );
reset_queue(); $fixture['in_loop'] = false;
expect( false !== strpos( Render::filter_the_content( '<p>Block theme</p>' ), 'data-wr-embed' ), 'A singular block theme outside the classic loop lost content placements.' );
reset_queue(); $fixture['singular'] = false;
expect( '<p>Outside loop</p>' === Render::filter_the_content( '<p>Outside loop</p>' ), 'A nonsingular view outside its loop received a content placement.' );
$fixture['singular'] = true; $fixture['in_loop'] = true;
reset_queue(); $fixture['post_id'] = 99;
expect( '<p>Related</p>' === Render::filter_the_content( '<p>Related</p>' ), 'Nested secondary content received automatic placements.' );
$fixture['post_id'] = 42; $fixture['main_query'] = false;
expect( '<p>Secondary</p>' === Render::filter_the_content( '<p>Secondary</p>' ), 'Secondary query received automatic placements.' );

$options['woorise_embed_index_v1'][0]['placement'] = 'wp_head';
reset_queue();
ob_start(); do_action( 'wp_head' ); $head = ob_get_clean();
expect( '' === $head, 'An embed container was emitted in the head.' );
ob_start(); Render::output_wp_footer(); $footer = ob_get_clean();
expect( 1 === substr_count( $footer, 'data-wr-embed' ), 'Themes without body-open lost their old head placement.' );
reset_queue();
ob_start(); do_action( 'wp_body_open' ); $body = ob_get_clean();
ob_start(); Render::output_wp_footer(); $footer = ob_get_clean();
expect( 1 === substr_count( $body, 'data-wr-embed' ) && '' === $footer, 'Body-open placement was lost or duplicated in the footer.' );

echo "PASS: {$checks} isolated embed checks.\n";
