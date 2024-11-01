<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_ALIDROPSHIP_DATA {
	private static $prefix;
	private $params;
	private $default;
	private static $countries;
	private static $states;
	private static $ali_states = array();
	protected static $instance = null;
	protected static $allow_html = null;
	protected static $is_ald_table = null;

	/**
	 * VI_WOO_ALIDROPSHIP_DATA constructor.
	 */
	public function __construct() {
		self::$prefix = 'vi-wad-';
		global $wooaliexpressdropship_settings;
		if ( ! $wooaliexpressdropship_settings ) {
			$wooaliexpressdropship_settings = get_option( 'wooaliexpressdropship_params', array() );
		}
		$this->default = array(
			'enable'                                => '1',
			'secret_key'                            => '',
			'product_status'                        => 'publish',
			'catalog_visibility'                    => 'visible',
			'product_gallery'                       => '1',
			'product_categories'                    => array(),
			'product_tags'                          => array(),
			'product_shipping_class'                => '',
			'product_description'                   => 'item_specifics_and_description',
			'variation_visible'                     => '',
			'manage_stock'                          => '1',
			'ignore_ship_from'                      => '',
			'price_from'                            => array( 0 ),
			'price_to'                              => array( '' ),
			'plus_value'                            => array( 200 ),
			'plus_sale_value'                       => array( - 1 ),
			'plus_value_type'                       => array( 'percent' ),
			'price_default'                         => array(
				'plus_value'      => 2,
				'plus_sale_value' => 1,
				'plus_value_type' => 'multiply',
			),
			'import_product_currency'               => 'USD',
			'import_currency_rate'                  => '1',
			'import_currency_rate_RUB'              => '',
			'fulfill_default_carrier'               => 'EMS_ZX_ZX_US',
			'fulfill_default_phone_number'          => '',
			'fulfill_default_phone_number_override' => '',
			'fulfill_default_phone_country'         => '',
			'fulfill_order_note'                    => 'I\'m dropshipping. Please DO NOT put any invoices, QR codes, promotions or your brand name logo in the shipments. Please ship as soon as possible for repeat business. Thank you!',
			'order_status_for_fulfill'              => array( 'wc-completed', 'wc-on-hold', 'wc-processing' ),
			'order_status_after_sync'               => 'wc-completed',
			'string_replace'                        => array(),
			'carrier_name_replaces'                 => array(
				'from_string' => array(),
				'to_string'   => array(),
				'sensitive'   => array(),
			),
			'carrier_url_replaces'                  => array(
				'from_string' => array(),
				'to_string'   => array(),
			),
			'disable_background_process'            => '',
			'simple_if_one_variation'               => '',
			'download_description_images'           => '',
			'show_shipping_option'                  => '1',
			'shipping_cost_after_price_rules'       => '1',
			'use_global_attributes'                 => '1',
			'format_price_rules_enable'             => '',
			'format_price_rules_test'               => 0,
			'format_price_rules'                    => array(),
			'override_hide'                         => 0,
			'override_keep_product'                 => 1,
			'override_title'                        => 0,
			'override_images'                       => 0,
			'override_description'                  => 0,
			'override_find_in_orders'               => 1,
			'delete_woo_product'                    => 1,
			'cpf_custom_meta_key'                   => '',
			'billing_number_meta_key'               => '',
			'shipping_number_meta_key'              => '',
			'billing_neighborhood_meta_key'         => '',
			'shipping_neighborhood_meta_key'        => '',
			'rut_meta_key'                          => '',
			'use_external_image'                    => '',
			'fulfill_billing_fields_in_latin'       => '',
			'ald_table'                             => '',
		);

		$this->params = wp_parse_args( $wooaliexpressdropship_settings, $this->default ) ;
	}

	public function get_params( $name = "" ,$default = false) {
		if ( ! $name ) {
			return apply_filters( 'wooaliexpressdropship_params',$this->params);
		}
		$name_filter = 'wooaliexpressdropship_params_' . $name;
		if (!isset($result)){
			$result = $this->params[ $name ] ?? $default;
		}
		return $name_filter ? apply_filters( $name_filter, $result) : $result;
	}

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public static function get_attribute_name_by_slug( $slug ) {
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 */
	public static function get_domain_from_url( $url ) {
		$url     = strtolower( $url );
		$url_arr = explode( '//', $url );
		if ( count( $url_arr ) > 1 ) {
			$url = str_replace( 'www.', '', $url_arr[1] );

		} else {
			$url = str_replace( 'www.', '', $url_arr[0] );
		}
		$url_arr = explode( '/', $url );
		$url     = $url_arr[0];

		return $url;
	}

	/**
	 * @param array $args
	 * @param bool $return_sku
	 *
	 * @return array
	 */
	public static function get_imported_products( $args = array(), $return_sku = false ) {
		$imported_products = array();
		$args              = wp_parse_args( $args, array(
			'post_type'      => 'vi_wad_draft_product',
			'posts_per_page' => - 1,
			'meta_key'       => '_vi_wad_sku',// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'post_status'    => array(
				'publish',
				'draft',
				'override'
			),
			'fields'         => 'ids'
		) );

//		$the_query = new WP_Query( $args );
		$the_query = VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? new Ali_Product_Query( $args ) : new WP_Query( $args );

		if ( $the_query->have_posts() ) {
			if ( $return_sku ) {
				foreach ( $the_query->posts as $product_id ) {
					$product_sku = Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_sku', true );
					if ( $product_sku ) {
						$imported_products[] = $product_sku;
					}
				}
			} else {
				$imported_products = $the_query->posts;
			}
		}
		wp_reset_postdata();

		return $imported_products;
	}

	public static function product_get_woo_id_by_aliexpress_id( $aliexpress_id, $is_variation = false, $count = false, $multiple = false ) {
		global $wpdb;
		if ( $aliexpress_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			if ( $is_variation ) {
				$post_type = 'product_variation';
				$meta_key  = '_vi_wad_aliexpress_variation_id';
			} else {
				$post_type = 'product';
				$meta_key  = '_vi_wad_aliexpress_product_id';
			}
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $aliexpress_id ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $aliexpress_id ), ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$query   .= ' LIMIT 1';
					$results = $wpdb->get_var( $wpdb->prepare( $query, $aliexpress_id ), 1 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	/**
	 * @param $product_id
	 * @param bool $count
	 * @param bool $multiple
	 * @param array $status
	 *
	 * @return array|bool|object|string|null
	 */
	public static function product_get_id_by_woo_id(
		$product_id, $count = false, $multiple = false, $status = array(
		'publish',
		'draft',
		'override'
	)
	) {
		global $wpdb;
		if ( $product_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			$post_type      = 'vi_wad_draft_product';
			$meta_key       = '_vi_wad_woo_id';
			$post_status    = '';
			if ( $status ) {
				if ( is_array( $status ) ) {
					$status_count = count( $status );
					if ( $status_count === 1 ) {
						$post_status = " AND {$table_posts}.post_status='{$status[0]}' ";
					} elseif ( $status_count > 1 ) {
						$post_status = " AND {$table_posts}.post_status IN ('" . implode( "','", $status ) . "') ";
					}
				} else {
					$post_status = " AND {$table_posts}.post_status='{$status}' ";
				}
			}

			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'{$post_status}and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $product_id ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'{$post_status}and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $product_id ), ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$query   .= ' LIMIT 1';
					$results = $wpdb->get_var( $wpdb->prepare( $query, $product_id ), 1 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	/**Get vi_wad_draft_product ID that will override $product_id
	 *
	 * @param $product_id
	 *
	 * @return bool|string|null
	 */
	public static function get_overriding_product( $product_id ) {
		global $wpdb;
		if ( $product_id ) {
			$table_posts = "{$wpdb->prefix}posts";
			$query       = "SELECT ID from {$table_posts} where {$table_posts}.post_type = 'vi_wad_draft_product' and {$table_posts}.post_status = 'override' and {$table_posts}.post_parent = %s LIMIT 1";

			return $wpdb->get_var( $wpdb->prepare( $query, $product_id ), 0 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			return false;
		}
	}

	/**
	 * @param $aliexpress_id
	 * @param array $post_status
	 * @param bool $count
	 * @param bool $multiple
	 *
	 * @return array|string|null
	 */
	public static function product_get_id_by_aliexpress_id( $aliexpress_id, $post_status = [ 'publish', 'draft', 'override' ], $count = false, $multiple = false ) {
		global $wpdb;
		$table_posts    = self::is_ald_table() ? $wpdb->ald_posts : "{$wpdb->prefix}posts";
		$table_postmeta = self::is_ald_table() ? $wpdb->ald_postmeta : "{$wpdb->prefix}postmeta";
		$post_id_column = self::is_ald_table() ? 'ald_post_id' : 'post_id';
		$post_type      = 'vi_wad_draft_product';
		$meta_key       = '_vi_wad_sku';
		$args           = array();
		$where          = array();
		if ( $post_status ) {
			if ( is_array( $post_status ) ) {
				if ( count( $post_status ) === 1 ) {
					$where[] = "{$table_posts}.post_status=%s";
					$args[]  = $post_status[0];
				} else {
					$where[] = "{$table_posts}.post_status IN (" . implode( ', ', array_fill( 0, count( $post_status ), '%s' ) ) . ")";
					foreach ( $post_status as $v ) {
						$args[] = $v;
					}
				}
			} else {
				$where[] = "{$table_posts}.post_status=%s";
				$args[]  = $post_status;
			}
		}
		if ( $aliexpress_id ) {
			$where[] = "{$table_postmeta}.meta_key = '{$meta_key}'";
			$where[] = "{$table_postmeta}.meta_value = %s";
			$args[]  = $aliexpress_id;
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'";
				$query   .= ' AND ' . implode( ' AND ', $where );
				$results = $wpdb->get_var( $wpdb->prepare( $query, $args ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'";
				$query .= ' AND ' . implode( ' AND ', $where );

				if ( $multiple ) {
					$results = $wpdb->get_col( $wpdb->prepare( $query, $args ), 1 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$query   .= ' LIMIT 1';
					$results = $wpdb->get_var( $wpdb->prepare( $query, $args ), 1 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

		} else {
			$where[] = "{$table_postmeta}.meta_key = '{$meta_key}'";
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'";
				$query   .= ' AND ' . implode( ' AND ', $where );
				$results = $wpdb->get_var( count( $args ) ? $wpdb->prepare( $query, $args ) : $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query   = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.{$post_id_column}={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}'";
				$query   .= ' AND ' . implode( ' AND ', $where );
				$results = $wpdb->get_col( count( $args ) ? $wpdb->prepare( $query, $args ) : $query, 1 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		return $results;
	}

	/**
	 * @param $url
	 * @param array $args
	 * @param string $html
	 * @param bool $skip_ship_from_check
	 *
	 * @return array
	 */
	public static function get_data( $url, $args = array(), $html = '', $skip_ship_from_check = false, $product_args=[]  ) {
		$response   = array(
			'status'  => 'success',
			'message' => '',
			'code'    => '',
			'data'    => array(),
		);
		$attributes = array(
			'sku' => '',
		);
		if ($html ==='viwad_init_data_before' && !empty($product_args['product_id'])){
			$product_data = self::ali_request([], wp_json_encode( $product_args),[],'https://aldapi.vinext.net/get_product');
			$html= isset($product_data['data']['product']) ? $product_data['data']['product'] : [];
			if (!empty($product_data['data']['freight'])){
				$response['freight'] = $product_data['data']['freight']['methods']??$product_data['data']['freight'];
			}
		}
		if ( ! $html && $url) {
			$args             = wp_parse_args( $args, array(
				'user-agent' => self::get_user_agent(),
				'timeout'    => 10,
			) );
			$request          = wp_remote_get( $url, $args );
			$response['code'] = wp_remote_retrieve_response_code( $request );
			if ( ! is_wp_error( $request ) ) {
				$html = $request['body'];
			} else {
				$response['status']  = 'error';
				$response['message'] = $request->get_error_messages();

				return $response;
			}
		}
		$prepare = VIALD_CLASS_Parse_Ali_Data::parse_data($attributes, $html,$skip_ship_from_check );
		if (!empty($prepare['error'])){
			$response['status'] = 'error';
			$response['code'] = $prepare['code']??'';
			$response['message'] = $prepare['message']??'';
			return $response;
		}
		if ( !empty($attributes['sku']) ) {
			$response['data'] = $attributes;
		} else {
			$response['status'] = 'error';
		}
		return $response;
	}
	public static function get_accept_currencies() {
		return [ 'RUB' ];
	}

	public static function get_user_agent() {
		$user_agent_list = get_option( 'vi_wad_user_agent_list' );
		if ( ! $user_agent_list ) {
			$user_agent_list = '["Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.1.1 Safari\/605.1.15","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.80 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (X11; Ubuntu; Linux x86_64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.14; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) HeadlessChrome\/60.0.3112.78 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; rv:60.0) Gecko\/20100101 Firefox\/60.0","Mozilla\/5.0 (Windows NT 6.1; Win64; x64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.90 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/64.0.3282.140 Safari\/537.36 Edge\/17.17134","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.131 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/64.0.3282.140 Safari\/537.36 Edge\/18.17763","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.80 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.1 Safari\/605.1.15","Mozilla\/5.0 (Windows NT 10.0; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.1.1 Safari\/605.1.15","Mozilla\/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; WOW64; Trident\/7.0; rv:11.0) like Gecko","Mozilla\/5.0 (X11; Linux x86_64; rv:60.0) Gecko\/20100101 Firefox\/60.0","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 Safari\/537.36 OPR\/60.0.3255.151","Mozilla\/5.0 (Windows NT 6.1; WOW64; Trident\/7.0; rv:11.0) like Gecko","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.80 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.13; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.80 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/62.0.3202.94 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.157 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64; rv:66.0) Gecko\/20100101 Firefox\/66.0","Mozilla\/5.0 (Windows NT 10.0; Win64; x64; rv:68.0) Gecko\/20100101 Firefox\/68.0","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/72.0.3626.109 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.90 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 Safari\/537.36 OPR\/60.0.3255.109","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 Safari\/537.36 OPR\/60.0.3255.170","Mozilla\/5.0 (Windows NT 6.3; Win64; x64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Windows NT 10.0; WOW64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (iPad; CPU OS 12_3_1 like Mac OS X) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.1.1 Mobile\/15E148 Safari\/604.1","Mozilla\/5.0 (Windows NT 6.1; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) HeadlessChrome\/60.0.3112.78 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 YaBrowser\/19.6.1.153 Yowser\/2.5 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/70.0.3538.77 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 YaBrowser\/19.4.3.370 Yowser\/2.5 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 YaBrowser\/19.6.0.1574 Yowser\/2.5 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Ubuntu Chromium\/74.0.3729.169 Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.131 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.0 Safari\/605.1.15","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_0) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.86 Safari\/537.36","Mozilla\/5.0 (Linux; U; Android 4.3; en-us; SM-N900T Build\/JSS15J) AppleWebKit\/534.30 (KHTML, like Gecko) Version\/4.0 Mobile Safari\/534.30","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.0.3 Safari\/605.1.15","Mozilla\/5.0 (Windows NT 6.1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/11.1.2 Safari\/605.1.15","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.80 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; WOW64; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/12.0.2 Safari\/605.1.15","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko\/20100101 Firefox\/45.0","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.90 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.157 Safari\/537.36","Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.90 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_14_2) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.169 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/72.0.3626.121 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.86 Safari\/537.36","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/75.0.3770.100 Safari\/537.36","Mozilla\/5.0 (Windows NT 10.0; Win64; x64; rv:60.0) Gecko\/20100101 Firefox\/60.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.12; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/13.0 Safari\/605.1.15","Mozilla\/5.0 (Windows NT 6.1; rv:67.0) Gecko\/20100101 Firefox\/67.0","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 Safari\/537.36 OPR\/60.0.3255.151","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 Safari\/537.36 OPR\/60.0.3255.170","Mozilla\/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/74.0.3729.131 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; WOW64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/73.0.3683.103 YaBrowser\/19.4.3.370 Yowser\/2.5 Safari\/537.36","Mozilla\/5.0 (Windows NT 6.1; WOW64; rv:56.0) Gecko\/20100101 Firefox\/56.0","Mozilla\/5.0 (Windows NT 6.1; WOW64; rv:56.0) Gecko\/20100101 Firefox\/56.0"]';
			update_option( 'vi_wad_user_agent_list', $user_agent_list );
		}
		$user_agent_list_array = vi_wad_json_decode( $user_agent_list );
		$return_agent          = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36';
		$last_used             = get_option( 'vi_wad_last_used_user_agent', 0 );
		if ( $last_used == count( $user_agent_list_array ) - 1 ) {
			$last_used = 0;
			shuffle( $user_agent_list_array );
			update_option( 'vi_wad_user_agent_list', wp_json_encode( $user_agent_list_array ) );
		} else {
			$last_used ++;
		}
		update_option( 'vi_wad_last_used_user_agent', $last_used );
		if ( isset( $user_agent_list_array[ $last_used ] ) && $user_agent_list_array[ $last_used ] ) {
			$return_agent = $user_agent_list_array[ $last_used ];
		}

		return $return_agent;
	}

	public static function sku_exists( $sku = '' ) {
		$sku_exists = false;
		if ( $sku ) {
			$id_from_sku = wc_get_product_id_by_sku( $sku );
			$product     = $id_from_sku ? wc_get_product( $id_from_sku ) : false;
			$sku_exists  = $product && 'importing' !== $product->get_status();
		}

		return $sku_exists;
	}

	public static function set( $name, $set_name = false ) {
		if ( is_array( $name ) ) {
			return implode( ' ', array_map( array( 'VI_WOO_ALIDROPSHIP_DATA', 'set' ), $name ) );
		} else {
			if ( $set_name ) {
				return str_replace( '-', '_', self::$prefix . $name );
			} else {
				return self::$prefix . $name;
			}
		}
	}

	public function get_default( $name = "" ) {
		if ( ! $name ) {
			return $this->default;
		} elseif ( isset( $this->default[ $name ] ) ) {
			return apply_filters( 'wooaliexpressdropship_params_default_' . $name, $this->default[ $name ] );
		} else {
			return false;
		}
	}

	/**
	 * @param $string_number
	 *
	 * @return float
	 */
	public static function string_to_float( $string_number ) {
		return floatval( str_replace( ',', '', $string_number ) );
	}

	public function process_exchange_price( $price ) {
		if ( ! $price ) {
			return $price;
		}
		$rate = floatval( $this->get_params( 'import_currency_rate' ) );
		if ( $rate ) {
			$price = $price * $rate;
		}
		if ( $this->get_params( 'format_price_rules_enable' ) ) {
			self::format_price( $price );
		}

		return round( $price, wc_get_price_decimals() );
	}

	protected static function calculate_price_base_on_type( $price, $value, $type ) {
		$match_value = floatval( $value );
		switch ( $type ) {
			case 'fixed':
				$price = $price + $match_value;
				break;
			case 'percent':
				$price = $price * ( 1 + $match_value / 100 );
				break;
			case 'multiply':
				$price = $price * $match_value;
				break;
			default:
				$price = $match_value;
		}

		return $price;
	}

	/**
	 * @param $price
	 * @param bool $is_sale_price
	 *
	 * @return float|int
	 */
	public function process_price( $price, $is_sale_price = false ) {
		if ( ! $price ) {
			return $price;
		}
		$original_price  = $price;
		$price_default   = $this->get_params( 'price_default' );
		$price_from      = $this->get_params( 'price_from' );
		$price_to        = $this->get_params( 'price_to' );
		$plus_value_type = $this->get_params( 'plus_value_type' );

		if ( $is_sale_price ) {
			$plus_sale_value = $this->get_params( 'plus_sale_value' );
			$level_count     = count( $price_from );
			if ( $level_count > 0 ) {
				/*adjust price rules since version 1.0.1.1*/
				if ( ! is_array( $price_to ) || count( $price_to ) !== $level_count ) {
					if ( $level_count > 1 ) {
						$price_to   = array_values( array_slice( $price_from, 1 ) );
						$price_to[] = '';
					} else {
						$price_to = array( '' );
					}
				}
				$match = false;
				for ( $i = 0; $i < $level_count; $i ++ ) {
					if ( $price >= $price_from[ $i ] && ( $price_to[ $i ] === '' || $price <= $price_to[ $i ] ) ) {
						$match = $i;
						break;
					}
				}
				if ( $match !== false ) {
					if ( $plus_sale_value[ $match ] < 0 ) {
						$price = 0;
					} else {
						$price = self::calculate_price_base_on_type( $price, $plus_sale_value[ $match ], $plus_value_type[ $match ] );
					}
				} else {
					$plus_sale_value_default = isset( $price_default['plus_sale_value'] ) ? $price_default['plus_sale_value'] : 1;
					if ( $plus_sale_value_default < 0 ) {
						$price = 0;
					} else {
						$price = self::calculate_price_base_on_type( $price, $plus_sale_value_default, isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply' );
					}
				}
			}
		} else {
			$plus_value  = $this->get_params( 'plus_value' );
			$level_count = count( $price_from );
			if ( $level_count > 0 ) {
				/*adjust price rules since version 1.0.1.1*/
				if ( ! is_array( $price_to ) || count( $price_to ) !== $level_count ) {
					if ( $level_count > 1 ) {
						$price_to   = array_values( array_slice( $price_from, 1 ) );
						$price_to[] = '';
					} else {
						$price_to = array( '' );
					}
				}
				$match = false;
				for ( $i = 0; $i < $level_count; $i ++ ) {
					if ( $price >= $price_from[ $i ] && ( $price_to[ $i ] === '' || $price <= $price_to[ $i ] ) ) {
						$match = $i;
						break;
					}
				}
				if ( $match !== false ) {
					$price = self::calculate_price_base_on_type( $price, $plus_value[ $match ], $plus_value_type[ $match ] );
				} else {
					$price = self::calculate_price_base_on_type( $price, isset( $price_default['plus_value'] ) ? $price_default['plus_value'] : 2, isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply' );
				}
			}
		}

		return apply_filters( 'vi_wad_processed_price', $price, $is_sale_price, $original_price );
	}

	public static function format_price( &$price ) {
		$applied = array();
		if ( $price ) {
			$instance = self::get_instance();
			$rules    = $instance->get_params( 'format_price_rules' );
			if ( is_array( $rules ) && count( $rules ) ) {
				$decimals        = wc_get_price_decimals();
				$price           = self::string_to_float( $price );
				$int_part        = intval( $price );
				$decimal_part    = number_format( $price - $int_part, $decimals );
				$int_part_length = strlen( $int_part );
				if ( $decimals > 0 ) {
					foreach ( $rules as $key => $rule ) {
						if ( $rule['part'] === 'fraction' ) {
							if ( ( ! $rule['from'] && ! $rule['to'] ) || ( $price >= $rule['from'] && $price <= $rule['to'] ) || ( ! $rule['from'] && $price <= $rule['to'] ) || ( ! $rule['to'] && $price >= $rule['from'] ) ) {
								$compare_value = $decimal_part;
								$string        = substr( strval( $decimal_part ), 2 );
								if ( ( $rule['value_from'] === '' && $rule['value_to'] === '' ) || ( $compare_value >= self::string_to_float( ".{$rule['value_from']}" ) && $compare_value <= self::string_to_float( ".{$rule['value_to']}" ) ) || ( $rule['value_from'] === '' && $compare_value <= self::string_to_float( ".{$rule['value_to']}" ) ) || ( $rule['value_to'] === '' && $compare_value >= self::string_to_float( ".{$rule['value_from']}" ) ) ) {
									while ( ( $pos = strpos( $rule['value'], 'x' ) ) !== false ) {
										$replace = 'y';
										if ( $pos < strlen( $string ) ) {
											$replace = substr( $string, $pos, 1 );
										}
										$rule['value'] = substr_replace( $rule['value'], $replace, $pos, 1 );
									}
									$price        = $int_part + self::string_to_float( ".{$rule['value']}" );
									$decimal_part = $price - $int_part;
									$applied[]    = $key;
									break;
								}
							}
						}
					}
				}
				foreach ( $rules as $key => $rule ) {
					if ( $rule['part'] === 'integer' ) {
						if ( $price >= $rule['from'] && $price <= $rule['to'] ) {
							if ( $rule['value_from'] === '' && $rule['value_to'] === '' ) {
								$max = min( $int_part_length - 1, strlen( $rule['value'] ) );
								if ( $max > 0 ) {
									$compare_value = intval( substr( $int_part, $int_part_length - $max ) );
									$string        = strval( zeroise( $compare_value, $max ) );
									$rule['value'] = zeroise( $rule['value'], $max );
									while ( ( $pos = strpos( $rule['value'], 'x' ) ) !== false ) {
										$replace = 'y';
										if ( $pos < strlen( $string ) ) {
											$replace = substr( $string, $pos, 1 );
										}
										$rule['value'] = substr_replace( $rule['value'], $replace, $pos, 1 );
									}
									$price     = $int_part - $compare_value + intval( $rule['value'] ) + $decimal_part;
									$applied[] = $key;
									break;
								}
							} else {
								$max = min( $int_part_length, max( strlen( $rule['value_from'] ), strlen( $rule['value_to'] ), strlen( $rule['value'] ) ) );
								if ( $max > 0 ) {
									$compare_value = intval( substr( $int_part, $int_part_length - $max ) );
									$string        = strval( zeroise( $compare_value, $max ) );
									$rule['value'] = zeroise( $rule['value'], $max );
									if ( ( $compare_value >= intval( $rule['value_from'] ) && $compare_value <= intval( $rule['value_to'] ) ) ) {
										while ( ( $pos = strpos( $rule['value'], 'x' ) ) !== false ) {
											$replace = 'y';
											if ( $pos < strlen( $string ) ) {
												$replace = substr( $string, $pos, 1 );
											}
											$rule['value'] = substr_replace( $rule['value'], $replace, $pos, 1 );
										}
										$price     = $int_part - $compare_value + intval( $rule['value'] ) + $decimal_part;
										$applied[] = $key;
										break;
									}
								}
							}
						}
					}
				}
			}
		}

		return $applied;
	}

	public static function process_variation_sku( $sku, $variation_ids ) {
		$return = '';
		if ( is_array( $variation_ids ) && count( $variation_ids ) ) {
			foreach ( $variation_ids as $key => $value ) {
				$variation_ids[ $key ] = wc_sanitize_taxonomy_name( $value );
			}
			$return = $sku . '-' . implode( '-', $variation_ids );
		}

		return $return;
	}

	public static function download_description( $product_id, $description_url, $description, $product_description ) {
		if ( $description_url && $product_id ) {
			$request = wp_remote_get(
				$description_url,
				array(
					'user-agent' => self::get_user_agent(),
					'timeout'    => 3,
				)
			);
			if ( ! is_wp_error( $request ) && get_post_type( $product_id ) === 'vi_wad_draft_product' ) {
				if ( isset( $request['body'] ) && $request['body'] ) {
					$body = preg_replace( '/<script\>[\s\S]*?<\/script>/im', '', $request['body'] );
					preg_match_all( '/src="([\s\S]*?)"/im', $body, $matches );
					if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
						Ali_Product_Table::update_post_meta( $product_id, '_vi_wad_description_images', array_values( array_unique( $matches[1] ) ) );
					}
					$instance    = self::get_instance();
					$str_replace = $instance->get_params( 'string_replace' );
					if ( isset( $str_replace['to_string'] ) && is_array( $str_replace['to_string'] ) && $str_replace_count = count( $str_replace['to_string'] ) ) {
						for ( $i = 0; $i < $str_replace_count; $i ++ ) {
							if ( $str_replace['sensitive'][ $i ] ) {
								$body = str_replace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $body );
							} else {
								$body = str_ireplace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $body );
							}
						}

					}
					if ( $product_description === 'item_specifics_and_description' || $product_description === 'description' ) {
						$description .= $body;
						Ali_Product_Table::wp_update_post( array( 'ID' => $product_id, 'post_content' => $description ) );
					}
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	public static function get_disable_wp_cron() {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON === true;
	}

	/**Download image from url
	 *
	 * @param $image_id
	 * @param $url
	 * @param int $post_parent
	 * @param array $exclude
	 * @param string $post_title
	 * @param null $desc
	 *
	 * @return array|bool|int|object|string|WP_Error|null
	 */
	public static function download_image( &$image_id, $url, $post_parent = 0, $exclude = array(), $post_title = '', $desc = null ) {
		global $wpdb;
		$instance = self::get_instance();
		if ( $instance->get_params( 'use_external_image' ) && class_exists( 'EXMAGE_WP_IMAGE_LINKS' ) ) {
			$external_image = EXMAGE_WP_IMAGE_LINKS::add_image( $url, $image_id, $post_parent );
			$thumb_id       = $external_image['id'] ? $external_image['id'] : new WP_Error( 'exmage_image_error', $external_image['message'] );
		} else {
			$new_url   = $url;
			$parse_url = wp_parse_url( $new_url );
			$scheme    = empty( $parse_url['scheme'] ) ? 'http' : $parse_url['scheme'];
			$image_id  = "{$parse_url['host']}{$parse_url['path']}";
			$new_url   = "{$scheme}://{$image_id}";
			preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG|webp|WEBP)/', $new_url, $matches );
			if ( ! is_array( $matches ) || ! count( $matches ) ) {
				preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG|webp|WEBP)/', $url, $matches );
				if ( is_array( $matches ) && count( $matches ) ) {
					$new_url  .= "?{$matches[0]}";
					$image_id .= "?{$matches[0]}";
				} elseif ( ! empty( $parse_url['query'] ) ) {
					$new_url .= '?' . $parse_url['query'];
				}
			} elseif ( ! empty( $parse_url['query'] ) ) {
				$new_url .= '?' . $parse_url['query'];
			}

			$thumb_id = self::get_id_by_image_id( $image_id );
			if ( ! $thumb_id ) {
				$thumb_id = vi_wad_upload_image( $new_url, $post_parent, $exclude, $post_title, $desc );
				if ( ! is_wp_error( $thumb_id ) ) {
					update_post_meta( $thumb_id, '_vi_wad_image_id', $image_id );
				}
			} elseif ( $post_parent ) {
				$table_postmeta = "{$wpdb->prefix}posts";
				$wpdb->query( $wpdb->prepare( "UPDATE {$table_postmeta} set post_parent=%s WHERE ID=%s AND post_parent = 0 LIMIT 1", array( // phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$post_parent,
					$thumb_id
				) ) );
			}
		}

		return $thumb_id;
	}

	/**
	 * @param $image_id
	 * @param bool $count
	 * @param bool $multiple
	 *
	 * @return array|bool|object|string|null
	 */
	public static function get_id_by_image_id( $image_id, $count = false, $multiple = false ) {
		global $wpdb;
		if ( $image_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			$post_type      = 'attachment';
			$meta_key       = "_vi_wad_image_id";
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $image_id ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $image_id ), ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$query   .= ' LIMIT 1';
					$results = $wpdb->get_var( $wpdb->prepare( $query, $image_id ), 1 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function count_posts( $status ) {
		$args_publish = array(
			'post_type'      => 'vi_wad_draft_product',
			'post_status'    => $status,
			'order'          => 'DESC',
			'meta_key'       => '_vi_wad_woo_id',// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'posts_per_page' => - 1,
		);
//		$the_query    = new WP_Query( $args_publish );
		$the_query = VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? new Ali_Product_Query( $args_publish ) : new WP_Query( $args_publish );

		$total = isset( $the_query->post_count ) ? $the_query->post_count : 0;
		wp_reset_postdata();

		return $total;
	}

	/**Get available shipping company
	 *
	 * @param string $slug
	 *
	 * @return array|mixed|string
	 */
	public static function get_shipping_companies( $slug = '' ) {
		$shipping_companies = apply_filters( 'vi_wad_aliexpress_shipping_companies', array(
			'AE_CAINIAO_STANDARD'      => "Cainiao Expedited Standard",
			'AE_CN_SUPER_ECONOMY_G'    => "Cainiao Super Economy Global",
			'ARAMEX'                   => "ARAMEX",
			'CAINIAO_CONSOLIDATION_SA' => "AliExpress Direct(SA)",
			'CAINIAO_CONSOLIDATION_AE' => "AliExpress Direct(AE)",
			'CAINIAO_ECONOMY'          => "AliExpress Saver Shipping",
			'CAINIAO_PREMIUM'          => "AliExpress Premium Shipping",
			'CAINIAO_STANDARD'         => "AliExpress Standard Shipping",
			'CHP'                      => "Swiss Post",
			'CPAM'                     => "China Post Registered Air Mail",
			'DHL'                      => "DHL",
			'DHLECOM'                  => "DHL e-commerce",
			'EMS'                      => "EMS",
			'EMS_ZX_ZX_US'             => "ePacket",
			'E_EMS'                    => "e-EMS",
			'FEDEX'                    => "Fedex IP",
			'FEDEX_IE'                 => "Fedex IE",
			'GATI'                     => "GATI",
			'POST_NL'                  => "PostNL",
			'PTT'                      => "Turkey Post",
			'SF'                       => "SF Express",
			'SF_EPARCEL'               => "SF eParcel",
			'SGP'                      => "Singapore Post",
			'SUNYOU_ECONOMY'           => "SunYou Economic Air Mail",
			'TNT'                      => "TNT",
			'TOLL'                     => "DPEX",
			'UBI'                      => "UBI",
			'UPS'                      => "UPS Express Saver",
			'UPSE'                     => "UPS Expedited",
			'USPS'                     => "USPS",
			'YANWEN_AM'                => "Yanwen Special Line-YW",
			'YANWEN_ECONOMY'           => "Yanwen Economic Air Mail",
			'YANWEN_JYT'               => "China Post Ordinary Small Packet Plus",
			'POLANDPOST_PL'            => "Poland Post",
			'Other'                    => "Seller's Shipping Method",
		) );
		if ( $slug ) {
			return isset( $shipping_companies[ $slug ] ) ? $shipping_companies[ $slug ] : '';
		} else {
			return $shipping_companies;
		}
	}

	public static function wp_remote_get( $url, $args = array() ) {
		$return  = array(
			'status' => 'error',
			'data'   => '',
			'code'   => '',
		);
		$args    = array_merge( array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'timeout'    => 3,
			)
			, $args );
		$request = wp_remote_get(
			$url, $args
		);
		if ( is_wp_error( $request ) ) {
			$return['data'] = $request->get_error_message();
			$return['code'] = $request->get_error_code();
		} else {
			$return['code'] = wp_remote_retrieve_response_code( $request );
			if ( $return['code'] === 200 ) {
				$return['status'] = 'success';
				$return['data']   = json_decode( $request['body'], true );
			}
		}

		return $return;
	}

	public static function sanitize_taxonomy_name( $name ) {
		return urldecode( function_exists( 'mb_strtolower' ) ? mb_strtolower( urlencode( wc_sanitize_taxonomy_name( $name ) ) ) : strtolower( urlencode( wc_sanitize_taxonomy_name( $name ) ) ) );
	}

	public static function get_aliexpress_product_url( $sku ) {
		return "https://www.aliexpress.com/item/{$sku}.html";
	}

	/**Get WooCommerce countries in English
	 * @return mixed
	 */
	public static function get_countries() {
		if ( self::$countries === null ) {
			unload_textdomain( 'woocommerce' );
			self::$countries = apply_filters( 'woocommerce_countries', include WC()->plugin_path() . '/i18n/countries.php' );
			if ( apply_filters( 'woocommerce_sort_countries', true ) ) {
				wc_asort_by_locale( self::$countries );
			}
			$locale = determine_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'woocommerce' );
			load_textdomain( 'woocommerce', WP_LANG_DIR . '/woocommerce/woocommerce-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce', false, plugin_basename( dirname( WC_PLUGIN_FILE ) ) . '/i18n/languages' );
		}

		return self::$countries;
	}

	/**Get WooCommerce states in English
	 *
	 * @param $cc
	 *
	 * @return bool|mixed
	 */
	public static function get_states( $cc ) {
		if ( self::$states === null ) {
			unload_textdomain( 'woocommerce' );
			self::$states = apply_filters( 'woocommerce_states', include WC()->plugin_path() . '/i18n/states.php' );
			$locale       = determine_locale();
			$locale       = apply_filters( 'plugin_locale', $locale, 'woocommerce' );
			load_textdomain( 'woocommerce', WP_LANG_DIR . '/woocommerce/woocommerce-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce', false, plugin_basename( dirname( WC_PLUGIN_FILE ) ) . '/i18n/languages' );
		}

		if ( ! is_null( $cc ) ) {
			return isset( self::$states[ $cc ] ) ? self::$states[ $cc ] : false;
		} else {
			return self::$states;
		}
	}

	/**Allows only numbers
	 *
	 * @param $phone
	 *
	 * @return string
	 */
	public static function sanitize_phone_number( $phone ) {
		return preg_replace( '/[^\d]/', '', $phone );
	}

	/**
	 * Get list of states/cities of a country to use when fulfilling AliExpress orders
	 *
	 * @param $cc
	 *
	 * @return mixed
	 */
	public static function get_state( $cc ) {
		if ( ! isset( self::$ali_states[ $cc ] ) ) {
			$states      = array();
			$states_file = VI_WOO_ALIDROPSHIP_PACKAGES . 'ali-states' . DIRECTORY_SEPARATOR . "$cc-states.json";
			if ( is_file( $states_file ) ) {
				ini_set( 'memory_limit', - 1 );
				$states = vi_wad_json_decode( file_get_contents( $states_file ) );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
			self::$ali_states[ $cc ] = $states;
		}

		return self::$ali_states[ $cc ];
	}

	/**
	 * @param $content
	 *
	 * @return mixed
	 */
	private function find_and_replace_strings( $content ) {
		$str_replace = $this->get_params( 'string_replace' );
		if ( isset( $str_replace['to_string'] ) && is_array( $str_replace['to_string'] ) && $str_replace_count = count( $str_replace['to_string'] ) ) {
			for ( $i = 0; $i < $str_replace_count; $i ++ ) {
				if ( $str_replace['sensitive'][ $i ] ) {
					$content = str_replace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $content );
				} else {
					$content = str_ireplace( $str_replace['from_string'][ $i ], $str_replace['to_string'][ $i ], $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Create ALD products(added to import list): Import via chrome extension, reimport, override
	 *
	 * @param $data
	 * @param $shipping_info
	 * @param array $post_data
	 *
	 * @return int|WP_Error
	 */
	public function create_product( $data, $shipping_info, $post_data = array() ) {
		$sku                 = isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '';
		$title               = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$description_url     = isset( $data['description_url'] ) ? stripslashes( $data['description_url'] ) : '';
		$short_description   = isset( $data['short_description'] ) ? wp_kses_post( stripslashes( $data['short_description'] ) ) : '';
		$description         = isset( $data['description'] ) ? wp_kses_post( stripslashes( $data['description'] ) ) : '';
		$specsModule         = isset( $data['specsModule'] ) ? stripslashes_deep( $data['specsModule'] ) : array();
		$gallery             = isset( $data['gallery'] ) ? stripslashes_deep( $data['gallery'] ) : array();
		$variation_images    = isset( $data['variation_images'] ) ? stripslashes_deep( $data['variation_images'] ) : array();
		$variations          = isset( $data['variations'] ) ? stripslashes_deep( $data['variations'] ) : array();
		$attributes          = isset( $data['attributes'] ) ? stripslashes_deep( $data['attributes'] ) : array();
		$list_attributes     = isset( $data['list_attributes'] ) ? stripslashes_deep( $data['list_attributes'] ) : array();
		$store_info          = isset( $data['store_info'] ) ? stripslashes_deep( $data['store_info'] ) : array();
		$currency_code       = isset( $data['currency_code'] ) ? strtoupper( stripslashes_deep( $data['currency_code'] ) ) : '';
		$description_setting = $this->get_params( 'product_description' );
		$specsModule         = apply_filters( 'vi_wad_import_product_specifications', $specsModule, $data );

		if ( count( $specsModule ) ) {
			ob_start();
			?>
			<div class="product-specs-list-container">
				<ul class="product-specs-list util-clearfix">
					<?php
					foreach ( $specsModule as $specs ) {
						?>
						<li class="product-prop line-limit-length"><span
									class="property-title"><?php echo esc_html( isset( $specs['attrName'] ) ? $specs['attrName'] : $specs['title'] ) ?>:&nbsp;</span><span
									class="property-desc line-limit-length"><?php echo esc_html( isset( $specs['attrValue'] ) ? $specs['attrValue'] : $specs['value'] ) ?></span>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
			<?php
			$short_description .= ob_get_clean();
			$short_description = apply_filters( 'vi_wad_import_product_short_description', $short_description, $data );
		}

		switch ( $description_setting ) {
			case 'none':
				$description = '';
				break;
			case 'item_specifics':
				$description = $short_description;
				break;
			case 'description':
				if ( $description_url ) {
					$description .= self::get_product_description_from_url( $description_url );
				}
				break;
			case 'item_specifics_and_description':
			default:
				if ( $description_url ) {
					$description .= self::get_product_description_from_url( $description_url );
				}
				$description = $short_description . $description;
		}

		$original_desc_images = array();
		if ( $description ) {
			/*Search for images before applying find and replace rules to remember original image urls*/
			preg_match_all( '/src="([\s\S]*?)"/im', $description, $matches );
			if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
				$original_desc_images = array_values( array_unique( $matches[1] ) );
			}
		}

		$description = $this->find_and_replace_strings( $description );
		if ( $description ) {
			/*In case image urls(in description) are affected, replace affected urls with their original ones*/
			preg_match_all( '/src="([\s\S]*?)"/im', $description, $matches );
			if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
				$desc_images       = array_values( array_unique( $matches[1] ) );
				$desc_images_count = count( $desc_images );
				if ( $desc_images_count === count( $original_desc_images ) && $desc_images_count !== count( array_intersect( $desc_images, $original_desc_images ) ) ) {
					$description = str_replace( $desc_images, $original_desc_images, $description );
				}
			}
		}

		$description = apply_filters( 'vi_wad_import_product_description', $description, $data );

		$title   = $this->find_and_replace_strings( $title );
		$post_id = Ali_Product_Table::wp_insert_post( array_merge( array(
			'post_title'   => $title,
			'post_type'    => 'vi_wad_draft_product',
			'post_status'  => 'draft',
			'post_excerpt' => '',
			'post_content' => $description,
		), $post_data ), true );
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			if ( count( $original_desc_images ) ) {
				Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_description_images', $original_desc_images );
			}
			Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_sku', $sku );
			Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_attributes', $attributes );
			Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_list_attributes', $list_attributes );
			if ( $shipping_info['freight'] ) {
				Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_shipping_info', $shipping_info );
			}
			if ( isset( $shipping_info['freight_ext'] ) ) {
				$freight_ext = json_decode( $shipping_info['freight_ext'], true );
				Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_shipping_freight_ext', $freight_ext );
			}
			$gallery = array_unique( array_filter( $gallery ) );
			if ( count( $gallery ) ) {
				Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_gallery', $gallery );
			}
			Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_variation_images', $variation_images );
			if ( is_array( $store_info ) && count( $store_info ) ) {
				Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_store_info', $store_info );
			}
			if ( count( $variations ) ) {
				$variations_news      = array();
				$woocommerce_currency = get_option( 'woocommerce_currency' );
				$rate                 = 0;
				if ( $woocommerce_currency === $currency_code ) {
					if ( $woocommerce_currency === 'RUB' ) {//temporarily restrict to RUB
						$import_currency_rate = $this->get_params( 'import_currency_rate' );
						if ( $import_currency_rate ) {
							$rate = 1 / $import_currency_rate;
						}
					}
				} elseif ( in_array( $currency_code, array( 'RUB', ), true ) ) { //'CNY'
					$rate = $this->get_params( "import_currency_rate_{$currency_code}" );
				}

				foreach ( $variations as $key => $variation ) {
					$variations_new            = array();
					$variations_new['image']   = $variation['image'];
					$variations_new['sku']     = self::process_variation_sku( $sku, $variation['variation_ids'] );
					$variations_new['sku_sub'] = self::process_variation_sku( $sku, $variation['variation_ids_sub'] );
					$variations_new['skuId']   = $variation['skuId'];
					$variations_new['skuAttr'] = $variation['skuAttr'];
					$skuVal                    = isset( $variation['skuVal'] ) ? $variation['skuVal'] : array();
					if ( $currency_code === 'USD' && isset( $skuVal['skuMultiCurrencyCalPrice'] ) ) {
						$variations_new['regular_price'] = $skuVal['skuMultiCurrencyCalPrice'];
						$variations_new['sale_price']    = isset( $skuVal['actSkuMultiCurrencyCalPrice'] ) ? $skuVal['actSkuMultiCurrencyCalPrice'] : '';
						if ( isset( $skuVal['actSkuMultiCurrencyBulkPrice'] ) && self::string_to_float( $skuVal['actSkuMultiCurrencyBulkPrice'] ) > self::string_to_float( $variations_new['sale_price'] ) ) {
							$variations_new['sale_price'] = $skuVal['actSkuMultiCurrencyBulkPrice'];
						}
					} else {
						/*maybe convert to USD if data currency is not USD but the store currency*/
						$variations_new['regular_price'] = isset( $skuVal['skuCalPrice'] ) ? $skuVal['skuCalPrice'] : '';
						$variations_new['sale_price']    = ( isset( $skuVal['actSkuCalPrice'], $skuVal['actSkuBulkCalPrice'] ) && self::string_to_float( $skuVal['actSkuBulkCalPrice'] ) > self::string_to_float( $skuVal['actSkuCalPrice'] ) ) ? $skuVal['actSkuBulkCalPrice'] : ( isset( $skuVal['actSkuCalPrice'] ) ? $skuVal['actSkuCalPrice'] : '' );
						if ( ( $currency_code === $woocommerce_currency || in_array( $currency_code, array( 'RUB', 'CNY' ), true ) ) && $rate ) {
							if ( $variations_new['regular_price'] ) {
								$variations_new['regular_price'] = $rate * $variations_new['regular_price'];
							}
							if ( $variations_new['sale_price'] ) {
								$variations_new['sale_price'] = $rate * $variations_new['sale_price'];
							}
						}
						if ( isset( $skuVal['skuAmount']['currency'], $skuVal['skuAmount']['value'] ) && $skuVal['skuAmount']['value'] ) {
							if ( $skuVal['skuAmount']['currency'] === 'USD' ) {
								$variations_new['regular_price'] = $skuVal['skuAmount']['value'];
								if ( isset( $skuVal['skuActivityAmount']['currency'], $skuVal['skuActivityAmount']['value'] ) && $skuVal['skuActivityAmount']['currency'] === 'USD' && $skuVal['skuActivityAmount']['value'] ) {
									$variations_new['sale_price'] = $skuVal['skuActivityAmount']['value'];
								}
							} elseif ( ( $skuVal['skuAmount']['currency'] === $woocommerce_currency || in_array( $skuVal['skuAmount']['currency'], array( 'RUB', 'CNY' ), true ) ) && $rate ) {
								$variations_new['regular_price'] = $rate * $skuVal['skuAmount']['value'];
								if ( isset( $skuVal['skuActivityAmount']['currency'], $skuVal['skuActivityAmount']['value'] ) && $skuVal['skuActivityAmount']['currency'] === $woocommerce_currency && $skuVal['skuActivityAmount']['value'] ) {
									$variations_new['sale_price'] = $rate * $skuVal['skuActivityAmount']['value'];
								}
							}
						}
					}
					$variations_new['stock']          = isset( $skuVal['availQuantity'] ) ? absint( $skuVal['availQuantity'] ) : 0;
					$variations_new['attributes']     = isset( $variation['variation_ids'] ) ? $variation['variation_ids'] : array();
					$variations_new['attributes_sub'] = isset( $variation['variation_ids_sub'] ) ? $variation['variation_ids_sub'] : array();
					$variations_new['ship_from']      = isset( $variation['ship_from'] ) ? $variation['ship_from'] : '';
					$variations_news[]                = $variations_new;
				}
				Ali_Product_Table::update_post_meta( $post_id, '_vi_wad_variations', $variations_news );
			}
		}

		return $post_id;
	}

	private static function get_product_description_from_url( $description_url ) {
		$request     = wp_remote_get(
			$description_url,
			array(
				'user-agent' => self::get_user_agent(),
				'timeout'    => 10,
			)
		);
		$description = '';

		$response_code = wp_remote_retrieve_response_code( $request );

		if ( ! is_wp_error( $request ) && $response_code !== 404 ) {
			if ( isset( $request['body'] ) && $request['body'] ) {
				$body        = preg_replace( '/<script\>[\s\S]*?<\/script>/im', '', $request['body'] );
				$description = $body;
			}
		}

		return $description;
	}

	public static function get_get_tracking_url( $aliexpress_order_id = '' ) {
		return add_query_arg( array(
			'fromDomain'          => urlencode( site_url() ),
			'tradeId'             => $aliexpress_order_id,
			'getTracking'         => 'manual',
			'redirectOrderStatus' => 'all',
		), 'https://www.aliexpress.com/p/order/index.html' );
	}

	public static function allow_html( ) {
		if ( self::$allow_html !== null ) {
			return self::$allow_html;
		}
		$tags = array_merge_recursive( wp_kses_allowed_html( 'post' ), array(
				'input'  => array(
					'type'         => 1,
					'id'           => 1,
					'name'         => 1,
					'class'        => 1,
					'placeholder'  => 1,
					'autocomplete' => 1,
					'style'        => 1,
					'value'        => 1,
					'size'         => 1,
					'checked'      => 1,
					'disabled'     => 1,
					'readonly'     => 1,
					'data-*'       => 1,
				),
				'form'   => array(
					'method' => 1,
					'id'     => 1,
					'class'  => 1,
					'action' => 1,
					'data-*' => 1,
				),
				'select' => array(
					'id'       => 1,
					'name'     => 1,
					'class'    => 1,
					'multiple' => 1,
					'data-*'   => 1,
				),
				'option' => array(
					'class'    => 1,
					'value'    => 1,
					'selected' => 1,
					'data-*'   => 1,
				),
			)
		);
		foreach ( $tags as $key => $value ) {
			if ( in_array( $key, array( 'div', 'span', 'a', 'form', 'select', 'option', 'tr', 'td' ) ) ) {
				$tags[ $key ]['data-*'] = 1;
			}
		}

		return self::$allow_html = $tags;
	}
	public static function wp_kses_post( $content ) {
		return wp_kses( $content, self::allow_html() );
	}

	/**
	 * @param $freight
	 *
	 * @return array
	 */
	public static function adjust_ali_freight( $freight, $from = '') {
		if ( empty( $freight ) ||  !is_array( $freight ) ) {
            return [];
		}
		$saved_freight = array();
		switch ($from){
			case 'api':
				foreach ( $freight as $freight_v ) {
					if ( empty( $freight_v ) || empty( $freight_v['company'] ) ) {
						continue;
					}
					$saved_freight[] = array(
						'company'       => $freight_v['code']??'',
						'company_name'  => $freight_v['company']??'',
						'shipping_cost' => self::get_freight_amount( $freight_v ),
						'delivery_time' => ($freight_v['min_delivery_days']??'').'-'.($freight_v['max_delivery_days']??''),
						'display_type'  => $freight_v['displayType'] ?? '',
						'tracking'      => $freight_v['tracking'] ?? '',
						'ship_from'     => $freight_v['ship_from_country'] ?? '',
					);
				}
				break;
			default:
				if ($from ==='api_ru'){
					foreach ( $freight as &$method ) {
						$method['company']       = $method['groupName'] . ' ' . $method['dateFormat'];
						$method['freightAmount'] = $method['amount'];
						$method['time']          = $method['dateEstimated'];
					}
				}elseif (!isset($freight[0]['serviceName'])) {
                    $tmp = $freight;
	                $freight = [];
	                foreach ( $tmp as $f ) {
		                if ( empty( $f['bizData'] ) ) {
			                continue;
		                }
		                $bizdata = $f['bizData'];
		                if ( ! empty( $bizdata['unreachable'] ) ) {
			                continue;
		                }
		                $delivery_time = [];
		                if ( isset( $bizdata['deliveryDayMin'] ) ) {
			                $delivery_time[] = $bizdata['deliveryDayMin'];
		                }
		                if ( isset( $bizdata['deliveryDayMax'] ) ) {
			                $delivery_time[] = $bizdata['deliveryDayMax'];
		                }
		                $freight[] = [
			                'serviceName'      => $bizdata['deliveryOptionCode'] ?? '',
			                'time'             => implode( '-', $delivery_time ),
			                'company'          => $bizdata['company'] ?? $bizdata['deliveryOptionCode'] ?? '',
			                'freightAmount'    => [
				                'formatedAmount' => '',
				                'currency'       => $bizdata['displayCurrency'] ?? $bizdata['currency'],
				                'value'          => $bizdata['displayAmount'] ?? 0,
			                ],
			                'sendGoodsCountry' => $bizdata['shipFromCode'] ?? 'CN'
		                ];

	                }
                }
				foreach ( $freight as $freight_k => $freight_v ) {
					if ( empty( $freight_v ) || empty( $freight_v['company'] ) ) {
						continue;
					}
					$saved_freight[] = array(
						'company'       => $freight_v['serviceName'],
						'company_name'  => $freight_v['company'],
						'shipping_cost' => self::get_freight_amount( $freight_v ),
						'delivery_time' => $freight_v['time'],
						'display_type'  => $freight_v['displayType'] ?? '',
						'tracking'      => $freight_v['tracking'] ?? '',
						'ship_from'     => isset( $freight_v['sendGoodsCountry'] ) ? $freight_v['sendGoodsCountry'] : '',
					);
				}
		}

		return $saved_freight;
	}

	/**
	 * @param $time
	 *
	 * @return int
	 */
	public static function get_shipping_cache_time( $time ) {
		return $time + wp_rand( 0, 600 );
	}

	/**
	 * Check if shipping cost is available in USD
	 *
	 * @param $freight_v
	 *
	 * @return mixed|string
	 */
	public static function get_freight_amount( $freight_v ) {
		if (!empty($freight_v['free_shipping'])){
			return  0;
		}
		$freight_amount = $currency = '';
		if ( isset( $freight_v['standardFreightAmount']['value'], $freight_v['standardFreightAmount']['currency'] ) && $freight_v['standardFreightAmount']['currency'] === 'USD' ) {
			$freight_amount = $freight_v['standardFreightAmount']['value'];
		} elseif ( isset( $freight_v['freightAmount']['value'], $freight_v['freightAmount']['currency'] ) && $freight_v['freightAmount']['currency'] === 'USD' ) {
			$freight_amount = $freight_v['freightAmount']['value'];
			$currency       = $freight_v['freightAmount']['currency'];
		} elseif ( isset( $freight_v['previewFreightAmount']['value'], $freight_v['previewFreightAmount']['currency'] ) && $freight_v['previewFreightAmount']['currency'] === 'USD' ) {
			$freight_amount = $freight_v['previewFreightAmount']['value'];
			$currency       = $freight_v['freightAmount']['currency'];
		}
		if ( $freight_amount === '' ) {
			if ( isset( $freight_v['standardFreightAmount']['value'], $freight_v['standardFreightAmount']['currency'] ) ) {
				$freight_amount = $freight_v['standardFreightAmount']['value'];
				$currency       = $freight_v['standardFreightAmount']['currency'];
			} elseif ( isset( $freight_v['freightAmount']['value'], $freight_v['freightAmount']['currency'] ) ) {
				$freight_amount = $freight_v['freightAmount']['value'];
				$currency       = $freight_v['freightAmount']['currency'];
			} elseif ( isset( $freight_v['previewFreightAmount']['value'], $freight_v['previewFreightAmount']['currency'] ) ) {
				$freight_amount = $freight_v['previewFreightAmount']['value'];
				$currency       = $freight_v['previewFreightAmount']['currency'];
			}
		}
		if ($freight_amount === '' && isset( $freight_v['shipping_fee_currency'], $freight_v['shipping_fee_cent'] ) ){
			$freight_amount = $freight_v['shipping_fee_cent'];
		}
		if ( $freight_amount && $currency && $currency !== 'USD' ) {
			$instance               = self::get_instance();
			$woocommerce_currency = get_option( 'woocommerce_currency' );
			$rate                 = 0;
			if ( $woocommerce_currency === $currency ) {
				$rate = $instance->get_params( 'import_currency_rate' );
			} elseif ( in_array( $currency, array( 'RUB', ), true ) ) {
				$rate = $instance->get_params( "import_currency_rate_{$currency}" );
			}
			if ( $rate ) {
				$freight_amount = $freight_amount * $rate;
				$freight_amount = round( $freight_amount, 2 );
			}
		}
		return $freight_amount;
	}

	public static function chrome_extension_buttons() {
		?>
		<span class="vi-ui positive button labeled icon <?php echo esc_attr( self::set( array( 'connect-chrome-extension', 'hidden' ) ) ) ?>"
		      data-site_url="<?php echo esc_url( site_url() ) ?>">
            <i class="linkify icon"> </i><?php esc_html_e( 'Connect the Extension', 'woo-alidropship' ) ?>
        </span>
		<a target="_blank" href="https://downloads.villatheme.com/?download=alidropship-extension"
		   class="vi-ui positive button labeled icon <?php echo esc_attr( self::set( 'download-chrome-extension' ) ) ?>">
			<i class="external icon"> </i><?php esc_html_e( 'Install Chrome Extension', 'woo-alidropship' ) ?>
		</a>
		<?php
	}

	public static function strtolower( $string ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $string ) : strtolower( $string );
	}

	public static function get_domain_name() {
		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$name = $_SERVER['HTTP_HOST'];
		} elseif ( ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$name = $_SERVER['SERVER_NAME'];
		} else {
			$name = self::get_domain_from_url( get_bloginfo( 'url' ) );
		}

		return $name;
	}

	public static function ali_ds_get_sign( $args, $type = 'place_order' ) {
		$return = array(
			'status' => 'error',
			'data'   => '',
			'code'   => '',
		);
		$url = VI_WOOCOMMERCE_ALIDROPSHIP_GET_SIGNATURE_SEARCH_PRODUCT;

		$url = apply_filters( 'ald_villatheme_api_url', $url, $type );

		$request = wp_remote_post( $url, array(
			'body'       => $args,
			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
			'timeout'    => 30,
		) );

		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			$body           = vi_wad_json_decode( $request['body'] );
			$return['code'] = $body['code'];
			$return['data'] = $body['msg'];
			if ( $body['code'] == 200 ) {
				$return['status'] = 'success';
			}
		} else {
			$return['code'] = $request->get_error_code();
			$return['data'] = $request->get_error_message();
		}

		return $return;
	}

	public static function ali_request( $params, $body = [] ,$request_args = [],$api_url='') {
		try {
			$url     = add_query_arg( array_map( 'urlencode', $params ), $api_url ?: 'https://api-sg.aliexpress.com/sync' );
			$request_args= wp_parse_args($request_args, array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'headers'    => array(
					'Content-Type' => 'text/plain;charset=UTF-8',
				),
				'body'       => $body,
				'timeout'    => 60,
			));
			$request = wp_remote_post( $url, $request_args);
			if ( ! is_wp_error( $request ) ) {
				$body = wp_remote_retrieve_body( $request );
				return vi_wad_json_decode( $body, true );
			} else {
				return false;
			}
		} catch ( \Exception $e ) {
			return false;
		}
	}
	public static function get_ali_orders( $count = true, $status = 'to_order', $limit = 0, $offset = 0 ) {
		$instance = self::get_instance();
		global $wpdb;
		$woocommerce_order_items    = $wpdb->prefix . "woocommerce_order_items";
		$woocommerce_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";
		$order_status_for_fulfill   = $instance->get_params( 'order_status_for_fulfill' );

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$posts  = $wpdb->prefix . "wc_orders";
			$select = "DISTINCT {$posts}.id";
			$query  = "FROM {$posts} LEFT JOIN {$woocommerce_order_items} ON {$posts}.id={$woocommerce_order_items}.order_id";
			$query  .= " LEFT JOIN {$woocommerce_order_itemmeta} ON {$woocommerce_order_items}.order_item_id={$woocommerce_order_itemmeta}.order_item_id";
			$query  .= " WHERE {$posts}.type='shop_order' AND {$woocommerce_order_itemmeta}.meta_key='_vi_wad_aliexpress_order_id'";
			if ( $order_status_for_fulfill ) {
				$query .= " AND {$posts}.status IN ( '" . implode( "','", $order_status_for_fulfill ) . "' )";
			}
		} else {
			$posts  = $wpdb->prefix . "posts";
			$select = "DISTINCT {$posts}.ID";
			$query  = "FROM {$posts} LEFT JOIN {$woocommerce_order_items} ON {$posts}.ID={$woocommerce_order_items}.order_id";
			$query  .= " LEFT JOIN {$woocommerce_order_itemmeta} ON {$woocommerce_order_items}.order_item_id={$woocommerce_order_itemmeta}.order_item_id";
			$query  .= " WHERE {$posts}.post_type='shop_order' AND {$woocommerce_order_itemmeta}.meta_key='_vi_wad_aliexpress_order_id'";
			if ( $order_status_for_fulfill ) {
				$query .= " AND {$posts}.post_status IN ( '" . implode( "','", $order_status_for_fulfill ) . "' )";
			}
		}


		if ( $status === 'to_order' ) {
			$query .= " AND {$woocommerce_order_itemmeta}.meta_value=''";
		}
//		else {
//			$query = "FROM {$posts} LEFT JOIN {$woocommerce_order_items} ON {$posts}.ID={$woocommerce_order_items}.order_id LEFT JOIN {$woocommerce_order_itemmeta} ON {$woocommerce_order_items}.order_item_id={$woocommerce_order_itemmeta}.order_item_id left JOIN `{$postmeta}` on `{$woocommerce_order_itemmeta}`.`meta_value`=`{$postmeta}`.`post_id` WHERE `{$woocommerce_order_itemmeta}`.`meta_key`='_product_id' and `{$postmeta}`.`meta_key`='_vi_wad_aliexpress_product_id' ";
//		}

		if ( $count ) {
			$query = "SELECT COUNT({$select}) {$query}";

			return $wpdb->get_var( $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$query = "SELECT {$select} {$query}";
			if ( $limit ) {
				$query .= " LIMIT {$offset},{$limit}";
			}

			return $wpdb->get_col( $query, 0 );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}


	/**
	 * @param $country_code
	 *
	 * @return float|int|string
	 */
	public static function get_ali_tax( $country_code ) {
		$country_code = strtolower( $country_code );
		$rates        = array(
			/*US*/
//			'us' => 10,
			/*New Zealand*/
//			'nz' => 15,
			/*Australia*/
//			'au' => 10,
			/*EU*/
			'at' => 20,
			'be' => 21,
			'cz' => 21,
			'dk' => 25,
			'ee' => 20,
			'fi' => 24,
			'fr' => 20,
			'de' => 19,
			'gr' => 24,
			'hu' => 27,
			'is' => 24,
			'ie' => 23,
			'it' => 22,
			'lv' => 21,
			'lu' => 17,
			'nl' => 21,
			'no' => 25,
			'pl' => 23,
			'pt' => 23,
			'sk' => 20,
			'si' => 22,
			'es' => 21,
			'se' => 25,
			'ch' => 7.7,
			'cy' => 19,
			/*United Kingdom*/
//			'uk' => 20,
		);

		return isset( $rates[ $country_code ] ) ? $rates[ $country_code ] / 100 : '';
	}

	/**
	 * Get exchange rate based on selected API
	 *
	 * @param string $api
	 * @param string $target_currency
	 * @param bool $decimals
	 * @param string $source_currency
	 *
	 * @return bool|int|mixed|void
	 */
	public static function get_exchange_rate( $api = 'google', $target_currency = '', $decimals = false, $source_currency = 'USD' ) {
		if ( $decimals === false ) {
			$decimals = self::get_instance()->get_params( 'exchange_rate_decimals' );
		}
		$rate = false;
		if ( ! $target_currency ) {
			$target_currency = get_option( 'woocommerce_currency' );
		}
		if ( self::strtolower( $target_currency ) === self::strtolower( $source_currency ) ) {
			$rate = 1;
		} else {
			switch ( $api ) {
				case 'google':
					$get_rate = self::get_google_exchange_rate( $target_currency, $source_currency );
					break;
				default:
					$get_rate = array(
						'status' => 'error',
						'data'   => false,
					);
			}
			if ( $get_rate['status'] === 'success' && $get_rate['data'] ) {
				$rate = $get_rate['data'];
			}
			$rate = apply_filters( 'vi_wad_get_exchange_rate', round( $rate, $decimals ), $api );
		}

		return $rate;
	}

	/**
	 * @param $target_currency
	 * @param string $source_currency
	 *
	 * @return array
	 */
	private static function get_google_exchange_rate( $target_currency, $source_currency = 'USD' ) {
		$response = array(
			'status' => 'error',
			'data'   => false,
		);
		$url      = 'https://www.google.com/async/currency_v2_update?vet=12ahUKEwjfsduxqYXfAhWYOnAKHdr6BnIQ_sIDMAB6BAgFEAE..i&ei=kgAGXN-gDJj1wAPa9ZuQBw&yv=3&async=source_amount:1,source_currency:' . self::get_country_freebase( $source_currency ) . ',target_currency:' . self::get_country_freebase( $target_currency ) . ',lang:en,country:us,disclaimer_url:https%3A%2F%2Fwww.google.com%2Fintl%2Fen%2Fgooglefinance%2Fdisclaimer%2F,period:5d,interval:1800,_id:knowledge-currency__currency-v2-updatable,_pms:s,_fmt:pc';
		$request  = wp_remote_get(
			$url, array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'timeout'    => 10
			)
		);
		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			preg_match( '/data-exchange-rate=\"(.+?)\"/', $request['body'], $match );
			if ( is_array( $match ) && count( $match ) > 1 ) {
				$response['status'] = 'success';
				$response['data']   = $match[1];
			} else {
				$response['data'] = esc_html__( 'Preg_match fails', 'woo-alidropship' );
			}
		} else {
			$response['data'] = $request->get_error_message();
		}

		return $response;
	}

	private static function get_country_freebase( $country_code = '' ) {
		$countries = array(
			"AED" => "/m/02zl8q",
			"AFN" => "/m/019vxc",
			"ALL" => "/m/01n64b",
			"AMD" => "/m/033xr3",
			"ANG" => "/m/08njbf",
			"AOA" => "/m/03c7mb",
			"ARS" => "/m/024nzm",
			"AUD" => "/m/0kz1h",
			"AWG" => "/m/08s1k3",
			"AZN" => "/m/04bq4y",
			"BAM" => "/m/02lnq3",
			"BBD" => "/m/05hy7p",
			"BDT" => "/m/02gsv3",
			"BGN" => "/m/01nmfw",
			"BHD" => "/m/04wd20",
			"BIF" => "/m/05jc3y",
			"BMD" => "/m/04xb8t",
			"BND" => "/m/021x2r",
			"BOB" => "/m/04tkg7",
			"BRL" => "/m/03385m",
			"BSD" => "/m/01l6dm",
			"BTC" => "/m/05p0rrx",
			"BWP" => "/m/02nksv",
			"BYN" => "/m/05c9_x",
			"BZD" => "/m/02bwg4",
			"CAD" => "/m/0ptk_",
			"CDF" => "/m/04h1d6",
			"CHF" => "/m/01_h4b",
			"CLP" => "/m/0172zs",
			"CNY" => "/m/0hn4_",
			"COP" => "/m/034sw6",
			"CRC" => "/m/04wccn",
			"CUC" => "/m/049p2z",
			"CUP" => "/m/049p2z",
			"CVE" => "/m/06plyy",
			"CZK" => "/m/04rpc3",
			"DJF" => "/m/05yxn7",
			"DKK" => "/m/01j9nc",
			"DOP" => "/m/04lt7_",
			"DZD" => "/m/04wcz0",
			"EGP" => "/m/04phzg",
			"ETB" => "/m/02_mbk",
			"EUR" => "/m/02l6h",
			"FJD" => "/m/04xbp1",
			"GBP" => "/m/01nv4h",
			"GEL" => "/m/03nh77",
			"GHS" => "/m/01s733",
			"GMD" => "/m/04wctd",
			"GNF" => "/m/05yxld",
			"GTQ" => "/m/01crby",
			"GYD" => "/m/059mfk",
			"HKD" => "/m/02nb4kq",
			"HNL" => "/m/04krzv",
			"HRK" => "/m/02z8jt",
			"HTG" => "/m/04xrp0",
			"HUF" => "/m/01hfll",
			"IDR" => "/m/0203sy",
			"ILS" => "/m/01jcw8",
			"INR" => "/m/02gsvk",
			"IQD" => "/m/01kpb3",
			"IRR" => "/m/034n11",
			"ISK" => "/m/012nk9",
			"JMD" => "/m/04xc2m",
			"JOD" => "/m/028qvh",
			"JPY" => "/m/088n7",
			"KES" => "/m/05yxpb",
			"KGS" => "/m/04k5c6",
			"KHR" => "/m/03_m0v",
			"KMF" => "/m/05yxq3",
			"KRW" => "/m/01rn1k",
			"KWD" => "/m/01j2v3",
			"KYD" => "/m/04xbgl",
			"KZT" => "/m/01km4c",
			"LAK" => "/m/04k4j1",
			"LBP" => "/m/025tsrc",
			"LKR" => "/m/02gsxw",
			"LRD" => "/m/05g359",
			"LSL" => "/m/04xm1m",
			"LYD" => "/m/024xpm",
			"MAD" => "/m/06qsj1",
			"MDL" => "/m/02z6sq",
			"MGA" => "/m/04hx_7",
			"MKD" => "/m/022dkb",
			"MMK" => "/m/04r7gc",
			"MOP" => "/m/02fbly",
			"MRO" => "/m/023c2n",
			"MUR" => "/m/02scxb",
			"MVR" => "/m/02gsxf",
			"MWK" => "/m/0fr4w",
			"MXN" => "/m/012ts8",
			"MYR" => "/m/01_c9q",
			"MZN" => "/m/05yxqw",
			"NAD" => "/m/01y8jz",
			"NGN" => "/m/018cg3",
			"NIO" => "/m/02fvtk",
			"NOK" => "/m/0h5dw",
			"NPR" => "/m/02f4f4",
			"NZD" => "/m/015f1d",
			"OMR" => "/m/04_66x",
			"PAB" => "/m/0200cp",
			"PEN" => "/m/0b423v",
			"PGK" => "/m/04xblj",
			"PHP" => "/m/01h5bw",
			"PKR" => "/m/02svsf",
			"PLN" => "/m/0glfp",
			"PYG" => "/m/04w7dd",
			"QAR" => "/m/05lf7w",
			"RON" => "/m/02zsyq",
			"RSD" => "/m/02kz6b",
			"RUB" => "/m/01hy_q",
			"RWF" => "/m/05yxkm",
			"SAR" => "/m/02d1cm",
			"SBD" => "/m/05jpx1",
			"SCR" => "/m/01lvjz",
			"SDG" => "/m/08d4zw",
			"SEK" => "/m/0485n",
			"SGD" => "/m/02f32g",
			"SLL" => "/m/02vqvn",
			"SOS" => "/m/05yxgz",
			"SRD" => "/m/02dl9v",
			"SSP" => "/m/08d4zw",
			"STD" => "/m/06xywz",
			"SZL" => "/m/02pmxj",
			"THB" => "/m/0mcb5",
			"TJS" => "/m/0370bp",
			"TMT" => "/m/0425kx",
			"TND" => "/m/04z4ml",
			"TOP" => "/m/040qbv",
			"TRY" => "/m/04dq0w",
			"TTD" => "/m/04xcgz",
			"TWD" => "/m/01t0lt",
			"TZS" => "/m/04s1qh",
			"UAH" => "/m/035qkb",
			"UGX" => "/m/04b6vh",
			"USD" => "/m/09nqf",
			"UYU" => "/m/04wblx",
			"UZS" => "/m/04l7bl",
			"VEF" => "/m/021y_m",
			"VND" => "/m/03ksl6",
			"XAF" => "/m/025sw2b",
			"XCD" => "/m/02r4k",
			"XOF" => "/m/025sw2q",
			"XPF" => "/m/01qyjx",
			"YER" => "/m/05yxwz",
			"ZAR" => "/m/01rmbs",
			"ZMW" => "/m/0fr4f"
		);
		if ( $country_code ) {
			return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : '';
		} else {
			return $countries;
		}
	}

	public static function is_ald_table() {
		if ( self::$is_ald_table !== null ) {
			return self::$is_ald_table;
		}

		$deleted_old_data = get_option( 'ald_deleted_old_posts_data' );
		if ( $deleted_old_data ) {
			self::$is_ald_table = true;
		} else {
			self::$is_ald_table = self::get_instance()->get_params( 'ald_table' );
		}

		return self::$is_ald_table;
	}
}