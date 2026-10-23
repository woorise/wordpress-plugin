<?php
/**
 * Embed settings, display conditions, and cached configuration.
 *
 * @package Woorise
 */

declare( strict_types = 1 );

namespace Woorise\Embed;

defined( 'ABSPATH' ) || exit;

/**
 * Manage embed settings and evaluate display conditions.
 */
final class Settings {
	public const META_EMBED_ID  = '_woorise_embed_id';
	public const META_PLACEMENT = '_woorise_embed_placement';
	private const META_RULES    = '_woorise_embed_rules';

	private const NONCE_ACTION = 'woorise_embed_save';
	private const NONCE_FIELD  = 'woorise_embed_nonce';
	private const OPTION_INDEX = 'woorise_embed_index_v1';

	/**
	 * Register WordPress hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_woorise_embed', array( __CLASS__, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'save_post_woorise_embed', array( __CLASS__, 'rebuild_index' ), 999, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_rebuild_index' ), 10, 1 );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_rebuild_index' ), 10, 1 );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_rebuild_index' ), 10, 1 );
	}

	/**
	 * Enqueue translated controls for the embed settings screen.
	 *
	 * @param string $hook Current administration screen hook.
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		global $post_type;

		if ( ( 'post.php' !== $hook && 'post-new.php' !== $hook ) || 'woorise_embed' !== $post_type ) {
			return;
		}

		wp_enqueue_style(
			'woorise-embed-settings',
			plugins_url( '../assets/admin/embed-settings.css', __FILE__ ),
			array(),
			\WOORISE_VER
		);

		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_style( 'wp-components' );

		wp_register_script(
			'woorise-embed-settings',
			plugins_url( '../assets/admin/embed-settings.js', __FILE__ ),
			array( 'wp-element', 'wp-components', 'wp-i18n' ),
			\WOORISE_VER,
			true
		);
		wp_set_script_translations( 'woorise-embed-settings', 'woorise', \WOORISE_PATH . 'languages' );

		$cpt_objects = get_post_types( array( 'public' => true ), 'objects' );
		unset( $cpt_objects['attachment'] );

		$cpts = array();
		foreach ( $cpt_objects as $slug => $obj ) {
			$cpts[] = array(
				'slug'  => $slug,
				'label' => $obj->labels->name,
			);
		}
		usort(
			$cpts,
			static fn( $a, $b ) => strcasecmp( (string) $a['label'], (string) $b['label'] )
		);

		$wc_pages = array();
		$fields   = array(
			array(
				'key'   => 'site',
				'label' => __( 'Site-wide', 'woorise' ),
				'type'  => 'scope_site',
			),
			array(
				'key'   => 'singular',
				'label' => __( 'Singular', 'woorise' ),
				'type'  => 'scope_singular',
			),
			array(
				'key'   => 'archive',
				'label' => __( 'Archives', 'woorise' ),
				'type'  => 'scope_archive',
			),
			array(
				'key'   => 'user',
				'label' => __( 'User', 'woorise' ),
				'type'  => 'enum',
			),
			array(
				'key'   => 'page',
				'label' => __( 'Pages', 'woorise' ),
				'type'  => 'ids',
			),
			array(
				'key'   => 'url',
				'label' => __( 'URL', 'woorise' ),
				'type'  => 'text',
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$wc_pages = array(
				array(
					'slug'  => 'product',
					'label' => __( 'Product', 'woorise' ),
				),
				array(
					'slug'  => 'shop',
					'label' => __( 'Shop archive', 'woorise' ),
				),
				array(
					'slug'  => 'cart',
					'label' => __( 'Cart', 'woorise' ),
				),
				array(
					'slug'  => 'checkout',
					'label' => __( 'Checkout', 'woorise' ),
				),
				array(
					'slug'  => 'account',
					'label' => __( 'Account', 'woorise' ),
				),
			);

			$fields[] = array(
				'key'   => 'woocommerce',
				'label' => __( 'WooCommerce', 'woorise' ),
				'type'  => 'wc',
			);
		}

		$operators = array(
			'scope_site'     => array(),
			'scope_singular' => array(
				'is'     => __( 'Is', 'woorise' ),
				'is_not' => __( 'Is not', 'woorise' ),
			),
			'scope_archive'  => array(
				'is'     => __( 'Is', 'woorise' ),
				'is_not' => __( 'Is not', 'woorise' ),
			),
			'enum'           => array(
				'is'     => __( 'Is', 'woorise' ),
				'is_not' => __( 'Is not', 'woorise' ),
			),
			'ids'            => array(
				'in'     => __( 'In', 'woorise' ),
				'not_in' => __( 'Not in', 'woorise' ),
			),
			'text'           => array(
				'contains'     => __( 'Contains', 'woorise' ),
				'not_contains' => __( 'Does not contain', 'woorise' ),
				'starts_with'  => __( 'Starts with', 'woorise' ),
				'ends_with'    => __( 'Ends with', 'woorise' ),
				'is'           => __( 'Is', 'woorise' ),
				'is_not'       => __( 'Is not', 'woorise' ),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$operators['wc'] = array(
				'is'     => __( 'Is', 'woorise' ),
				'is_not' => __( 'Is not', 'woorise' ),
			);
		}

		wp_localize_script(
			'woorise-embed-settings',
			'WooriseConditions',
			array(
				'cpts'      => $cpts,
				'fields'    => $fields,
				'operators' => $operators,
				'wcPages'   => $wc_pages,
			)
		);

		wp_enqueue_script( 'woorise-embed-settings' );
	}

	/**
	 * Register settings and display condition panels.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'woorise_embed_settings',
			__( 'Embed Settings', 'woorise' ),
			array( __CLASS__, 'render_settings_box' ),
			'woorise_embed',
			'normal',
			'high'
		);

		add_meta_box(
			'woorise_embed_conditions',
			__( 'Display Conditions', 'woorise' ),
			array( __CLASS__, 'render_conditions_box' ),
			'woorise_embed',
			'normal',
			'default'
		);
	}

	/**
	 * Render the nonce-protected settings panel.
	 *
	 * @param \WP_Post $post Embed post being edited.
	 */
	public static function render_settings_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$embed_id  = get_post_meta( $post->ID, self::META_EMBED_ID, true );
		$placement = get_post_meta( $post->ID, self::META_PLACEMENT, true );
		if ( 'wp_head' === $placement ) {
			$placement = 'wp_body_open';
		}
		if ( '' === (string) $placement ) {
			$placement = 'wp_footer';
		}
		$placements = self::placements(); ?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row">
					<label for="woorise_embed_id"><?php esc_html_e( 'Embed ID', 'woorise' ); ?></label>
				</th>
				<td>
					<input type="text"
						id="woorise_embed_id"
						name="woorise_embed_id"
						class="regular-text"
						value="<?php echo esc_attr( (string) $embed_id ); ?>"
						aria-describedby="woorise_embed_id_desc"
						placeholder="<?php esc_attr_e( 'Paste the Woorise Embed ID', 'woorise' ); ?>" />
					<p id="woorise_embed_id_desc" class="description">
						<?php esc_html_e( 'The unique ID of your Woorise embed (e.g., ABC123456).', 'woorise' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="woorise_embed_placement"><?php esc_html_e( 'Placement', 'woorise' ); ?></label>
				</th>
				<td>
					<select id="woorise_embed_placement" name="woorise_embed_placement">
						<?php foreach ( $placements as $key => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $placement, $key ); ?>>
								<?php echo esc_html( (string) $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Choose where the embed will be injected automatically.', 'woorise' ); ?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the display condition editor and saved rules.
	 *
	 * @param \WP_Post $post Embed post being edited.
	 */
	public static function render_conditions_box( \WP_Post $post ): void {
		$rules = self::get_rules( $post->ID );
		if ( empty( $rules ) ) {
			$rules = array(
				array(
					array(
						'field'    => '',
						'operator' => '',
						'value'    => '',
					),
				),
			);
		}
		?>
		<div id="wr-cond-react" data-initial="<?php echo esc_attr( wp_json_encode( $rules ) ); ?>"></div>
		<input type="hidden" name="woorise_rules" id="woorise_rules" value="<?php echo esc_attr( wp_json_encode( $rules ) ); ?>">
		<?php
	}

	/**
	 * Save sanitized settings after nonce and capability checks.
	 *
	 * @param int $post_id Embed post ID.
	 */
	public static function save_meta( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$embed_id = isset( $_POST['woorise_embed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['woorise_embed_id'] ) ) : '';
		update_post_meta( $post_id, self::META_EMBED_ID, $embed_id );

		$placement = isset( $_POST['woorise_embed_placement'] ) ? sanitize_key( wp_unslash( $_POST['woorise_embed_placement'] ) ) : '';
		$allowed   = array_keys( self::placements() );
		if ( ! in_array( $placement, $allowed, true ) ) {
			$placement = 'wp_footer';
		}
		update_post_meta( $post_id, self::META_PLACEMENT, $placement );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Structured values are sanitized by sanitize_rules() below.
		$raw_rules = isset( $_POST['woorise_rules'] ) ? wp_unslash( $_POST['woorise_rules'] ) : array();
		if ( is_string( $raw_rules ) ) {
			$try       = json_decode( $raw_rules, true );
			$raw_rules = is_array( $try ) ? $try : array();
		}
		update_post_meta( $post_id, self::META_RULES, self::sanitize_rules( is_array( $raw_rules ) ? $raw_rules : array() ) );
	}

	/**
	 * Check whether an embed matches the current request.
	 *
	 * @param int $post_id Embed post ID.
	 * @return bool Result.
	 */
	public static function should_render( int $post_id ): bool {
		$embed_id = (string) get_post_meta( $post_id, self::META_EMBED_ID, true );
		if ( '' === $embed_id ) {
			return false;
		}
		return self::rules_match( self::get_rules( $post_id ) );
	}

	/**
	 * Check whether every condition in an AND group matches.
	 *
	 * @param array $rules Display condition groups.
	 * @return bool Result.
	 */
	private static function and_group_matches( array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( ! self::rule_matches( $rule ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Evaluate one display condition for the current request.
	 *
	 * @param array $rule One display condition.
	 * @return bool Result.
	 */
	private static function rule_matches( array $rule ): bool {
		$field    = isset( $rule['field'] ) ? sanitize_key( (string) $rule['field'] ) : '';
		$operator = isset( $rule['operator'] ) ? sanitize_key( (string) $rule['operator'] ) : '';
		$value    = $rule['value'] ?? '';

		switch ( $field ) {
			case 'site':
				return true;

			case 'singular':
				$want = array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );
				$have = self::current_post_types();
				$hit  = ! empty( array_intersect( $want, $have ) ) && is_singular();
				return ( 'is' === $operator ) ? $hit : ( 'is_not' === $operator ? ! $hit : false );

			case 'archive':
				$want       = array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );
				$have       = self::current_post_types();
				$on_archive = is_archive() || is_home();
				$hit        = $on_archive && ! empty( array_intersect( $want, $have ) );
				return ( 'is' === $operator ) ? $hit : ( 'is_not' === $operator ? ! $hit : false );

			case 'page':
				$ids = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
				if ( empty( $ids ) ) {
					return false;
				}
				if ( ! is_singular() ) {
					return ( 'not_in' === $operator );
				}
				$current_id = (int) get_queried_object_id();
				$in         = in_array( $current_id, $ids, true );
				return ( 'in' === $operator ) ? $in : ( 'not_in' === $operator ? ! $in : false );

			case 'user':
				$state = is_string( $value ) ? sanitize_key( $value ) : '';
				$match = ( 'logged_in' === $state ) ? is_user_logged_in() : ( 'logged_out' === $state ? ! is_user_logged_in() : false );
				return ( 'is' === $operator ) ? $match : ( 'is_not' === $operator ? ! $match : false );

			case 'url':
				$val         = is_string( $value ) ? (string) $value : '';
				$host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
				$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $host . $request_uri;
				if ( '' === $val ) {
					return false;
				}

				$needles = preg_split( '/\s*,\s*/', $val, -1, PREG_SPLIT_NO_EMPTY );
				if ( ! $needles ) {
					return false;
				}

				$matches_any = static function ( callable $cb ) use ( $needles ): bool {
					foreach ( $needles as $n ) {
						if ( $cb( $n ) ) {
							return true;
						}
					}
					return false;
				};

				$matches_all_not = static function ( callable $cb ) use ( $needles ): bool {
					foreach ( $needles as $n ) {
						if ( $cb( $n ) ) {
							return false;
						}
					}
					return true;
				};

				switch ( $operator ) {
					case 'contains':
						return $matches_any( static fn( $n ) => str_contains( $current_url, $n ) );

					case 'not_contains':
						return $matches_all_not( static fn( $n ) => str_contains( $current_url, $n ) );

					case 'starts_with':
						return $matches_any( static fn( $n ) => str_starts_with( $current_url, $n ) );

					case 'ends_with':
						return $matches_any( static fn( $n ) => str_ends_with( $current_url, $n ) );

					case 'is':
						return $matches_any( static fn( $n ) => $current_url === $n );

					case 'is_not':
						return $matches_all_not( static fn( $n ) => $current_url === $n );
				}

				return false;

			case 'woocommerce':
				if ( ! class_exists( 'WooCommerce' ) ) {
					return false;
				}

				$want = array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );
				if ( empty( $want ) ) {
					return false;
				}

				$have = self::current_wc_pages();
				$hit  = ! empty( array_intersect( $want, $have ) );

				return ( 'is' === $operator ) ? $hit : ( 'is_not' === $operator ? ! $hit : false );
		}

		return false;
	}

	/**
	 * Get WooCommerce scopes matching the current request.
	 *
	 * @return array Result.
	 */
	private static function current_wc_pages(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$have = array();

		if ( function_exists( 'is_product' ) && is_product() ) {
			$have[] = 'product';
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$have[] = 'shop';
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$have[] = 'cart';
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$have[] = 'checkout';
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$have[] = 'account';
		}

		return array_values( array_unique( $have ) );
	}

	/**
	 * Get post types targeted by the main query.
	 *
	 * @return array Result.
	 */
	private static function current_post_types(): array {
		if ( is_singular() ) {
			$type = get_post_type( get_queried_object_id() );
			return $type ? array( $type ) : array();
		}

		if ( is_archive() || is_home() ) {
			$post_type = get_query_var( 'post_type' );
			if ( 'any' === $post_type ) {
				return array_values( get_post_types( array( 'public' => true ) ) );
			}
			if ( is_array( $post_type ) ) {
				return array_values( array_map( 'sanitize_key', $post_type ) );
			}
			if ( is_string( $post_type ) && $post_type ) {
				return array( sanitize_key( $post_type ) );
			}
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$qo  = get_queried_object();
			$tax = $qo && isset( $qo->taxonomy ) ? get_taxonomy( $qo->taxonomy ) : null;
			if ( $tax && is_array( $tax->object_type ) ) {
				return array_values( array_map( 'sanitize_key', $tax->object_type ) );
			}
		}

		if ( is_post_type_archive() ) {
			$obj = get_queried_object();
			return isset( $obj->name ) && is_string( $obj->name ) ? array( sanitize_key( $obj->name ) ) : array();
		}

		if ( is_author() || is_date() || is_home() ) {
			return array( 'post' );
		}

		return array();
	}

	/**
	 * Read saved display condition groups.
	 *
	 * @param int $post_id Embed post ID.
	 * @return array Result.
	 */
	private static function get_rules( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::META_RULES, true );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Normalize submitted condition groups before storage.
	 *
	 * @param mixed $raw Submitted display condition groups.
	 * @return array Result.
	 */
	private static function sanitize_rules( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$to_array = static function ( $v ): array {
			if ( is_array( $v ) ) {
				return array_values( $v );
			}
			if ( is_string( $v ) ) {
				$trim = trim( $v );
				if ( '' !== $trim && ( '[' === $trim[0] || '{' === $trim[0] ) ) {
					$decoded = json_decode( $trim, true );
					if ( is_array( $decoded ) ) {
						return array_values( $decoded );
					}
				}
				$parts = preg_split( '/\s*,\s*/', $v, -1, PREG_SPLIT_NO_EMPTY );
				return $parts ? array_values( $parts ) : array();
			}
			return array();
		};

		$out = array();

		foreach ( $raw as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$rules = array();

			foreach ( $group as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$field    = isset( $rule['field'] ) ? sanitize_key( (string) $rule['field'] ) : '';
				$operator = isset( $rule['operator'] ) ? sanitize_key( (string) $rule['operator'] ) : '';
				$value    = $rule['value'] ?? '';

				switch ( $field ) {
					case 'site':
						$operator = '';
						$value    = '';
						break;

					case 'singular':
					case 'archive':
						$value = array_values( array_filter( array_map( 'sanitize_key', $to_array( $value ) ) ) );
						if ( empty( $value ) ) {
							continue 2; }
						break;

					case 'woocommerce':
						if ( ! class_exists( 'WooCommerce' ) ) {
							continue 2;
						}
						$value   = array_values( array_filter( array_map( 'sanitize_key', $to_array( $value ) ) ) );
						$allowed = array( 'product', 'shop', 'cart', 'checkout', 'account' );
						$value   = array_values( array_intersect( $value, $allowed ) );
						if ( empty( $value ) ) {
							continue 2; }
						break;

					case 'page':
						$value = array_values( array_unique( array_filter( array_map( 'absint', $to_array( $value ) ) ) ) );
						if ( empty( $value ) ) {
							continue 2; }
						break;

					case 'user':
						$value = 'logged_in';
						break;

					case 'url':
						$parts = $to_array( $value );
						$parts = array_map(
							static function ( $v ): string {
								$v = sanitize_text_field( (string) $v );
								return trim( $v );
							},
							$parts
						);
						$parts = array_values( array_filter( $parts, static fn( $v ) => '' !== $v ) );
						$parts = array_values( array_unique( $parts ) );

						$parts = array_slice( $parts, 0, 20 );
						foreach ( $parts as &$p ) {
							if ( strlen( $p ) > 512 ) {
								$p = substr( $p, 0, 512 );
							}
						}
						unset( $p );

						if ( empty( $parts ) ) {
							continue 2; }

						$value = implode( ',', $parts );
						break;

					default:
						continue 2;
				}

				$rules[] = array(
					'field'    => $field,
					'operator' => $operator,
					'value'    => $value,
				);
			}

			if ( ! empty( $rules ) ) {
				$out[] = $rules;
			}
		}

		return $out;
	}

	/**
	 * Get supported automatic placements.
	 *
	 * @return array Result.
	 */
	private static function placements(): array {
		return array(
			'wp_body_open'   => __( 'Beginning of page', 'woorise' ),
			'wp_footer'      => __( 'Site footer', 'woorise' ),
			'content_top'    => __( 'Before content', 'woorise' ),
			'content_bottom' => __( 'After content', 'woorise' ),
		);
	}

	/**
	 * Read or lazily rebuild the published embed index.
	 *
	 * @return array Result.
	 */
	public static function get_index(): array {
		$index = get_option( self::OPTION_INDEX );
		if ( is_array( $index ) ) {
			return $index;
		}

		if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			$lock_key = self::OPTION_INDEX . '_rebuild_lock';
			if ( ! get_transient( $lock_key ) ) {
				set_transient( $lock_key, 1, HOUR_IN_SECONDS );
				self::rebuild_index();
				$index = get_option( self::OPTION_INDEX );
				if ( is_array( $index ) ) {
					return $index;
				}
			}
		}

		return array();
	}

	/**
	 * Refresh cached published embed configuration.
	 */
	public static function rebuild_index(): void {
		$args = array(
			'post_type'      => 'woorise_embed',
			'posts_per_page' => 30,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$ids   = get_posts( $args );
		$index = array();

		if ( $ids ) {
			foreach ( $ids as $pid ) {
				$embed_id = (string) get_post_meta( $pid, self::META_EMBED_ID, true );
				if ( '' === $embed_id ) {
					continue;
				}
				$placement = (string) get_post_meta( $pid, self::META_PLACEMENT, true );
				if ( '' === $placement ) {
					$placement = 'wp_footer';
				}
				$rules = self::get_rules( (int) $pid );

				$index[] = array(
					'post_id'   => (int) $pid,
					'embed_id'  => $embed_id,
					'placement' => $placement,
					'rules'     => is_array( $rules ) ? $rules : array(),
				);
			}
		}

		update_option( self::OPTION_INDEX, $index, false );
	}

	/**
	 * Refresh the index after an embed changes status.
	 *
	 * @param int $post_id Embed post ID.
	 */
	public static function maybe_rebuild_index( int $post_id ): void {
		if ( 'woorise_embed' === get_post_type( $post_id ) ) {
			self::rebuild_index();
		}
	}

	/**
	 * Check whether any saved AND group matches the current request.
	 *
	 * @param array $rules Display condition groups.
	 * @return bool Result.
	 */
	public static function rules_match( array $rules ): bool {
		if ( empty( $rules ) ) {
			return true;
		}
		foreach ( $rules as $and_rules ) {
			if ( self::and_group_matches( $and_rules ) ) {
				return true;
			}
		}
		return false;
	}
}
