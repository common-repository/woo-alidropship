<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOO_ALIDROPSHIP_Admin_API
 */
class VI_WOO_ALIDROPSHIP_Admin_API {
	protected $product_data;
	protected static $settings;
	protected static $orders_tracking_carriers;
	protected static $found_carriers;
	protected $process_description;
	protected $namespace;

	public function __construct() {
		self::$found_carriers = array(
			'url'      => array(),
			'carriers' => array(),
		);
		self::$settings       = VI_WOO_ALIDROPSHIP_DATA::get_instance();
		/*Namespace must be the same for both premium and free version as it's hardcoded in the chrome extension as permissions in manifest.json*/
		$this->namespace = 'woocommerce_aliexpress_dropship';

		add_action( 'rest_api_init', array( $this, 'register_api' ) );
		add_filter( 'woocommerce_rest_is_request_to_rest_api', array( $this, 'woocommerce_rest_is_request_to_rest_api' ) );
		add_filter( 'villatheme_woo_alidropship_sync_ali_order_carrier_url', array( $this, 'villatheme_woo_alidropship_sync_ali_order_carrier_url' ) );
	}

	public function woocommerce_rest_is_request_to_rest_api( $is_request_to_rest_api ) {
		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		if ( false !== strpos( $request_uri, $rest_prefix . "{$this->namespace}/" ) ) {
			$is_request_to_rest_api = true;
		}

		return $is_request_to_rest_api;
	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 */
	public function villatheme_woo_alidropship_sync_ali_order_carrier_url( $url ) {
		$carrier_url_replaces = self::$settings->get_params( 'carrier_url_replaces' );
		if ( $url && isset( $carrier_url_replaces['to_string'] ) && is_array( $carrier_url_replaces['to_string'] ) && $str_replace_count = count( $carrier_url_replaces['to_string'] ) ) {
			for ( $i = 0; $i < $str_replace_count; $i ++ ) {
				if ( false !== stripos( $url, $carrier_url_replaces['from_string'][ $i ] ) ) {
					$url = $carrier_url_replaces['to_string'][ $i ];
					add_filter( 'villatheme_woo_alidropship_sync_ali_order_carrier_name', array(
						$this,
						'villatheme_woo_alidropship_sync_ali_order_carrier_name'
					) );
					break;
				}
			}
		}

		return $url;
	}

	/**
	 * @param $name
	 *
	 * @return mixed
	 */
	public function villatheme_woo_alidropship_sync_ali_order_carrier_name( $name ) {
		$carrier_name_replaces = self::$settings->get_params( 'carrier_name_replaces' );
		if ( $name && isset( $carrier_name_replaces['to_string'] ) && is_array( $carrier_name_replaces['to_string'] ) && $str_replace_count = count( $carrier_name_replaces['to_string'] ) ) {
			for ( $i = 0; $i < $str_replace_count; $i ++ ) {
				if ( $carrier_name_replaces['sensitive'][ $i ] ) {
					$name = str_replace( $carrier_name_replaces['from_string'][ $i ], $carrier_name_replaces['to_string'][ $i ], $name );
				} else {
					$name = str_ireplace( $carrier_name_replaces['from_string'][ $i ], $carrier_name_replaces['to_string'][ $i ], $name );
				}
			}
		}

		return $name;
	}

	public static function sort_by_product_id( $array1, $array2 ) {
		return $array1['productID'] - $array2['productID'];
	}

	/**
	 * Register API json
	 */
	public function register_api() {
		register_rest_route(
			$this->namespace, '/sync', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_normal' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace, '/get_product_sku', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_product_sku_normal' ),
				'permission_callback' => '__return_true',
			)
		);
//		register_rest_route(
//			$this->namespace, '/get_aliexpress_orders', array(
//				'methods'             => WP_REST_Server::CREATABLE,
//				'callback'            => array( $this, 'get_aliexpress_orders_normal' ),
//				'permission_callback' => '__return_true',
//			)
//		);
		register_rest_route(
			$this->namespace, '/get_orders_to_sync', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_orders_to_sync_normal' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace, '/request_order', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'request_order_normal' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace, '/response_order', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'response_order_normal' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace, '/sync_order', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_order_normal' ),
				'permission_callback' => '__return_true',
			)
		);
		/*Auth method*/
		register_rest_route(
			$this->namespace, '/auth', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'auth' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$this->namespace, '/auth/sync', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_auth' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace, '/auth/get_product_sku', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_product_sku_auth' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
//		register_rest_route(
//			$this->namespace, '/auth/get_aliexpress_orders', array(
//				'methods'             => WP_REST_Server::CREATABLE,
//				'callback'            => array( $this, 'get_aliexpress_orders_auth' ),
//				'permission_callback' => array( $this, 'permissions_check' ),
//			)
//		);
		register_rest_route(
			$this->namespace, '/auth/get_orders_to_sync', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_orders_to_sync_auth' ),
				'permission_callback' => array( $this, 'permissions_check_edit_orders' ),
			)
		);
		register_rest_route(
			$this->namespace, '/auth/request_order', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'request_order_auth' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace, '/auth/response_order', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'response_order_auth' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace, '/auth/sync_order', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_order_auth' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace, '/auth/revoke_api_key', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'revoke_api_key' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * @param $consumer_key
	 *
	 * @return array|object|null
	 */
	private function get_user_data_by_consumer_key( $consumer_key ) {
		global $wpdb;

		$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
		$user         = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"
			SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE consumer_key = %s
		",
				$consumer_key
			)
		);

		return $user;
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function revoke_api_key( $request ) {
		$this->validate( $request, false );
		$consumer_key    = sanitize_text_field( $request->get_param( 'consumer_key' ) );
		$consumer_secret = sanitize_text_field( $request->get_param( 'consumer_secret' ) );
		if ( ! $consumer_key ) {
			$authorization = $request->get_header( 'authorization' );
			if ( $authorization ) {
				$authorization = base64_decode( substr( $authorization, 6 ) );
				$consumer      = explode( ':', $authorization );
				if ( count( $consumer ) === 2 ) {
					$consumer_key    = $consumer[0];
					$consumer_secret = $consumer[1];
				}
			}
		}
		if ( ! $consumer_key && ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			$consumer_key = sanitize_text_field( $_SERVER['PHP_AUTH_USER'] );
		}
		if ( ! $consumer_secret && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
			$consumer_secret = sanitize_text_field( $_SERVER['PHP_AUTH_PW'] );
		}
		wp_send_json(
			array(
				'status' => 'success',
				'result' => self::revoke_woocommerce_api_key( $consumer_key, $consumer_secret ),
			)
		);
	}

	/**
	 * @param $consumer_key
	 * @param $consumer_secret
	 *
	 * @return bool|false|int
	 */
	public static function revoke_woocommerce_api_key( $consumer_key, $consumer_secret ) {
		global $wpdb;
		$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );

		return $wpdb->query(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"
			DELETE FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE consumer_key = %s AND consumer_secret=%s
		",
				$consumer_key, $consumer_secret
			)
		);
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @return bool|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( ! wc_rest_check_post_permissions( 'product', 'create' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_create', esc_html__( 'Unauthorized', 'woo-alidropship' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	public function permissions_check_edit_orders( $request ) {
		if ( ! wc_rest_check_post_permissions( 'shop_order', 'create' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_edit', esc_html__( 'Unauthorized', 'woo-alidropship' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * @param $request WP_REST_Request
	 */

	public function auth( $request ) {
		$consumer_key    = sanitize_text_field( $request->get_param( 'consumer_key' ) );
		$consumer_secret = sanitize_text_field( $request->get_param( 'consumer_secret' ) );
		if ( $consumer_key && $consumer_secret ) {
			$user = $this->get_user_data_by_consumer_key( $consumer_key );
			if ( $user && hash_equals( $user->consumer_secret, $consumer_secret ) ) {
				update_option( 'vi_wad_temp_api_credentials', $request->get_params() );
			}
		}
	}

	/**Validate request from chrome extension
	 *
	 * @param $request WP_REST_Request
	 * @param bool $check_key
	 */

	public function validate( $request, $check_key = true ) {
		$result = array(
			'status'       => 'error',
			'message'      => '',
			'message_type' => 1,
		);

		/*check ssl*/
		if ( ! is_ssl() ) {
			$result['message']      = esc_html__( 'SSL is required', 'woo-alidropship' );
			$result['message_type'] = 2;

			wp_send_json( $result );
		}
		/*check enable*/
		if ( ! self::$settings->get_params( 'enable' ) ) {
			$result['message']      = esc_html__( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce plugin is currently disabled. Please enable it to use this function.', 'woo-alidropship' );
			$result['message_type'] = 2;

			wp_send_json( $result );
		}
		/*check key*/
		if ( $check_key ) {
			$key = $request->get_param( 'key' );
			if ( ! $key || $key !== self::$settings->get_params( 'secret_key' ) ) {
				$result['message']      = esc_html__( 'Secret key does not match', 'woo-alidropship' );
				$result['message_type'] = 2;

				wp_send_json( $result );
			}
		}
		$require_version = $request->get_param( 'require_version' );

		/*check version*/
		if ( version_compare( VI_WOO_ALIDROPSHIP_VERSION, $require_version, '<' ) ) {
			$result['message']      = sprintf( esc_html__( 'Require ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce plugin version %s, your current version is %s', 'woo-alidropship' ), $require_version ,VI_WOO_ALIDROPSHIP_VERSION);//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			$result['message_type'] = 2;

			wp_send_json( $result );
		}

		$migrate_process = VI_WOO_ALIDROPSHIP_Admin_Migrate_New_Table::migrate_process();
		if ( ! $migrate_process->is_queue_empty() || $migrate_process->is_process_running() ) {
			$result['message']      = esc_html__( 'The migration process is currently running in the background. Please do not import at this time', 'woo-alidropship' );
			$result['message_type'] = 2;

			wp_send_json( $result );
		}
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function get_orders_to_sync_normal( $request ) {
		$this->validate( $request );
		$this->get_orders_to_sync( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function get_orders_to_sync_auth( $request ) {
		$this->validate( $request, false );
		$this->get_orders_to_sync( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_orders_to_sync( $request ) {
		global $wpdb;
		$per_page = absint( $request->get_param( 'per_page' ) );
		if ( ! $per_page ) {
			$per_page = 10;
		}
		$current_page = absint( $request->get_param( 'current_page' ) );
		$total_page   = 1;
		$query        = "SELECT count(DISTINCT woocommerce_order_itemmeta.meta_value) FROM {$wpdb->prefix}woocommerce_order_itemmeta AS woocommerce_order_itemmeta WHERE (meta_key='_vi_wad_aliexpress_order_id' OR meta_key='_vi_wad_match_aliexpress_order_id') AND meta_value!=''";
		$total        = $wpdb->get_var( $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $total > 0 ) {
			$total_page = ceil( $total / $per_page );
		}

		$query = "SELECT DISTINCT woocommerce_order_itemmeta.meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta AS woocommerce_order_itemmeta WHERE (meta_key='_vi_wad_aliexpress_order_id' OR meta_key='_vi_wad_match_aliexpress_order_id') AND meta_value!='' LIMIT %d,%d";

		$orders_ids = $wpdb->get_col( $wpdb->prepare( $query, array(// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$per_page * ( $current_page - 1 ),
			$per_page
		) ), 0 );

		$response = array(
			'status'       => 'success',
			'total_page'   => $total_page,
			'total_orders' => $total,
			'orders_ids'   => $orders_ids,
		);

		wp_send_json( $response );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_aliexpress_orders_normal( $request ) {
		$this->validate( $request );
		$this->get_aliexpress_orders( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_aliexpress_orders_auth( $request ) {
		$this->validate( $request, false );
		$this->get_aliexpress_orders( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_aliexpress_orders( $request ) {
		global $wpdb;
		$result = array(
			'status'  => 'success',
			'message' => '',
			'data'    => wp_json_encode( array() ),
		);
		vi_wad_set_time_limit();
		$query          = "Select DISTINCT woocommerce_order_itemmeta.meta_value from {$wpdb->prefix}woocommerce_order_itemmeta AS woocommerce_order_itemmeta where meta_key='_vi_wad_aliexpress_order_id' OR meta_key='_vi_wad_match_aliexpress_order_id'";
		$result['data'] = wp_json_encode( array_values( array_filter( $wpdb->get_col( $query, 0 ) ) ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_send_json( $result );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function sync_normal( $request ) {
		$this->validate( $request );
		$this->sync( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function sync_auth( $request ) {
		$this->validate( $request, false );
		$this->sync( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function sync( $request ) {
		$result = array(
			'status'       => 'error',
			'message'      => '',
			'message_type' => 1,
		);
		vi_wad_set_time_limit();
		$product_id   = $request->get_param( 'productID' );
		$product_html =  base64_decode( $request->get_param( 'ali_product_data' ) ) ;
		$data         = array();
		$country     = $request->get_param( 'country' );
		if ( $product_id ) {
			$language     = explode('_',$request->get_param( 'language' )?:'en')[0] ;
			$currency     = $request->get_param( 'currency' );
			$get_data = VI_WOO_ALIDROPSHIP_DATA::get_data( "https://www.aliexpress.com/item/{$product_id}.html", [], $product_html,false,[
				'product_id' => $product_id,
				'target_currency' => $currency,
				'ship_to_country' => $country,
				'target_language' => $language,
				'locale' => 'en_US',
				'domain'=>get_site_url(),
				'action' => 'import',
			] );

			if ( ! empty( $request->get_param( 'ru_product_description' ) ) && empty( $get_data['data']['description'] ) ) {
				$get_data['data']['description'] = base64_decode( $request->get_param( 'ru_product_description' ) );
			}

			if ( ! empty( $request->get_param( 'ru_product_characteristics' ) ) && empty( $get_data['data']['specsModule'] ) ) {
				$get_data['data']['specsModule'] = $request->get_param( 'ru_product_characteristics' );
			}

			if ( $get_data['status'] == 'success' ) {
				$data = $get_data['data'];
			} else {
				if ( $get_data['message'] ) {
					$result['message']      = $get_data['message'];
					$result['message_type'] = 2;
				} else {
					$result['message']      = esc_html__( 'Cannot retrieve data.', 'woo-alidropship' );
					$result['message_type'] = 'try_again';
				}
				wp_send_json( $result );
			}
		} else {
			$data = vi_wad_json_decode( $request->get_param( 'data' ) );
		}

		$freight     = !empty($get_data['freight']) ? $get_data['freight'] : $request->get_param( 'freight' );
		$time = time() ;
		if ($freight){
			if (is_array($freight)){
				$freight = self::$settings::adjust_ali_freight( $freight,$country ==='RU'? 'api_ru':'api' );
			}else{
				$tmp = vi_wad_json_decode($freight);
				$freight = self::$settings::adjust_ali_freight( $tmp, $country ==='RU'? 'api_ru':'' );
			}
		}
		if (empty($freight)){
			$freight = [];
			$time -= HOUR_IN_SECONDS;
		}
		$freight_ext = $request->get_param( 'freight_ext' );

		$shipping_info = array(
			'time'          => $time,
			'country'       => $request->get_param( 'country' ),
			'company'       => $request->get_param( 'company' ),
			'company_name'  => '',
			'freight'       => $freight,
			'freight_ext'   => $freight_ext,
			'shipping_cost' => null,
			'delivery_time' => '',
		);
		$this->add_to_import_list( $data, $shipping_info );
	}

	public function add_to_import_list( $data, $shipping_info ) {
		$result  = array(
			'status'       => 'error',
			'message'      => '',
			'message_type' => 1,
		);
		$sku     = isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '';
		$post_id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_aliexpress_id( $sku );
		if ( ! $post_id ) {
			$post_id = self::$settings->create_product( $data, $shipping_info );
			if ( is_wp_error( $post_id ) ) {
				$result['message'] = $post_id->get_error_message();
				wp_send_json( $result );
			} elseif ( ! $post_id ) {
				$result['message'] = esc_html__( 'Cannot create post', 'woo-alidropship' );
				wp_send_json( $result );
			}
			$result['status']  = 'success';
			$result['message'] = esc_html__( 'Product is added to import list', 'woo-alidropship' );
		} else {
			$result['message'] = esc_html__( 'Product exists', 'woo-alidropship' );
		}

		wp_send_json( $result );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_product_sku_normal( $request ) {
		$this->validate( $request );
		$this->get_product_sku( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_product_sku_auth( $request ) {
		$this->validate( $request, false );
		$this->get_product_sku( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function get_product_sku( $request ) {
		$result   = array(
			'status'  => 'success',
			'message' => '',
			'data'    => wp_json_encode( array() ),
		);
		$products = $request->get_param( 'products' );
		if ( $products && is_array( $products ) ) {
			vi_wad_set_time_limit();
			$args = array(
				'post_type'      => 'vi_wad_draft_product',
				'posts_per_page' => count( $products ),
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'post_status'    => array(
					'publish',
					'draft',
					'override'
				),
				'fields'         => 'ids',
				'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => '_vi_wad_sku',
						'value'   => $products,
						'compare' => 'IN'
					)
				)
			);
//			$the_query = new WP_Query( $args );
			$the_query = VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? new Ali_Product_Query( $args ) : new WP_Query( $args );

			$imported = array();
			if ( $the_query->posts ) {
				foreach ( $the_query->posts as $id ) {
					$imported[] = Ali_Product_Table::get_post_meta( $id, '_vi_wad_sku', true );
				}
				$result['data'] = wp_json_encode( $imported );
			}
		}
		wp_send_json( $result );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function response_order_normal( $request ) {
		$this->validate( $request );
		$this->response_order( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function response_order_auth( $request ) {
		$this->validate( $request, false );
		$this->response_order( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function response_order( $request ) {
		vi_wad_set_time_limit();
		$matchOrders = $request->get_param( 'matchOrders' );
		if ( is_array( $matchOrders ) && count( $matchOrders ) ) {
			$from_order_id = $request->get_param( 'fromOrderId' );
			$order         = wc_get_order( $from_order_id );
			if ( $from_order_id && $order ) {
				$order_items = $order->get_items();
				if ( count( $order_items ) ) {
					foreach ( $matchOrders as $matchOrder ) {
						$ali_order_id = $matchOrder['orderId'];
						$orderTotal   = $matchOrder['orderTotal'];
						if ( $orderTotal ) {
							$orderDetails = self::get_order_details( $orderTotal );
							VI_WOO_ALIDROPSHIP_Ali_Orders_Info_Table::insert( $ali_order_id, $orderDetails['currency'], $orderDetails['total'] );
						}
						$matchProductIds = array_unique( $matchOrder['matchProductIds'] );
						foreach ( $order_items as $item_id => $item ) {
							$product_id     = $item['product_id'];
							$ali_product_id = get_post_meta( $product_id, '_vi_wad_aliexpress_product_id', true );
							if ( in_array( $ali_product_id, $matchProductIds ) ) {
								$match_aliexpress_order_ids = wc_get_order_item_meta( $item_id, '_vi_wad_match_aliexpress_order_id', false );
								if ( ! in_array( $ali_order_id, $match_aliexpress_order_ids ) ) {
									wc_add_order_item_meta( $item_id, '_vi_wad_match_aliexpress_order_id', $ali_order_id );
								}
								if ( strtolower( trim( $matchOrder['orderStatus'] ) ) === 'wait_seller_send_goods' ) {
									wc_update_order_item_meta( $item_id, '_vi_wad_aliexpress_order_id', $ali_order_id );
								}
							}
						}
					}
				}
			}
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**Get order currency and order total
	 *
	 * @param $orderTotal
	 *
	 * @return array
	 */
	public static function get_order_details( $orderTotal ) {
		$orderTotal         = html_entity_decode( $orderTotal );
		$support_currencies = array( '$', '￡', '€', 'руб.', '￥', 'SEK' );
		$total              = $currency = '';
		foreach ( $support_currencies as $symbol ) {
			if ( strpos( $orderTotal, $symbol ) !== false ) {
				$orderTotalPatterns = explode( $symbol, $orderTotal );
				$orderTotalPatterns = array_map( 'trim', $orderTotalPatterns );
				switch ( $symbol ) {
					case '￡':
						$currency = 'GBP';
						$total    = trim( str_ireplace( array( $currency, $symbol ), array( '', '' ), $orderTotal ) );
						break;
					case '€':
						$currency = 'EUR';
						$total    = trim( str_ireplace( array( $currency, $symbol ), array( '', '' ), $orderTotal ) );
						break;
					case 'руб.':
						$currency = 'RUB';
						$total    = trim( str_ireplace( array( $currency, $symbol ), array( '', '' ), $orderTotal ) );
						break;
					case '￥':
						$currency = 'JPY';
						$total    = trim( str_ireplace( array( $currency, $symbol ), array( '', '' ), $orderTotal ) );
						break;
					case 'SEK':
						$currency = 'SEK';
						$total    = trim( str_ireplace( array( $currency, $symbol ), array( '', '' ), $orderTotal ) );
						break;
					case '$':
					default:
						$receiveCurrency = strtolower( $orderTotalPatterns[0] );
						if ( $receiveCurrency === 'au' ) {
							$currency = 'AUD';
							$total    = trim( str_ireplace( array( $currency, $symbol, 'au' ), array(
								'',
								'',
								''
							), $orderTotal ) );
						} elseif ( $receiveCurrency === 'us' ) {
							$currency = 'USD';
							$total    = trim( str_ireplace( array( $currency, $symbol, 'us' ), array(
								'',
								'',
								''
							), $orderTotal ) );
						} else {
							$currency = 'CAD';
							$total    = trim( str_ireplace( array( $currency, $symbol, 'ca' ), array(
								'',
								'',
								''
							), $orderTotal ) );
						}

				}
			}
		}
		$total = str_replace( ',', '.', $total );

		return array( 'total' => $total, 'currency' => $currency );
	}

	/**
	 * @param $ali_order_id
	 * @param $tracking_number
	 * @param $tracking_status
	 * @param $carrier_url
	 * @param $carrier_name
	 * @param $orderTotal
	 *
	 * @return array
	 * @throws Exception
	 */
	public function save_order_data( $ali_order_id, $tracking_number, $tracking_status, $carrier_url, $carrier_name, $orderTotal ) {
		$item_response = array(
			'status'       => 'error',
			'message'      => '',
			'ali_order_id' => $ali_order_id,
		);
		global $wpdb;
		$aliexpress_standard_shipping = false;
		if ( strtolower( trim( $carrier_name ) ) === 'aliexpress standard shipping' ) {
			$aliexpress_standard_shipping = true;
		} else {
			$carrier_url  = apply_filters( 'villatheme_woo_alidropship_sync_ali_order_carrier_url', $carrier_url );
			$carrier_name = apply_filters( 'villatheme_woo_alidropship_sync_ali_order_carrier_name', $carrier_name );
		}
		if ( $ali_order_id ) {
			$query          = $wpdb->prepare( "Select * from {$wpdb->prefix}woocommerce_order_itemmeta where meta_key='_vi_wad_aliexpress_order_id' and meta_value=%s", $ali_order_id );
			$q_result       = $wpdb->get_results( $query, ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared ,WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$order_item_ids = array();
			if ( count( $q_result ) ) {
				if ( $orderTotal ) {
					$orderDetails = self::get_order_details( $orderTotal );
					VI_WOO_ALIDROPSHIP_Ali_Orders_Info_Table::insert( $ali_order_id, $orderDetails['currency'], $orderDetails['total'] );
				}
				$item_response['status']  = 'success';
				$item_response['message'] = esc_html__( 'Order synced successfully', 'woo-alidropship' );
				foreach ( $q_result as $item ) {
					$order_item_id      = $item['order_item_id'];
					$order_item_ids[]   = $order_item_id;
					$item_tracking_data = wc_get_order_item_meta( $order_item_id, '_vi_wot_order_item_tracking_data', true );
					if ( $tracking_number ) {
						$current_tracking_data = array(
							'tracking_number' => '',
							'carrier_slug'    => '',
							'carrier_url'     => '',
							'carrier_name'    => '',
							'carrier_type'    => '',
							'time'            => time(),
						);
						if ( $item_tracking_data ) {
							$item_tracking_data = vi_wad_json_decode( $item_tracking_data );
							foreach ( $item_tracking_data as $order_tracking_data_k => $order_tracking_data_v ) {
								if ( $order_tracking_data_v['tracking_number'] == $tracking_number ) {
									$current_tracking_data = $order_tracking_data_v;
									unset( $item_tracking_data[ $order_tracking_data_k ] );
									break;
								}
							}
							$item_tracking_data = array_values( $item_tracking_data );
						} else {
							$item_tracking_data = array();
						}
						$current_tracking_data['tracking_number'] = $tracking_number;
						$found_carrier                            = $this->get_orders_tracking_carrier( $carrier_url, $carrier_name, $aliexpress_standard_shipping );
						if ( count( $found_carrier ) ) {
							$current_tracking_data['carrier_slug'] = $found_carrier['slug'];
							$current_tracking_data['carrier_url']  = $found_carrier['url'];
							$current_tracking_data['carrier_name'] = $found_carrier['name'];
						} else {
							$current_tracking_data['carrier_url']  = $carrier_url;
							$current_tracking_data['carrier_name'] = $carrier_name;
						}
						$item_tracking_data[] = $current_tracking_data;
						wc_update_order_item_meta( $order_item_id, '_vi_wot_order_item_tracking_data', wp_json_encode( $item_tracking_data ) );
					} else {
						$item_response['status']  = 'warning';
						$item_response['message'] = esc_html__( 'No tracking number', 'woo-alidropship' );
						if ( $item_tracking_data ) {
							$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
							$current_tracking_data = array_pop( $item_tracking_data );
							if ( $current_tracking_data['tracking_number'] ) {
								$item_tracking_data[] = array(
									'tracking_number' => '',
									'carrier_slug'    => '',
									'carrier_url'     => '',
									'carrier_name'    => '',
									'carrier_type'    => '',
									'time'            => time(),
								);
								wc_update_order_item_meta( $order_item_id, '_vi_wot_order_item_tracking_data', wp_json_encode( $item_tracking_data ) );
							}
						}
					}
					if ( in_array( strtolower( trim( $tracking_status ) ), array(
						'delivery successful',
						'delivered'
					), true ) ) {
						wc_update_order_item_meta( $order_item_id, '_vi_wad_aliexpress_order_item_status', 'shipped' );
					}
				}

			} else {
				$query    = $wpdb->prepare( "Select * from {$wpdb->prefix}woocommerce_order_itemmeta where meta_key='_vi_wad_match_aliexpress_order_id' and meta_value=%s", $ali_order_id );
				$q_result = $wpdb->get_results( $query, ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( count( $q_result ) ) {
					if ( $orderTotal ) {
						$orderDetails = self::get_order_details( $orderTotal );
						VI_WOO_ALIDROPSHIP_Ali_Orders_Info_Table::insert( $ali_order_id, $orderDetails['currency'], $orderDetails['total'] );
					}
					foreach ( $q_result as $item ) {
						$order_item_id    = $item['order_item_id'];
						$order_item_ids[] = $order_item_id;
						if ( wc_update_order_item_meta( $order_item_id, '_vi_wad_aliexpress_order_id', $ali_order_id ) ) {
							wc_update_order_item_meta( $order_item_id, '_vi_wad_aliexpress_order_item_status', 'processing' );
							$item_response['status']  = 'success';
							$item_response['message'] = esc_html__( 'Order synced successfully', 'woo-alidropship' );
							$item_tracking_data       = wc_get_order_item_meta( $order_item_id, '_vi_wot_order_item_tracking_data', true );
							if ( $tracking_number ) {
								$current_tracking_data = array(
									'tracking_number' => '',
									'carrier_slug'    => '',
									'carrier_url'     => '',
									'carrier_name'    => '',
									'carrier_type'    => '',
									'time'            => time(),
								);
								if ( $item_tracking_data ) {
									$item_tracking_data = vi_wad_json_decode( $item_tracking_data );
									foreach ( $item_tracking_data as $order_tracking_data_k => $order_tracking_data_v ) {
										if ( $order_tracking_data_v['tracking_number'] == $tracking_number ) {
											$current_tracking_data = $order_tracking_data_v;
											unset( $item_tracking_data[ $order_tracking_data_k ] );
											break;
										}
									}
									$item_tracking_data = array_values( $item_tracking_data );
								} else {
									$item_tracking_data = array();
								}
								$current_tracking_data['tracking_number'] = $tracking_number;
								$found_carrier                            = $this->get_orders_tracking_carrier( $carrier_url, $carrier_name, $aliexpress_standard_shipping );
								if ( count( $found_carrier ) ) {
									$current_tracking_data['carrier_slug'] = $found_carrier['slug'];
									$current_tracking_data['carrier_url']  = $found_carrier['url'];
									$current_tracking_data['carrier_name'] = $found_carrier['name'];
								} else {
									$current_tracking_data['carrier_url']  = $carrier_url;
									$current_tracking_data['carrier_name'] = $carrier_name;
								}
								$item_tracking_data[] = $current_tracking_data;
								wc_update_order_item_meta( $order_item_id, '_vi_wot_order_item_tracking_data', wp_json_encode( $item_tracking_data ) );
							} else {
								$item_response['status']  = 'warning';
								$item_response['message'] = esc_html__( 'No tracking number', 'woo-alidropship' );
								if ( $item_tracking_data ) {
									$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
									$current_tracking_data = array_pop( $item_tracking_data );
									if ( $current_tracking_data['tracking_number'] ) {
										$item_tracking_data[] = array(
											'tracking_number' => '',
											'carrier_slug'    => '',
											'carrier_url'     => '',
											'carrier_name'    => '',
											'carrier_type'    => '',
											'time'            => time(),
										);
										wc_update_order_item_meta( $order_item_id, '_vi_wot_order_item_tracking_data', wp_json_encode( $item_tracking_data ) );
									}
								}
							}
							if ( in_array( strtolower( trim( $tracking_status ) ), array(
								'delivery successful',
								'delivered'
							), true ) ) {
								wc_update_order_item_meta( $order_item_id, '_vi_wad_aliexpress_order_item_status', 'shipped' );
							}
						}
					}
				} else {
					$item_response['status']  = 'error';
					$item_response['message'] = esc_html__( 'No matched order found', 'woo-alidropship' );
				}
			}

			if ( count( $order_item_ids ) ) {
				$this->change_order_status( $order_item_ids );
			}
		} else {
			$item_response['status']  = 'error';
			$item_response['message'] = esc_html__( 'No matched order found', 'woo-alidropship' );
		}

		return $item_response;
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function sync_order_normal( $request ) {
		$this->validate( $request );
		$this->sync_order( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function sync_order_auth( $request ) {
		$this->validate( $request, false );
		$this->sync_order( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @throws Exception
	 */
	public function sync_order( $request ) {
		$result = array(
			'status'  => 'success',
			'message' => '',
			'data'    => array(),
			'stop'    => 0,
		);
		vi_wad_set_time_limit();
		$tracking_data_array = $request->get_param( 'tracking_data_array' );
		$response_array      = array();
		if ( is_array( $tracking_data_array ) && count( $tracking_data_array ) ) {
			foreach ( $tracking_data_array as $ali_tracking_item ) {
				$ali_order_id     = $ali_tracking_item['orderId'];
				$tracking_number  = $ali_tracking_item['tracking_number'];
				$tracking_status  = $ali_tracking_item['tracking_status'];
				$carrier_url      = apply_filters( 'villatheme_woo_alidropship_sync_ali_order_carrier_url', $ali_tracking_item['carrier_url'] );
				$carrier_name     = apply_filters( 'villatheme_woo_alidropship_sync_ali_order_carrier_name', $ali_tracking_item['carrier_name'] );
				$orderTotal       = $ali_tracking_item['orderTotal'];
				$item_response    = $this->save_order_data( $ali_order_id, $tracking_number, $tracking_status, $carrier_url, $carrier_name, $orderTotal );
				$response_array[] = $item_response;
			}
			$result['data'] = $response_array;
		} else {
			$ali_order_id      = $request->get_param( 'orderId' );
			$tracking_number   = $request->get_param( 'tracking_number' );
			$tracking_status   = $request->get_param( 'tracking_status' );
			$carrier_url       = $request->get_param( 'carrier_url' );
			$carrier_name      = $request->get_param( 'carrier_name' );
			$orderTotal        = $request->get_param( 'orderTotal' );
			$item_response     = $this->save_order_data( $ali_order_id, $tracking_number, $tracking_status, $carrier_url, $carrier_name, $orderTotal );
			$result['status']  = $item_response['status'];
			$result['message'] = $item_response['message'];
		}
		wp_send_json( $result );
	}

	/**
	 * @param $order_item_ids
	 *
	 * @throws Exception
	 */
	public function change_order_status( $order_item_ids ) {
		$status = self::$settings->get_params( 'order_status_after_sync' );
		if ( in_array( $status, array_keys( wc_get_order_statuses() ) ) ) {
			$order_ids = array_unique( array_map( 'wc_get_order_id_by_order_item_id', $order_item_ids ) );
			foreach ( $order_ids as $order_id ) {
				$order                 = wc_get_order( $order_id );
				$items                 = $order->get_items();
				$sum_ali_order_id      = 0;
				$sum_ali_tracking_code = 0;
				foreach ( $items as $item_id => $item ) {
					$ali_order_id       = $item->get_meta( '_vi_wad_aliexpress_order_id' );
					$item_tracking_data = wc_get_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', true );
					if ( $item_tracking_data ) {
						$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
						$current_tracking_data = array_pop( $item_tracking_data );
						if ( $current_tracking_data['tracking_number'] ) {
							$sum_ali_tracking_code ++;
						}
					}
					if ( $ali_order_id ) {
						$sum_ali_order_id ++;
					}
				}

				if ( $sum_ali_order_id && $sum_ali_tracking_code === $sum_ali_order_id ) {
					$order->update_status( $status );
				}
			}
		}
	}

	/**
	 * @param $carrier_url
	 * @param string $carrier_name
	 * @param bool $aliexpress_standard_shipping
	 *
	 * @return array|mixed
	 */
	public function get_orders_tracking_carrier( $carrier_url, $carrier_name = '', $aliexpress_standard_shipping = false ) {
		$return_carrier = array();
		if ( $aliexpress_standard_shipping ) {
			if ( class_exists( 'VI_WOO_ORDERS_TRACKING_DATA' ) || class_exists( 'VI_WOOCOMMERCE_ORDERS_TRACKING_DATA' ) ) {
				$return_carrier = array(
					'name'               => 'Aliexpress Standard Shipping',
					'slug'               => 'aliexpress-standard-shipping',
					'url'                => 'https://global.cainiao.com/detail.htm?mailNoList={tracking_number}',
					'country'            => 'Global',
					'active'             => '',
					'tracking_more_slug' => 'cainiao',
				);
			}
		} else {
			if ( $carrier_url ) {
				if ( self::$orders_tracking_carriers !== array() ) {
					$search_carrier = array_search( $carrier_url, self::$found_carriers['url'] );
					if ( false !== $search_carrier ) {
						$return_carrier = self::$found_carriers['carriers'][ $search_carrier ];
					} else {
						$original_url = $carrier_url;
						$carrier_url  = VI_WOO_ALIDROPSHIP_DATA::get_domain_from_url( $carrier_url );
						if ( self::$orders_tracking_carriers === null ) {
							if ( class_exists( 'VI_WOO_ORDERS_TRACKING_DATA' ) ) {
								$orders_tracking_data = new VI_WOO_ORDERS_TRACKING_DATA();
								$carriers_array       = VI_WOO_ORDERS_TRACKING_DATA::shipping_carriers();
								$custom_carriers      = $orders_tracking_data->get_params( 'custom_carriers_list' );
								if ( $custom_carriers ) {
									$carriers_array = array_merge( $carriers_array, vi_wad_json_decode( $custom_carriers ) );
								}
								self::$orders_tracking_carriers = $carriers_array;
							} elseif ( class_exists( 'VI_WOOCOMMERCE_ORDERS_TRACKING_DATA' ) ) {
								$orders_tracking_data = new VI_WOOCOMMERCE_ORDERS_TRACKING_DATA();
								$carriers_array       = VI_WOOCOMMERCE_ORDERS_TRACKING_DATA::shipping_carriers();
								$custom_carriers      = $orders_tracking_data->get_params( 'custom_carriers_list' );
								if ( $custom_carriers ) {
									$carriers_array = array_merge( $carriers_array, vi_wad_json_decode( $custom_carriers ) );
								}
								self::$orders_tracking_carriers = $carriers_array;
							} else {
								self::$orders_tracking_carriers = array();
							}
						}

						if ( count( self::$orders_tracking_carriers ) ) {
							$found = false;
							foreach ( self::$orders_tracking_carriers as $carrier ) {
								$existing_url = VI_WOO_ALIDROPSHIP_DATA::get_domain_from_url( $carrier['url'] );
								if ( $carrier_url === $existing_url ) {
									$return_carrier                     = $carrier;
									self::$found_carriers['url'][]      = $original_url;
									self::$found_carriers['carriers'][] = $carrier;
									$found                              = true;
									break;
								}
							}
							if ( ! $found ) {
								self::$found_carriers['url'][] = $original_url;
								if ( class_exists( 'VI_WOO_ORDERS_TRACKING_DATA' ) ) {
									$orders_tracking_data = new VI_WOO_ORDERS_TRACKING_DATA();
								} elseif ( class_exists( 'VI_WOOCOMMERCE_ORDERS_TRACKING_DATA' ) ) {
									$orders_tracking_data = new VI_WOOCOMMERCE_ORDERS_TRACKING_DATA();
								}
								if ( isset( $orders_tracking_data ) ) {
									if ( ! $carrier_name ) {
										$carrier_name = $carrier_url;
									}
									$orders_tracking_options = $orders_tracking_data->get_params();
									$custom_carriers         = $orders_tracking_data->get_params( 'custom_carriers_list' );
									if ( $custom_carriers ) {
										$custom_carriers = vi_wad_json_decode( $custom_carriers );
									} else {
										$custom_carriers = array();
									}
									$custom_carrier                                  = array(
										'name'    => $carrier_name,
										'slug'    => 'custom_' . time(),
										'url'     => $original_url,
										'country' => 'GLOBAL',
										'type'    => 'custom',
									);
									$custom_carriers[]                               = $custom_carrier;
									$return_carrier                                  = $custom_carrier;
									self::$orders_tracking_carriers[]                = $custom_carrier;
									$orders_tracking_options['custom_carriers_list'] = wp_json_encode( $custom_carriers );
									update_option( 'woo_orders_tracking_settings', $orders_tracking_options );
									self::$found_carriers['carriers'][] = $custom_carrier;
								} else {
									self::$found_carriers['carriers'][] = array();
								}
							}
						}
					}
				}
			}
		}

		return $return_carrier;
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function request_order_normal( $request ) {
		$this->validate( $request );
		$this->request_order( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function request_order_auth( $request ) {
		$this->validate( $request, false );
		$this->request_order( $request );
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function request_order( $request ) {
		$result = array(
			'status'  => 'error',
			'message' => esc_html__( 'Order not found', 'woo-alidropship' ),
		);
		vi_wad_set_time_limit();
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );
		if ( $order ) {
			$result['message']                    = esc_html__( 'Success', 'woo-alidropship' );
			$result['status']                     = 'success';
			$result['order_info']                 = $this->get_order_info( $order );
			$result['customerInfo']               = self::get_customer_info( $order );
			$result['customerInfo']['carrier']    = self::$settings->get_params( 'fulfill_default_carrier' );
			$result['customerInfo']['order_note'] = self::$settings->get_params( 'fulfill_order_note' );
		}
		wp_send_json( $result );
	}

	/**
	 * @param $order WC_Order
	 *
	 * @return array
	 */
	public function get_order_info( $order ) {
		$result     = array();
		$list_items = $order->get_items();
		foreach ( $list_items as $item_id => $item ) {
			if ( $item->get_meta( '_vi_wad_aliexpress_order_id' ) ) {
				continue;
			}
			$pid     = $item->get_product_id();
			$vid     = $item->get_variation_id();
			$qty     = $item->get_quantity() + $order->get_qty_refunded_for_item( $item_id );
			$ali_pid = get_post_meta( $pid, '_vi_wad_aliexpress_product_id', true );

			if ( $ali_pid ) {
				$title = get_the_title( $pid );
				if ( $vid ) {
					$title    = get_the_title( $vid );
					$ali_vid  = get_post_meta( $vid, '_vi_wad_aliexpress_variation_id', true );
					$sku_attr = get_post_meta( $vid, '_vi_wad_aliexpress_variation_attr', true );
				} else {
					$ali_vid  = get_post_meta( $pid, '_vi_wad_aliexpress_variation_id', true );
					$sku_attr = get_post_meta( $pid, '_vi_wad_aliexpress_variation_attr', true );
				}
				$shipping_company = '';
				$shipping_info    = get_post_meta( $pid, '_vi_wad_shipping_info', true );
				if ( ! empty( $shipping_info['company'] ) ) {
					$shipping_company = $shipping_info['company'];
				}
				$item_exists = false;
				foreach ( $result as &$re ) {
					if ( $re['productID'] == $ali_pid && $re['skuID'] == $ali_vid && $re['skuAttr'] == $sku_attr ) {
						$re['quantity'] += $qty;
						$item_exists    = true;
						break;
					}
				}
				if ( ! $item_exists ) {
					$result[] = array(
						'productID'       => $ali_pid,
						'skuID'           => $ali_vid,
						'skuAttr'         => $sku_attr,
						'quantity'        => $qty,
						'title'           => $title,
						'shippingCompany' => $shipping_company,
					);
				}
			}
		}

//		uasort( $result, array( $this, 'sort_by_product_id' ) );

		return $result;
	}

	/**
	 * @param $order WC_Order
	 *
	 * @return array
	 */
	public static function get_customer_info( $order ) {
		$shipping_country = $order->get_shipping_country();
		$billing_country  = $order->get_billing_country();
		$address_number   = $neighborhood = '';
		if ( $order->has_shipping_address() ) {
			$state_code              = $order->get_shipping_state();
			$city                    = $order->get_shipping_city();
			$address1                = $order->get_shipping_address_1();
			$address2                = $order->get_shipping_address_2();
			$post_code               = $order->get_shipping_postcode();
			$billing_number_meta_key = self::$settings->get_params( 'billing_number_meta_key' );
			if ( $billing_number_meta_key ) {
				$address_number = $order->get_meta( $billing_number_meta_key );
			}
			$billing_neighborhood_meta_key = self::$settings->get_params( 'billing_neighborhood_meta_key' );
			if ( $billing_neighborhood_meta_key ) {
				$neighborhood = $order->get_meta( $billing_neighborhood_meta_key );
			}
		} else {
			$state_code               = $order->get_billing_state();
			$city                     = $order->get_billing_city();
			$address1                 = $order->get_billing_address_1();
			$address2                 = $order->get_billing_address_2();
			$post_code                = $order->get_billing_postcode();
			$shipping_number_meta_key = self::$settings->get_params( 'shipping_number_meta_key' );
			if ( $shipping_number_meta_key ) {
				$address_number = $order->get_meta( $shipping_number_meta_key );
			}
			$shipping_neighborhood_meta_key = self::$settings->get_params( 'shipping_neighborhood_meta_key' );
			if ( $shipping_neighborhood_meta_key ) {
				$neighborhood = $order->get_meta( $shipping_neighborhood_meta_key );
			}
		}
		$woo_country           = $shipping_country ? $shipping_country : $billing_country;
		$country               = self::filter_country( $woo_country );
		$countries             = VI_WOO_ALIDROPSHIP_DATA::get_countries();
		$country_name          = isset( $countries[ $country ] ) ? $countries[ $country ] : '';
		$phone_country         = self::get_phone_country_code( $country );
		$phone                 = $order->get_billing_phone();
		$default_phone_number  = self::$settings->get_params( 'fulfill_default_phone_number' );
		$default_phone_country = self::$settings->get_params( 'fulfill_default_phone_country' );
		if ( $phone && ( ! self::$settings->get_params( 'fulfill_default_phone_number_override' ) || ! $default_phone_number || $country !== $default_phone_country ) ) {
			$phone = str_replace( $phone_country, '', $phone );
			if ( ! $phone_country ) {
				$phone_country = WC()->countries->get_country_calling_code( $woo_country );
			}
		} else {
			$phone = $default_phone_number;
			if ( $default_phone_country ) {
				$phone_country = self::get_phone_country_code( $default_phone_country );
			}
		}
		$states = VI_WOO_ALIDROPSHIP_DATA::get_states( $woo_country );
		$name   = trim( $order->get_formatted_shipping_full_name() );
		if ( ! $name ) {
			$name = trim( $order->get_formatted_billing_full_name() );
		}
		if ( ! $name ) {
			$user = $order->get_user();
			if ( $user ) {
				if ( ! empty( $user->display_name ) ) {
					$name = $user->display_name;
				} elseif ( ! empty( $user->user_nicename ) ) {
					$name = $user->user_nicename;
				} elseif ( ! empty( $user->user_login ) ) {
					$name = $user->user_login;
				}
			}
		}
		
		if ( $state_code ) {
			$state      = isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;
			$ali_states = VI_WOO_ALIDROPSHIP_DATA::get_state( $country );
			if ( count( $ali_states ) ) {
				$address_ = array();

				$search      = VI_WOO_ALIDROPSHIP_DATA::strtolower( $state );
				$search_1    = array( $search, remove_accents( $search ) );
				$found_state = false;
				foreach ( $ali_states['addressList'] as $key => $value ) {
					if ( in_array( VI_WOO_ALIDROPSHIP_DATA::strtolower( $value['n'] ), $search_1, true ) ) {
						$found_state = $key;
						$state       = $value['n'];
						break;
					}
				}
				if ( $found_state === false ) {
					if ( array_search( 'Other', array_column( $ali_states['addressList'], 'n' ) ) !== false ) {
						if ( $state_code ) {
							array_push( $address_, $state );
						}
						$state = 'Other';
					}
				} else {
					if ( isset( $ali_states['addressList'][ $found_state ]['children'] ) && is_array( $ali_states['addressList'][ $found_state ]['children'] ) && count( $ali_states['addressList'][ $found_state ]['children'] ) ) {
						$found_city = false;
						if ( $city ) {
							$search   = VI_WOO_ALIDROPSHIP_DATA::strtolower( $city );
							$search_1 = array( $search, remove_accents( $search ) );
							foreach ( $ali_states['addressList'][ $found_state ]['children'] as $key => $value ) {
								if ( in_array( VI_WOO_ALIDROPSHIP_DATA::strtolower( $value['n'] ), $search_1, true ) ) {
									$found_city = $key;
									$city       = $value['n'];
									break;
								}
							}
						}
						if ( $found_city === false ) {
							$city = ucwords( remove_accents( $city ) );
							if ( array_search( 'Other', array_column( $ali_states['addressList'][ $found_state ]['children'], 'n' ) ) !== false ) {
								array_push( $address_, $city );
								$city = 'Other';
							}
						}
					} else {
						$city = ucwords( remove_accents( $city ) );
					}
				}
				if ( count( $address_ ) ) {
					if ( $address1 ) {
						$address1 = implode( ', ', array_merge( array( $address1 ), $address_ ) );
					}
					if ( $address2 ) {
						$address2 = implode( ', ', array_merge( array( $address2 ), $address_ ) );
					}
				}
			} else {
				$state = isset( $states[ $state_code ] ) ? remove_accents( $states[ $state_code ] ) : ( self::country_support_city_other( $country ) ? 'Other' : $city );
				$city  = ucwords( remove_accents( $city ) );
			}
		} else {
			if ( self::country_support_city_other( $country ) ) {
				$state = 'Other';
			} else {
				$state = $city;
			}
		}

		$result = array(
			'name'         => remove_accents( $name ),
			'fname'        => remove_accents( $order->get_shipping_first_name() ),
			'lname'        => remove_accents( $order->get_shipping_last_name() ),
			'phone'        => VI_WOO_ALIDROPSHIP_DATA::sanitize_phone_number( $phone ),
			'street'       => remove_accents( $address1 ),
			'address2'     => remove_accents( $address2 ),
			'city'         => $city,
			'state_code'   => remove_accents( $state_code ),
			'state'        => $state,
			'country'      => $country,
			'countryName'  => $country_name,
			'postcode'     => $post_code,
			'phoneCountry' => $phone_country,
			'cpf'          => '',
			'rutNo'        => '',
			'fromOrderId'  => $order->get_id()
		);

		if ( $country === 'BR' ) {
			$result['cpf'] = $order->get_shipping_company();
			if ( ! $result['cpf'] ) {
				$result['cpf'] = $order->get_billing_company();
			}
			$cpf_custom_meta_key = self::$settings->get_params( 'cpf_custom_meta_key' );
			if ( $cpf_custom_meta_key ) {
				$cpf_custom_meta = $order->get_meta( $cpf_custom_meta_key );
				if ( $cpf_custom_meta ) {
					$result['cpf'] = $cpf_custom_meta;
				}
			}
		}
		if ( $country === 'CL' ) {
			$rut_meta_key = self::$settings->get_params( 'rut_meta_key' );
			if ( $rut_meta_key ) {
				$rut = $order->get_meta( $rut_meta_key );
				if ( $rut ) {
					$result['rutNo'] = substr( $rut, 0, 12 );
				}
			}
		}
		if ( $address_number ) {
			$result['street'] = "{$result['street']}, {$address_number}";
		}

		if ( $neighborhood ) {
			$neighborhood = remove_accents( $neighborhood );
			if ( $result['address2'] ) {
				$result['street']   = "{$result['street']}, {$result['address2']}";
				$result['address2'] = '';
			}
			$result['street'] = "{$result['street']}, {$neighborhood}";
		}
		if ( $result['cpf'] ) {
			$result['cpf'] = substr( VI_WOO_ALIDROPSHIP_DATA::sanitize_phone_number( $result['cpf'] ), 0, 11 );
		}

		return apply_filters( 'vi_wad_fulfillment_customer_info', $result, $order );
	}

	public static function filter_country( $country ) {
		switch ( $country ) {
			case 'AQ':
			case 'BV':
			case 'IO':
			case 'CU':
			case 'TF':
			case 'HM':
			case 'IR':
			case 'IM':
			case 'SH':
			case 'PN':
			case 'SD':
			case 'SJ':
			case 'SY':
			case 'TK':
			case 'UM':
			case 'EH':
				$country = 'OTHER';
				break;
			case 'AX':
				$country = 'ALA';
				break;
			case 'CN':
				$country = 'HK';
				break;
			case 'CD':
				$country = 'ZR';
				break;
			case 'GG':
				$country = 'GGY';
				break;
			case 'JE':
				$country = 'JEY';
				break;
			case 'ME':
				$country = 'MNE';
				break;
			case 'KP':
				$country = 'KR';
				break;
			case 'BL':
				$country = 'BLM';
				break;
			case 'MF':
				$country = 'MAF';
				break;
			case 'RS':
				$country = 'SRB';
				break;
			case 'GS':
				$country = 'SGS';
				break;
			case 'TL':
				$country = 'TLS';
				break;
			case 'GB':
				$country = 'UK';
				break;
			default:
		}

		return $country;
	}

	private static function country_support_city_other( $country_code ) {
		return in_array( $country_code, array(
			'BR',
			'CL',
			'CZ',
			'FR',
			'IN',
			'ID',
			'IT',
			'KZ',
			'KR',
			'NL',
			'NZ',
			'PL',
			'RU',
			'SA',
			'ES',
			'TR',
			'UA',
			'UK',
			'US',
			'TH',
			'PE',
			'DE',
			'NG',
			'CO',
			'JP',
			'MA',
			'AE',
			'LK',
			'VN',
			'PK',
			'AU',
			'AT',
			'MY',
			'IL',
			'PT',
			'BE',
		) );
	}

	public static function get_phone_country_code( $country = '' ) {
		$map = '{"VU":"+678","EC":"+593","VN":"+84","VI":"","DZ":"+213","VG":"+1 (284)","DM":"+1 (767)","VE":"+58","DO":"+1 (8)","VC":"+1 (784)","VA":"+39 (066)","DE":"+49","UZ":"+998","UY":"+598","DK":"+45","DJ":"+253","US":"+1","UM":"","UG":"+256","UA":"+380","ET":"+251","ES":"+34","ER":"+291","EH":"+212","EG":"+20","TZ":"+255","EE":"+372","TT":"+1 (868)","TV":"+688","GD":"+1 (473)","GE":"+995","GF":"+594","GA":"+241","ASC":"","GB":"+44","FR":"+33","FO":"+298","FK":"+500","FJ":"+679","FM":"+691","FI":"+358","WS":"+685","GY":"+592","GW":"+245","GU":"","GT":"+502","GR":"+30","GQ":"+240","WF":"+681","GP":"+590","GN":"+224","GM":"+220","GL":"+299","GI":"+350","GH":"+233","GG":"+44","RE":"+262","RO":"+40","AT":"+43","AS":"","AR":"+54","AQ":"","AX":"","AW":"+297","QA":"+974","AU":"+61","AZ":"+994","BA":"+387","PT":"+351","AD":"+376","PW":"+680","AG":"+1 (268)","PR":"+1","AE":"+971","PS":"","AF":"","AL":"+355","AI":"","AO":"+244","PY":"+595","AM":"+374","AN":"","TG":"+228","BW":"+267","TF":"","BV":"","BY":"+375","TD":"+235","BS":"+1 (242)","TK":"","TJ":"+992","BR":"+55","TH":"+66","BT":"+975","TO":"+676","TN":"+216","TM":"+993","CA":"+1","TL":"+670","BZ":"+501","TR":"+90","BF":"+226","SV":"+503","BG":"+359","SS":"+211","BH":"+973","ST":"+239","BI":"+257","SY":"+963","BB":"+1 (246)","SZ":"+268","BD":"+880","SX":"+590","BE":"+32","BN":"+673","BO":"+591","BQ":"","BJ":"+229","TC":"+1 (649)","BL":"","BM":"+1 (441)","CZ":"+420","SD":"+249","CY":"+357","SC":"+248","CX":"","CW":"","SE":"+46","CV":"+238","SH":"","CU":"+53","SG":"+65","SJ":"+47","SI":"+386","SL":"+232","SK":"+421","SN":"+221","SM":"+378","SO":"+252","SGS":"","SR":"+597","CI":"+225","RS":"+381","CG":"+242","CH":"+41","RU":"+7","CF":"+236","RW":"+250","CC":"","CD":"+243","CR":"+506","CO":"+57","CM":"+237","CN":"+86","CK":"","SA":"+966","CL":"+56","SB":"+677","LV":"+371","LU":"+352","LT":"+370","LY":"+218","LS":"+266","LR":"+231","MG":"+261","MH":"+692","ME":"+382","MF":"","MK":"+389","ML":"+223","MC":"+377","MD":"+373","MA":"+212","MV":"+960","MU":"+230","MX":"+52","MW":"+265","MZ":"+258","MY":"+60","MN":"+976","MM":"+95","MP":"","MR":"+222","MQ":"+596","MT":"+356","MS":"","NF":"","NG":"+234","NI":"+505","NL":"+31","NA":"+264","NC":"+687","NE":"+227","NZ":"+64","NU":"","NR":"+674","NP":"+977","NO":"+47","OM":"+968","PL":"+48","PM":"+508","PN":"","PH":"+63","PK":"+92","PE":"+51","PF":"+689","PG":"+675","PA":"+507","ZA":"+27","HN":"+504","HM":"","HR":"+385","EAZ":"","HT":"+509","HU":"+36","ZM":"+260","ID":"+62","ZW":"+263","IE":"+353","IL":"+972","IM":"+44","IN":"+91","IO":"","IQ":"+964","IR":"+98","YE":"+967","IS":"+354","IT":"+39","JE":"+44","YT":"+262","JP":"+81","JO":"+962","JM":"+1 (876)","KI":"+686","KH":"","KG":"+996","KE":"+254","GBA":"","KP":"+850","KR":"+82","KM":"+269","KN":"+1 (869)","KW":"+965","KY":"+1 (345)","KZ":"+77","KS":"","LA":"+856","LC":"+1 (758)","LB":"+961","LI":"+423","LK":"+94"}';
		$map = vi_wad_json_decode( $map );
		if ( $country ) {
			return isset( $map[ $country ] ) ? $map[ $country ] : '';
		} else {
			return $map;
		}
	}
}