<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOO_ALIDROPSHIP_Admin_Settings
 */
class VI_WOO_ALIDROPSHIP_Admin_Settings {
	private static $settings;
	private $orders_tracking_active;

	public function __construct() {
		self::$settings               = VI_WOO_ALIDROPSHIP_DATA::get_instance();
		$this->orders_tracking_active = false;
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_wad_search_product', array( $this, 'search_product' ) );
		add_action( 'wp_ajax_wad_search_cate', array( $this, 'search_cate' ) );
		add_action( 'wp_ajax_wad_search_tags', array( $this, 'search_tags' ) );
		add_action( 'wp_ajax_wad_format_price_rules_test', array( $this, 'format_price_rules_test' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 999999 );
	}

	public function search_product() {
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keyword              = isset( $_GET['keyword'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
		$exclude_ali_products = isset( $_GET['exclude_ali_products'] ) ? sanitize_text_field( $_GET['exclude_ali_products'] ) : '';
		if ( empty( $keyword ) ) {
			die();
		}
		$post_status = array( 'publish' );
		if ( current_user_can( 'edit_private_products' ) ) {
			if ( $exclude_ali_products ) {
				$post_status = array(
					'private',
					'draft',
					'pending',
					'publish'
				);
			} else {
				$post_status = array(
					'private',
					'publish'
				);
			}
		}
		$arg = array(
			'post_type'      => 'product',
			'posts_per_page' => 50,
			's'              => $keyword,
			'post_status'    => apply_filters( 'vi_wad_search_product_statuses', $post_status ),
			'fields'         => 'ids',
		);
		if ( $exclude_ali_products ) {
			$arg['meta_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_vi_wad_aliexpress_product_id',
					'compare' => 'NOT EXISTS'
				)
			);
		}
		$the_query      = new WP_Query( $arg );
		$found_products = array();
		if ( $the_query->have_posts() ) {
			foreach ( $the_query->posts as $product_id ) {
				$found_products[] = array(
					'id'   => $product_id,
					'text' => "(#{$product_id}) " . get_the_title( $product_id )
				);
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( $found_products );
	}

	public function format_price_rules_test() {
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		global $wooaliexpressdropship_settings;
		$price                                                = isset( $_GET['format_price_rules_test'] ) ? sanitize_text_field( $_GET['format_price_rules_test'] ) : '';
		$format_price_rules                                   = isset( $_GET['format_price_rules'] ) ? stripslashes_deep( $_GET['format_price_rules'] ) : array();
		$wooaliexpressdropship_settings['format_price_rules'] = $format_price_rules;
		self::$settings                                       = VI_WOO_ALIDROPSHIP_DATA::get_instance( true );
		$applied                                              = VI_WOO_ALIDROPSHIP_DATA::format_price( $price );
		if ( count( $applied ) ) {
			$result = sprintf( esc_html__( '%1$s => Applied rule number: %2$s', 'woo-alidropship' ), $price, implode( ',', array_map( array( __CLASS__,'increase_by_one'), $applied ) ) );//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		} else {
			$result = sprintf( esc_html__( '%s => No rule matched', 'woo-alidropship' ), $price );//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( array( 'result' => $result, 'applied' => $applied ) );
	}

	public static function increase_by_one( $number ) {
		$number = intval( $number );
		$number ++;

		return $number;
	}

	public function admin_notices() {
		$errors              = array();
		$permalink_structure = get_option( 'permalink_structure' );
		if ( ! $permalink_structure ) {
			$errors[] = sprintf(__( 'You are using Permalink structure as Plain. Please go to <a href="%s" target="_blank">Permalink Settings</a> to change it.', 'woo-alidropship' ), admin_url( 'options-permalink.php' ));//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		}
		if ( ! is_ssl() ) {
			$errors[] = __( 'Your site is not using HTTPS. For more details, please read <a target="_blank" href="https://wordpress.org/documentation/article/https-for-wordpress/">HTTPS for WordPress</a>', 'woo-alidropship' );
		}
		if ( count( $errors ) ) {
			?>
            <div class="error">
                <h3><?php echo wp_kses_post(_n( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce: you can not import products or fulfil AliExpress orders unless below issue is resolved', 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce: you can not import products or fulfil AliExpress orders unless below issues are resolved', count( $errors ), 'woo-alidropship' )); ?></h3>
				<?php
				foreach ( $errors as $error ) {
					?>
                    <p><?php echo wp_kses( $error, VI_WOO_ALIDROPSHIP_DATA::allow_html() );  ?></p>
					<?php
				}
				?>
            </div>
			<?php
		}
	}

	private static function set( $name, $set_name = false ) {
		return VI_WOO_ALIDROPSHIP_DATA::set( $name, $set_name );
	}

	public function search_tags() {
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keyword    = isset($_GET['keyword'])? sanitize_text_field($_GET['keyword']):'';
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'orderby'    => 'name',
				'order'      => 'ASC',
				'search'     => $keyword,
				'hide_empty' => false
			)
		);
		$items      = array();
		$items[]    = array( 'id' => $keyword, 'text' => $keyword );
		if ( count( $categories ) ) {
			foreach ( $categories as $category ) {
				$item    = array(
					'id'   => $category->name,
					'text' => $category->name
				);
				$items[] = $item;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( $items );
	}

	public function search_cate() {
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keyword    = isset($_GET['keyword'])? sanitize_text_field($_GET['keyword']):'';
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'orderby'    => 'name',
				'order'      => 'ASC',
				'search'     => $keyword,
				'hide_empty' => false
			)
		);
		$items      = array();
		if ( count( $categories ) ) {
			foreach ( $categories as $category ) {
				$item    = array(
					'id'   => $category->term_id,
					'text' => VI_WOO_ALIDROPSHIP_Admin_Import_List::build_category_name( $category->name, $category )
				);
				$items[] = $item;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( $items );
	}

	public function admin_enqueue_scripts() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		global $pagenow;
		if ( $pagenow === 'admin.php' && $page === 'woo-alidropship' ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
			self::enqueue_semantic();
			wp_enqueue_style( 'woo-alidropship-admin-style', VI_WOO_ALIDROPSHIP_CSS . 'admin.css', '', VI_WOO_ALIDROPSHIP_VERSION );
			wp_enqueue_script( 'woo-alidropship-admin', VI_WOO_ALIDROPSHIP_JS . 'admin.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION , false);
			$decimals = wc_get_price_decimals();
			wp_localize_script( 'woo-alidropship-admin', 'vi_wad_admin_settings_params', array(
				'decimals'                    => wc_get_price_decimals(),
				'url'                         => admin_url( 'admin-ajax.php' ),
				'_vi_wad_ajax_nonce'          => self::create_ajax_nonce(),
				'i18n_error_max_digit'        => esc_html__( 'Maximum {value} digit', 'woo-alidropship' ),
				'i18n_error_max_digits'       => esc_html__( 'Maximum {value} digits', 'woo-alidropship' ),
				'i18n_error_digit_only'       => esc_html__( 'Numerical digit only', 'woo-alidropship' ),
				'i18n_error_digit_and_x_only' => esc_html__( 'Numerical digit & X only', 'woo-alidropship' ),
				'i18n_error_min_digits'       => esc_html__( 'Minimum 2 digits', 'woo-alidropship' ),
				'i18n_error_min_max'          => esc_html__( 'Min can not > max', 'woo-alidropship' ),
				'i18n_error_max_min'          => esc_html__( 'Max can not < min', 'woo-alidropship' ),
				'i18n_error_max_decimals'     => sprintf( _n( 'Max decimal: %s', 'Max decimals: %s', $decimals, 'woo-alidropship' ), '<a target="_blank" href="admin.php?page=wc-settings#woocommerce_price_num_decimals">' . $decimals . '</a>' ),//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			) );
		}
	}

	public static function enqueue_semantic() {
		wp_dequeue_script( 'select-js' );//Causes select2 error, from ThemeHunk MegaMenu Plus plugin
		wp_dequeue_style( 'eopa-admin-css' );
		/*Stylesheet*/
		wp_enqueue_style( 'woo-alidropship-message', VI_WOO_ALIDROPSHIP_CSS . 'message.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-input', VI_WOO_ALIDROPSHIP_CSS . 'input.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-label', VI_WOO_ALIDROPSHIP_CSS . 'label.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-image', VI_WOO_ALIDROPSHIP_CSS . 'image.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-transition', VI_WOO_ALIDROPSHIP_CSS . 'transition.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-form', VI_WOO_ALIDROPSHIP_CSS . 'form.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-icon', VI_WOO_ALIDROPSHIP_CSS . 'icon.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-dropdown', VI_WOO_ALIDROPSHIP_CSS . 'dropdown.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
		wp_enqueue_style( 'woo-alidropship-checkbox', VI_WOO_ALIDROPSHIP_CSS . 'checkbox.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
		wp_enqueue_style( 'woo-alidropship-segment', VI_WOO_ALIDROPSHIP_CSS . 'segment.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
		wp_enqueue_style( 'woo-alidropship-menu', VI_WOO_ALIDROPSHIP_CSS . 'menu.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
		wp_enqueue_style( 'woo-alidropship-tab', VI_WOO_ALIDROPSHIP_CSS . 'tab.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-table', VI_WOO_ALIDROPSHIP_CSS . 'table.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-button', VI_WOO_ALIDROPSHIP_CSS . 'button.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-grid', VI_WOO_ALIDROPSHIP_CSS . 'grid.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
		wp_enqueue_style( 'woo-alidropship-accordion', VI_WOO_ALIDROPSHIP_CSS . 'accordion.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-dimmer', VI_WOO_ALIDROPSHIP_CSS . 'dimmer.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-modal', VI_WOO_ALIDROPSHIP_CSS . 'modal.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		wp_enqueue_style( 'woo-alidropship-card', VI_WOO_ALIDROPSHIP_CSS . 'card.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
		wp_enqueue_script( 'woo-alidropship-transition', VI_WOO_ALIDROPSHIP_JS . 'transition.min.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false );
		wp_enqueue_script( 'woo-alidropship-dropdown', VI_WOO_ALIDROPSHIP_JS . 'dropdown.min.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false  );
		wp_enqueue_script( 'woo-alidropship-checkbox', VI_WOO_ALIDROPSHIP_JS . 'checkbox.js', array( 'jquery' ) , VI_WOO_ALIDROPSHIP_VERSION, false );
		wp_enqueue_script( 'woo-alidropship-tab', VI_WOO_ALIDROPSHIP_JS . 'tab.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false  );
		wp_enqueue_script( 'woo-alidropship-accordion', VI_WOO_ALIDROPSHIP_JS . 'accordion.min.js', array( 'jquery' ) , VI_WOO_ALIDROPSHIP_VERSION, false );
		wp_enqueue_script( 'woo-alidropship-dimmer', VI_WOO_ALIDROPSHIP_JS . 'dimmer.min.js', array( 'jquery' ) , VI_WOO_ALIDROPSHIP_VERSION, false );
		wp_enqueue_script( 'woo-alidropship-modal', VI_WOO_ALIDROPSHIP_JS . 'modal.min.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false  );
		wp_enqueue_script( 'woo-alidropship-address', VI_WOO_ALIDROPSHIP_JS . 'jquery.address-1.6.min.js', array( 'jquery' ) , VI_WOO_ALIDROPSHIP_VERSION, false );
		wp_enqueue_style( 'select2', VI_WOO_ALIDROPSHIP_CSS . 'select2.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		if ( woocommerce_version_check( '3.0.0' ) ) {
			wp_enqueue_script( 'select2' );
		} else {
			wp_enqueue_script( 'select2-v4', VI_WOO_ALIDROPSHIP_JS . 'select2.js', array( 'jquery' ), '4.0.3' , false);
		}
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( is_plugin_active( 'woo-orders-tracking/woo-orders-tracking.php' ) || is_plugin_active( 'woocommerce-orders-tracking/woocommerce-orders-tracking.php' ) ) {
			$this->orders_tracking_active = true;
		}
		global $wooaliexpressdropship_settings;
		if ( isset( $_POST['vi_wad_save_settings'] ) && isset( $_POST['_wooaliexpressdropship_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_wooaliexpressdropship_nonce'] ), 'wooaliexpressdropship_save_settings' ) ) {
			$args = self::$settings->get_params();
			if ( isset( $_REQUEST['vi_wad_setup_wizard'] ) ) {
				foreach ( $args as $key => $arg ) {
					if ( isset( $_POST[ 'wad_' . $key ] ) ) {
						if ( is_array( $_POST[ 'wad_' . $key ] ) ) {
							$args[ $key ] = isset( $_POST[ 'wad_' . $key ] ) ? ( stripslashes_deep( $_POST[ 'wad_' . $key ] ) ) : '';
						} else if ( in_array( $key, array( 'fulfill_order_note' ) ) ) {
							$args[ $key ] = stripslashes( wp_kses_post( $_POST[ 'wad_' . $key ] ) );
						} else {
							$args[ $key ] = sanitize_text_field( $_POST[ 'wad_' . $key ] );
						}
					} elseif ( in_array( $key, array(
						'show_shipping_option',
						'shipping_cost_after_price_rules',
						'use_external_image',
						'use_global_attributes',
					) ) ) {
						$args[ $key ] = '';
					}
				}
			} else {
				foreach ( $args as $key => $arg ) {
					if ( isset( $_POST[ 'wad_' . $key ] ) ) {
						if ( is_array( $_POST[ 'wad_' . $key ] ) ) {
							$args[ $key ] = isset( $_POST[ 'wad_' . $key ] ) ? ( stripslashes_deep( $_POST[ 'wad_' . $key ] ) ) : '';
						} else if ( in_array( $key, array( 'fulfill_order_note' ) ) ) {
							$args[ $key ] = stripslashes( wp_kses_post( $_POST[ 'wad_' . $key ] ) );
						} else {
							$args[ $key ] = sanitize_text_field( $_POST[ 'wad_' . $key ] );
						}
					} else {
						if ( is_array( $arg ) ) {
							$args[ $key ] = array();
						} else {
							$args[ $key ] = '';
						}
					}
				}
			}
			/*Format price rules*/
			if ( ! empty( $args['format_price_rules']['from'] ) && is_array( $args['format_price_rules']['from'] ) ) {
				$format_price_rules = array();
				for ( $i = 0; $i < count( $args['format_price_rules']['from'] ); $i ++ ) {
					$format_price_rules[] = array(
						'from'       => $args['format_price_rules']['from'][ $i ],
						'to'         => $args['format_price_rules']['to'][ $i ],
						'part'       => $args['format_price_rules']['part'][ $i ],
						'value_from' => $args['format_price_rules']['value_from'][ $i ],
						'value_to'   => $args['format_price_rules']['value_to'][ $i ],
						'value'      => $args['format_price_rules']['value'][ $i ],
					);
				}
				$args['format_price_rules'] = $format_price_rules;
			}

			if ( ! empty( $args['string_replace']['from_string'] ) && is_array( $args['string_replace']['from_string'] ) ) {
				$strings          = $args['string_replace']['from_string'];
				$strings_replaces = array(
					'from_string' => array(),
					'to_string'   => array(),
					'sensitive'   => array(),
				);
				$count            = count( $strings );
				for ( $i = 0; $i < $count; $i ++ ) {
					if ( $strings[ $i ] !== '' ) {
						$strings_replaces['from_string'][] = $args['string_replace']['from_string'][ $i ];
						$strings_replaces['to_string'][]   = $args['string_replace']['to_string'][ $i ];
						$strings_replaces['sensitive'][]   = $args['string_replace']['sensitive'][ $i ];
					}
				}
				$args['string_replace'] = $strings_replaces;
			}
			$args['carrier_name_replaces'] = isset( $_POST['vi-wad-carrier_name_replaces'] ) ? self::stripslashes_deep( $_POST['vi-wad-carrier_name_replaces'] ) : array(
				'from_string' => array(),
				'to_string'   => array(),
				'sensitive'   => array(),
			);

			if ( ! empty( $args['carrier_name_replaces']['from_string'] ) && is_array( $args['carrier_name_replaces']['from_string'] ) ) {
				$strings_replaces = array(
					'from_string' => array(),
					'to_string'   => array(),
					'sensitive'   => array(),
				);
				$count            = count( $args['carrier_name_replaces']['from_string'] );
				for ( $i = 0; $i < $count; $i ++ ) {
					if ( $args['carrier_name_replaces']['from_string'][ $i ] !== '' ) {
						$strings_replaces['from_string'][] = $args['carrier_name_replaces']['from_string'][ $i ];
						$strings_replaces['to_string'][]   = $args['carrier_name_replaces']['to_string'][ $i ];
						$strings_replaces['sensitive'][]   = $args['carrier_name_replaces']['sensitive'][ $i ];
					}
				}
				$args['carrier_name_replaces'] = $strings_replaces;
			}
			$args['carrier_url_replaces'] = isset( $_POST['vi-wad-carrier_url_replaces'] ) ? self::stripslashes_deep( $_POST['vi-wad-carrier_url_replaces'] ) : array(
				'from_string' => array(),
				'to_string'   => array(),
				'sensitive'   => array(),
			);
			if ( ! empty( $args['carrier_url_replaces']['from_string'] ) && is_array( $args['carrier_url_replaces']['from_string'] ) ) {
				$strings_replaces = array(
					'from_string' => array(),
					'to_string'   => array(),
				);
				$count            = count( $args['carrier_url_replaces']['from_string'] );
				for ( $i = 0; $i < $count; $i ++ ) {
					if ( $args['carrier_url_replaces']['from_string'][ $i ] !== '' && $args['carrier_url_replaces']['to_string'][ $i ] !== '' ) {
						$strings_replaces['from_string'][] = $args['carrier_url_replaces']['from_string'][ $i ];
						$strings_replaces['to_string'][]   = esc_url_raw( $args['carrier_url_replaces']['to_string'][ $i ] );
					}
				}
				$args['carrier_url_replaces'] = $strings_replaces;
			}
			update_option( 'wooaliexpressdropship_params', $args );
			$wooaliexpressdropship_settings = $args;

			self::$settings = VI_WOO_ALIDROPSHIP_DATA::get_instance( true );
			if ( isset( $_POST['vi_wad_setup_redirect'] ) ) {
				$url_redirect = esc_url_raw( $_POST['vi_wad_setup_redirect'] );
				wp_safe_redirect( $url_redirect );
				exit;
			}
		}
	}

	private static function stripslashes_deep( $value ) {
		if ( is_array( $value ) ) {
			$value = array_map( 'stripslashes_deep', $value );
		} else {
			$value = wp_kses_post( stripslashes( $value ) );
		}

		return $value;
	}

	/**
	 *
	 */
	public function page_callback() {
		global $wpdb;
		$shipping_companies = VI_WOO_ALIDROPSHIP_DATA::get_shipping_companies();
		?>
        <div class="wrap woo-alidropship">
            <h2><?php esc_html_e( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce Settings', 'woo-alidropship' ) ?></h2>
			<?php
			if ( ! get_option( 'ald_deleted_old_posts_data' ) ) {
				?>
				<div class="vi-ui message info">
					<a href="#ald-migrate-table">
						<?php esc_html_e( 'Migrate import list/imported to new data table', 'woo-alidropship' ); ?>
					</a>
				</div>
				<?php
			}
			$messages = array();
			if ( VI_WOO_ALIDROPSHIP_DATA::get_disable_wp_cron() ) {
				$messages[] = __( '<strong>DISABLE_WP_CRON</strong> is set to true, product images may not be downloaded properly. Please try option <strong>"Disable background process"</strong>', 'woo-alidropship' );
			}
			if ( is_plugin_active( 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ) {
				foreach (
					array(
						'cpf_custom_meta_key',
						'billing_number_meta_key',
						'shipping_number_meta_key',
						'billing_neighborhood_meta_key',
						'shipping_neighborhood_meta_key'
					) as $br_custom_field
				) {
					if ( ! self::$settings->get_params( $br_custom_field ) ) {
						$messages[] = __( 'Some extra checkout fields are not configured which may lead to incorrect address of Brazilian customers when fulfilling AliExpress orders. Please go to <a href="#fulfill">Fulfill</a> tab to configure CPF, Billing/Shipping number and neighborhood meta fields. If you already use your own code to handle these custom fields, please ignore this warning.', 'woo-alidropship' );
						break;
					}
				}
			}
			if ( $messages ) {
				?>
                <div class="vi-ui message negative">
                    <div class="header"><?php esc_html_e( 'ALD - Warning', 'woo-alidropship' ) ?>:</div>
                    <ul class="list">
						<?php
						foreach ( $messages as $message ) {
							?>
                            <li><?php echo wp_kses( $message , VI_WOO_ALIDROPSHIP_DATA::allow_html()) ?></li>
							<?php
						}
						?>
                    </ul>
                </div>
				<?php
			}
			?>
            <form method="post" action="" class="vi-ui form">
				<?php $this->set_nonce() ?>
                <div class="vi-ui attached tabular menu">
                    <div class="item active" data-tab="general">
						<?php esc_html_e( 'General', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item" data-tab="products">
						<?php esc_html_e( 'Products', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item" data-tab="price">
						<?php esc_html_e( 'Product Price', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item <?php self::set_params( 'tab-item', true ) ?>" data-tab="attributes">
						<?php esc_html_e( 'Product Attributes', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item <?php self::set_params( 'tab-item', true ) ?>" data-tab="product_update">
						<?php esc_html_e( 'Product Sync', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item" data-tab="override">
						<?php esc_html_e( 'Product Overriding', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item" data-tab="fulfill">
						<?php esc_html_e( 'Fulfill', 'woo-alidropship' ) ?>
                    </div>
                    <div class="item <?php self::set_params( 'tab-item', true ) ?>" data-tab="shipping">
						<?php esc_html_e( 'Frontend Shipping', 'woo-alidropship' ) ?>
                    </div>
					<?php
					if ( $this->orders_tracking_active ) {
						?>
                        <div class="item" data-tab="tracking_carrier">
							<?php esc_html_e( 'Tracking Carrier', 'woo-alidropship' ) ?>
                        </div>
						<?php
					}
					?>
                </div>
                <div class="vi-ui bottom attached tab segment active" data-tab="general">
                    <div class="vi-ui message positive">
                        <ul class="list">
                            <li><?php echo wp_kses_post(__( 'Since version 1.0.2 of <a href="https://downloads.villatheme.com/?download=alidropship-extension" target="_blank">WooCommerce AliExpress Dropshipping Extension</a>, you can authenticate your extension using WooCommerce REST API authentication(recommended). To edit or revoke your APIs, please go to <a href="admin.php?page=wc-settings&tab=advanced&section=keys" target="_blank">WooCommerce settings/Advanced/REST API</a>', 'woo-alidropship' )) ?></li>
                            <li><?php echo wp_kses_post(__( 'Connecting with extension using secret key may be deprecated in an update in the near future.', 'woo-alidropship' )) ?></li>
                        </ul>
                    </div>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'enable', true ) ?>">
									<?php esc_html_e( 'Enable', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'enable', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'enable' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'enable' ) ?>"/>
                                    <label><?php esc_html_e( 'You need to enable this to let WooCommerce AliExpress Dropshipping Extension connect to your store', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'secret_key', true ) ?>"><?php esc_html_e( 'Secret key', 'woo-alidropship' ) ?></label>
                            </th>
                            <td class="vi-wad relative">
                                <div class="vi-ui left labeled input fluid">
                                    <label class="vi-ui label">
                                        <div class="vi-wad-buttons-group">
                                            <span class="vi-wad-copy-secretkey"
                                                  title="<?php esc_attr_e( 'Copy Secret key', 'woo-alidropship' ) ?>">
                                                <i class="dashicons dashicons-admin-page"></i>
                                            </span>
                                            <span class="vi-wad-generate-secretkey"
                                                  title="<?php esc_attr_e( 'Generate new key', 'woo-alidropship' ) ?>">
                                                <i class="dashicons dashicons-image-rotate"></i>
                                            </span>
                                        </div>
                                    </label>
                                    <input type="text" name="<?php self::set_params( 'secret_key' ) ?>"
                                           value="<?php echo esc_attr( self::$settings->get_params( 'secret_key' ) ) ?>"
                                           id="<?php self::set_params( 'secret_key', true ) ?>"
                                           class="<?php self::set_params( 'secret_key', true ) ?>">
                                </div>
                                <p><?php esc_html_e( 'Secret key is one of the two ways to connect the chrome extension with your store. The other way is to use WooCommerce authentication.', 'woo-alidropship' ) ?></p>
                                <p class="vi-wad-connect-extension-desc vi-wad-hidden"><?php esc_html_e( 'To let the chrome extension connect with this store, please click the "Connect the Extension" button below.', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                            </th>
                            <td>
                                <p>
                                    <a href="https://downloads.villatheme.com/?download=alidropship-extension"
                                       target="_blank">
										<?php esc_html_e( 'Add WooCommerce AliExpress Dropshipping Extension', 'woo-alidropship' ); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <div class="vi-ui styled fluid accordion">
                                    <div class="title active">
                                        <i class="dropdown icon"> </i>
										<?php esc_html_e( 'Install and connect the chrome extension', 'woo-alidropship' ) ?>
                                    </div>
                                    <div class="content active" style="text-align: center">
                                        <iframe width="560" height="315"
                                                src="https://www.youtube-nocookie.com/embed/eO_C_b4ZQmo"
                                                title="YouTube video player" frameborder="0"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen></iframe>
                                    </div>
                                    <div class="title">
                                        <i class="dropdown icon"> </i>
										<?php esc_html_e( 'How to use this plugin?', 'woo-alidropship' ) ?>
                                    </div>
                                    <div class="content" style="text-align: center">
                                        <iframe width="560" height="315"
                                                src="https://www.youtube-nocookie.com/embed/eCt8sJVsBXk" frameborder="0"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen></iframe>
                                    </div>
                                </div>
                            </td>
                        </tr>

						<?php
						if ( ! get_option( 'ald_deleted_old_posts_data' ) ) {
							?>
							<tr id="ald-migrate-table">
								<th>
									<label><?php esc_html_e( 'Use new table for Ali product', 'woo-alidropship' ) ?></label>
								</th>
								<td>
									<?php
									$migrate_process = VI_WOO_ALIDROPSHIP_Admin_Migrate_New_Table::migrate_process();
									if ( ! get_option( 'ald_migrated_to_new_table' ) ) {
										if ( ! $migrate_process->is_queue_empty() || $migrate_process->is_process_running() ) {
											$count_ali_post_type = array_sum( (array) wp_count_posts( 'vi_wad_draft_product' ) );
											if ( $count_ali_post_type ) {
												$migrated = $wpdb->get_var( "select count(*) from {$wpdb->ald_posts}" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
												$remain   = $count_ali_post_type - $migrated;
												if ( $remain ) {
													printf( "<div class='vi-ui message red '><b>%s %s</b></div>",
														esc_html( $remain ),
														esc_html__( 'items remaining in the migration process. New Ali products cannot be added while the process is ongoing.', 'woo-alidropship' ) );
												}
											}
										} else {
											?>
											<button type="button" class="vi-ui button ald-migrate-to-new-table blue">
												<?php esc_html_e( 'Migrate & use new table', 'woo-alidropship' ); ?>
											</button>
											<?php
										}
									} else {
										?>
										<div class="vi-ui toggle checkbox">
											<input id="<?php self::set_params( 'ald_table', true ) ?>"
											       type="checkbox" <?php checked( self::$settings->get_params( 'ald_table' ), 1 ) ?>
											       tabindex="0" class="<?php self::set_params( 'ald_table', true ) ?>"
											       value="1"
											       name="<?php self::set_params( 'ald_table' ) ?>"/>
											<label><?php esc_html_e( 'Change to use data from new table', 'woo-alidropship' ) ?></label>
											<br>
										</div>
										<?php
										if ( ! $migrate_process->is_queue_empty() || $migrate_process->is_process_running() ) {
											printf( "<div class='vi-ui message red '><b>%s</b></div>",
												esc_html__( 'Deleting old data in background', 'woo-alidropship' ) );
										} else {
											$count_ali_post_type = array_sum( (array) wp_count_posts( 'vi_wad_draft_product' ) );
											if ( $count_ali_post_type || ! get_option( 'ald_deleted_old_posts_data' ) ) {
												?>
												<div>
													<button type="button" class="vi-ui button ald-migrate-remove-old-data red">
														<?php esc_html_e( 'Remove old data in posts table', 'woo-alidropship' ); ?>
													</button>
													<p><?php esc_html_e( 'Note: You should backup data before doing this action', 'woo-alidropship' ) ?></p>
												</div>
												<?php
											}
										}
									}
									?>
								</td>
							</tr>
							<?php
						}

						?>
                        </tbody>
                    </table>
                </div>

                <div class="vi-ui bottom attached tab segment " data-tab="products">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_status', true ) ?>"><?php esc_html_e( 'Product status', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'product_status' ) ?>"
                                        id="<?php self::set_params( 'product_status', true ) ?>"
                                        class="<?php self::set_params( 'product_status', true ) ?> vi-ui fluid dropdown">
                                    <option value="publish" <?php selected( self::$settings->get_params( 'product_status' ), 'publish' ) ?>><?php esc_html_e( 'Publish', 'woo-alidropship' ) ?></option>
                                    <option value="pending" <?php selected( self::$settings->get_params( 'product_status' ), 'pending' ) ?>><?php esc_html_e( 'Pending', 'woo-alidropship' ) ?></option>
                                    <option value="draft" <?php selected( self::$settings->get_params( 'product_status' ), 'draft' ) ?>><?php esc_html_e( 'Draft', 'woo-alidropship' ) ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Imported products status will be set to this value.', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'use_global_attributes', true ) ?>">
									<?php esc_html_e( 'Use global attributes', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'use_global_attributes', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'use_global_attributes' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'use_global_attributes' ) ?>"/>
                                    <label><?php echo wp_kses_post(__( 'Global attributes will be used instead of custom attributes. More detail about <a href="https://woocommerce.com/document/managing-product-taxonomies/#product-attributes" target="_blank">Product attributes</a>', 'woo-alidropship' )) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'show_shipping_option', true ) ?>">
									<?php esc_html_e( 'Show shipping option', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'show_shipping_option', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'show_shipping_option' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'show_shipping_option' ) ?>"/>
                                    <label><?php esc_html_e( 'Shipping cost will be added to price of original product. You can select shipping country/company to calculate shipping cost of products before importing.', 'woo-alidropship' ) ?></label>
                                </div>
                                <p class="description"><?php echo wp_kses_post(__( '<strong>*Note:</strong> The shipping cost will be calculated based on when the product is added to the import list.', 'woo-alidropship') ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'shipping_cost_after_price_rules', true ) ?>">
									<?php esc_html_e( 'Add shipping cost after price rules', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'shipping_cost_after_price_rules', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'shipping_cost_after_price_rules' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'shipping_cost_after_price_rules' ) ?>"/>
                                    <label><?php esc_html_e( 'Shipping cost will be added to price of original product after applying price rules.', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'simple_if_one_variation', true ) ?>">
									<?php esc_html_e( 'Import as simple product', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'simple_if_one_variation', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'simple_if_one_variation' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'simple_if_one_variation' ) ?>"/>
                                    <label><?php esc_html_e( 'If a product has only 1 variation or you select only 1 variation to import, that product will be imported as simple product. Variation sku and attributes will not be used.', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'catalog_visibility', true ) ?>"><?php esc_html_e( 'Catalog visibility', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'catalog_visibility' ) ?>"
                                        id="<?php self::set_params( 'catalog_visibility', true ) ?>"
                                        class="<?php self::set_params( 'catalog_visibility', true ) ?> vi-ui fluid dropdown">
                                    <option value="visible" <?php selected( self::$settings->get_params( 'catalog_visibility' ), 'visible' ) ?>><?php esc_html_e( 'Shop and search results', 'woo-alidropship' ) ?></option>
                                    <option value="catalog" <?php selected( self::$settings->get_params( 'catalog_visibility' ), 'catalog' ) ?>><?php esc_html_e( 'Shop only', 'woo-alidropship' ) ?></option>
                                    <option value="search" <?php selected( self::$settings->get_params( 'catalog_visibility' ), 'search' ) ?>><?php esc_html_e( 'Search results only', 'woo-alidropship' ) ?></option>
                                    <option value="hidden" <?php selected( self::$settings->get_params( 'catalog_visibility' ), 'hidden' ) ?>><?php esc_html_e( 'Hidden', 'woo-alidropship' ) ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'This setting determines which shop pages products will be listed on.', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_description', true ) ?>"><?php esc_html_e( 'Product description', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'product_description' ) ?>"
                                        id="<?php self::set_params( 'product_description', true ) ?>"
                                        class="<?php self::set_params( 'product_description', true ) ?> vi-ui fluid dropdown">
                                    <option value="none" <?php selected( self::$settings->get_params( 'product_description' ), 'none' ) ?>><?php esc_html_e( 'None', 'woo-alidropship' ) ?></option>
                                    <option value="item_specifics" <?php selected( self::$settings->get_params( 'product_description' ), 'item_specifics' ) ?>><?php esc_html_e( 'Item specifics', 'woo-alidropship' ) ?></option>
                                    <option value="description" <?php selected( self::$settings->get_params( 'product_description' ), 'description' ) ?>><?php esc_html_e( 'Product Description', 'woo-alidropship' ) ?></option>
                                    <option value="item_specifics_and_description" <?php selected( self::$settings->get_params( 'product_description' ), 'item_specifics_and_description' ) ?>><?php esc_html_e( 'Item specifics & Product Description', 'woo-alidropship' ) ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Default product description when adding product to import list', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'use_external_image', true ) ?>">
									<?php esc_html_e( 'Use external links for images', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'use_external_image', true ) ?>"
                                           type="checkbox" <?php
									if ( class_exists( 'EXMAGE_WP_IMAGE_LINKS' ) ) {
										checked( self::$settings->get_params( 'use_external_image' ), 1 );
									} else {
										echo esc_attr( 'disabled' );
									}
									?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'use_external_image', true ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'use_external_image' ) ?>"/>
                                    <label><?php esc_html_e( 'This helps save storage by using original AliExpress image URLs but you will not be able to edit them', 'woo-alidropship' ) ?></label>
                                </div>
								<?php
								if ( ! class_exists( 'EXMAGE_WP_IMAGE_LINKS' ) ) {
									$plugins     = get_plugins();
									$plugin_slug = 'exmage-wp-image-links';
									$plugin      = "{$plugin_slug}/{$plugin_slug}.php";
									if ( ! isset( $plugins[ $plugin ] ) ) {
										$button = '<a href="' . esc_url( wp_nonce_url( self_admin_url( "update.php?action=install-plugin&plugin={$plugin_slug}" ), "install-plugin_{$plugin_slug}" ) ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Install now', 'woo-alidropship' ) . '</a>';;
									} else {
										$button = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array(
												'action' => 'activate',
												'plugin' => $plugin
											), admin_url( 'plugins.php' ) ), "activate-plugin_{$plugin}" ) ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Activate now', 'woo-alidropship' ) . '</a>';
									}
									?>
                                    <p>
                                        <strong>*</strong><?php echo wp_kses_post(sprintf( esc_html__( 'To use this feature, you have to install and activate %1$s plugin. %2$s', 'woo-alidropship' ), '<a target="_blank" href="https://bit.ly/exmage">EXMAGE – WordPress Image Links</a>', $button )) //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?>
                                    </p>
									<?php
								}
								?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'download_description_images', true ) ?>">
									<?php esc_html_e( 'Import description images', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'download_description_images', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'download_description_images' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'download_description_images' ) ?>"/>
                                    <label><?php esc_html_e( 'Upload images in product description if any. If disabled, images in description will use the original AliExpress cdn links', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_gallery', true ) ?>">
									<?php esc_html_e( 'Default select product images', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'product_gallery', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'product_gallery' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'product_gallery' ) ?>"/>
                                    <label><?php esc_html_e( 'First image will be selected as product image and other images(except images from product description) are selected in gallery when adding product to import list', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'disable_background_process', true ) ?>">
									<?php esc_html_e( 'Disable background process', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'disable_background_process', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'disable_background_process' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'disable_background_process' ) ?>"/>
                                    <label><?php esc_html_e( 'When importing products, instead of letting their images import in the background, main product image will be uploaded immediately while gallery and variation images(if any) will be added to Failed images page so that you can go there to import them manually.', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_categories', true ) ?>"><?php esc_html_e( 'Default categories', 'woo-alidropship' ); ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'product_categories', false, true ) ?>"
                                        class="<?php self::set_params( 'product_categories', true ) ?> search-category"
                                        id="<?php self::set_params( 'product_categories', true ) ?>"
                                        multiple="multiple">
									<?php
									$categories = self::$settings->get_params( 'product_categories' );
									if ( is_array( $categories ) && count( $categories ) ) {
										foreach ( $categories as $category_id ) {
											$category = get_term( $category_id );
											if ( $category ) {
												?>
                                                <option value="<?php echo esc_attr( $category_id ) ?>"
                                                        selected><?php echo esc_html( $category->name ); ?></option>
												<?php
											}
										}
									}
									?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Imported products will be added to these categories.', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_tags', true ) ?>"><?php esc_html_e( 'Default product tags', 'woo-alidropship' ); ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'product_tags', false, true ) ?>"
                                        class="<?php self::set_params( 'product_tags', true ) ?> search-tags"
                                        id="<?php self::set_params( 'product_tags', true ) ?>"
                                        multiple="multiple">
									<?php
									$product_tags = self::$settings->get_params( 'product_tags' );
									if ( is_array( $product_tags ) && count( $product_tags ) ) {
										foreach ( $product_tags as $product_tag_id ) {
											?>
                                            <option value="<?php echo esc_attr( $product_tag_id ) ?>"
                                                    selected><?php echo esc_html( $product_tag_id ); ?></option>
											<?php
										}
									}
									?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_shipping_class', true ) ?>"><?php esc_html_e( 'Default shipping class', 'woo-alidropship' ); ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'product_shipping_class', false, false ) ?>"
                                        class="vi-ui dropdown search <?php self::set_params( 'product_shipping_class', true ) ?>"
                                        id="<?php self::set_params( 'product_shipping_class', true ) ?>">
                                    <option value=""><?php esc_html_e( 'No shipping class', 'woo-alidropship' ) ?></option>
									<?php
									$shipping_classes       = get_terms(
										array(
											'taxonomy'   => 'product_shipping_class',
											'orderby'    => 'name',
											'order'      => 'ASC',
											'hide_empty' => false
										)
									);
									$product_shipping_class = self::$settings->get_params( 'product_shipping_class' );
									if ( is_array( $shipping_classes ) && count( $shipping_classes ) ) {
										foreach ( $shipping_classes as $shipping_class ) {
											?>
                                            <option value="<?php echo esc_attr( $shipping_class->term_id ) ?>"
												<?php selected( $shipping_class->term_id, $product_shipping_class ) ?>><?php echo esc_html( $shipping_class->name ); ?></option>
											<?php
										}
									}
									?>
                                </select>
                                <p><?php esc_html_e( 'Shipping class selected here will also be selected by default in the Import list', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'variation_visible', true ) ?>">
									<?php esc_html_e( 'Product variations is visible on product page', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'variation_visible', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'variation_visible' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'variation_visible' ) ?>"/>
                                    <label><?php esc_html_e( 'Enable to make variations of imported products visible on product page', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'manage_stock', true ) ?>">
									<?php esc_html_e( 'Manage stock', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'manage_stock', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'manage_stock' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'manage_stock' ) ?>"/>
                                    <label><?php esc_html_e( 'Enable manage stock and import product inventory.', 'woo-alidropship' ) ?></label>
                                </div>
                                <p class="description"><?php esc_html_e( 'If this option is disabled, products stock status will be set "Instock" and product inventory will not be imported', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'ignore_ship_from', true ) ?>">
									<?php esc_html_e( 'Ignore import ship from', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'ignore_ship_from', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'ignore_ship_from' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'ignore_ship_from' ) ?>"/>
                                    <label><?php esc_html_e( 'Enable to only import product variations with ship from China', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="vi-ui segment string-replace">
                                    <div class="vi-ui blue small message">
                                        <div class="header">
											<?php esc_html_e( 'Find and Replace', 'woo-alidropship' ); ?>
                                        </div>
                                        <ul class="list">
                                            <li><?php esc_html_e( 'Search for strings in product title and description and replace found strings with respective values.', 'woo-alidropship' ); ?></li>
                                        </ul>
                                    </div>
                                    <table class="vi-ui table">
                                        <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Search', 'woo-alidropship' ); ?></th>
                                            <th><?php esc_html_e( 'Case Sensitive', 'woo-alidropship' ); ?></th>
                                            <th><?php esc_html_e( 'Replace with', 'woo-alidropship' ); ?></th>
                                            <th><?php esc_html_e( 'Remove', 'woo-alidropship' ); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody>
										<?php
										$string_replace       = self::$settings->get_params( 'string_replace' );
										$string_replace_count = 1;
										if ( ! empty( $string_replace['from_string'] ) && ! empty( $string_replace['to_string'] ) && is_array( $string_replace['from_string'] ) ) {
											$string_replace_count = count( $string_replace['from_string'] );
										}
										for ( $i = 0; $i < $string_replace_count; $i ++ ) {
											$checked = $case_sensitive = '';
											if ( ! empty( $string_replace['sensitive'][ $i ] ) ) {
												$checked        = 'checked';
												$case_sensitive = 1;
											}
											?>
                                            <tr class="clone-source">
                                                <td>
                                                    <input type="text"
                                                           value="<?php echo esc_attr( isset( $string_replace['from_string'][ $i ] ) ? $string_replace['from_string'][ $i ] : '' ) ?>"
                                                           name="<?php self::set_params( 'string_replace[from_string][]' ) ?>">
                                                </td>
                                                <td>
                                                    <div class="<?php self::set_params( 'string-replace-sensitive-container', true ) ?>">
                                                        <input type="checkbox"
                                                               value="1" <?php echo esc_attr( $checked ) ?>
                                                               class="<?php self::set_params( 'string-replace-sensitive', true ) ?>">
                                                        <input type="hidden"
                                                               class="<?php self::set_params( 'string-replace-sensitive-value', true ) ?>"
                                                               value="<?php echo esc_attr( $case_sensitive ) ?>"
                                                               name="<?php self::set_params( 'string_replace[sensitive][]' ) ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           placeholder="<?php esc_html_e( 'Leave blank to delete matches', 'woo-alidropship' ); ?>"
                                                           value="<?php echo esc_attr( isset( $string_replace['to_string'][ $i ] ) ? $string_replace['to_string'][ $i ] : '' ) ?>"
                                                           name="<?php self::set_params( 'string_replace[to_string][]' ) ?>">
                                                </td>
                                                <td>
                                                    <span class="vi-ui button negative tiny delete-string-replace-rule"><i
                                                                class="dashicons dashicons-trash"></i></span>
                                                </td>
                                            </tr>
											<?php
										}
										?>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th colspan="4">
                                                <span class="vi-ui button positive tiny add-string-replace-rule"><?php esc_html_e( 'Add', 'woo-alidropship' ); ?></span>
                                            </th>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="price">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <td colspan="2">
                                <div class="vi-ui yellow small message">
                                    <div class="header">
										<?php esc_html_e( 'Important', 'woo-alidropship' ); ?>
                                    </div>
                                    <ul class="list">
                                        <li><?php esc_html_e( 'Products are imported in USD, the price of imported products will be converted after applying the price rule below', 'woo-alidropship' ); ?></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo wp_kses_post(sprintf( esc_html__( 'Exchange rate - USD/%s', 'woo-alidropship' ), get_option( 'woocommerce_currency' ) )) //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></th>
                            <td>
                                <div class="vi-ui input">
                                    <input type="text" <?php checked( self::$settings->get_params( 'import_currency_rate' ), 1 ) ?>
                                           id="<?php self::set_params( 'import_currency_rate', true ) ?>"
                                           value="<?php echo esc_attr( self::$settings->get_params( 'import_currency_rate' ) ) ?>"
                                           name="<?php self::set_params( 'import_currency_rate' ) ?>"/>
                                </div>
                                <p><?php echo wp_kses_post(sprintf( __( 'This is exchange rate to convert product price from USD to your store\'s currency(%s) when adding products to import list.', 'woo-alidropship' ), get_option( 'woocommerce_currency' ) )) //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></p>
                            </td>
                        </tr>
						<?php
						if ( get_option( 'woocommerce_currency' ) !== 'RUB' ) {
							?>
                            <tr>
                                <th>
                                    <label for="<?php self::set_params( 'import_currency_rate_RUB', true ) ?>"><?php esc_html_e( 'Exchange rate - RUB/USD', 'woo-alidropship' ) ?></label>
                                <td>
                                    <div class="vi-ui input">
                                        <input type="number" <?php checked( self::$settings->get_params( 'import_currency_rate_RUB' ), 1 ) ?>
                                               step="0.001"
                                               min="0.001"
                                               id="<?php self::set_params( 'import_currency_rate_RUB', true ) ?>"
                                               class="<?php self::set_params( 'import_currency_rate_RUB', true ) ?>"
                                               value="<?php echo esc_attr(self::$settings->get_params( 'import_currency_rate_RUB' )) ?>"
                                               name="<?php self::set_params( 'import_currency_rate_RUB' ) ?>"/>
                                    </div>
                                    <p><?php esc_html_e( 'AliExpress now does not allow switching currency if ship-to is set to Russian Federation(always in RUB) so if your store\'s currency is not RUB, you have to set this rate to be able to import products', 'woo-alidropship' ) ?></p>
                                </td>
                            </tr>
							<?php
						}
						?>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'exchange_rate_api', true ) ?>"><?php esc_html_e( 'Exchange rate API', 'woo-alidropship' ) ?></label>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                                <p><?php esc_html_e( 'Get exchange rate from Google finance, Yahoo finance API...', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label><?php esc_html_e( 'Update rate automatically', 'woo-alidropship' ) ?></label>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td>
                                <p><?php esc_html_e( 'E.g: Your WooCommerce store currency is VND, exchange rate is: 1 USD = 23 000 VND', 'woo-alidropship' ) ?></p>
                                <p><?php esc_html_e( '=> set "Exchange rate" 23 000', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div class="vi-ui segment <?php self::set_params( 'price_rule_wrapper', true ) ?>">
                        <div class="vi-ui positive small message">
							<?php esc_html_e( 'For each price, first matched rule(from top to bottom) will be applied. If no rules match, the default will be used.', 'woo-alidropship' ) ?>
                        </div>
                        <table class="vi-ui table price-rule">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'Price range', 'woo-alidropship' ) ?></th>
                                <th><?php esc_html_e( 'Actions', 'woo-alidropship' ) ?></th>
                                <th><?php esc_html_e( 'Sale price', 'woo-alidropship' ) ?>
                                    <div class="<?php self::set_params( 'description', true ) ?>">
										<?php esc_html_e( '(Set -1 to not use sale price)', 'woo-alidropship' ) ?>
                                    </div>
                                </th>
                                <th style="min-width: 135px"><?php esc_html_e( 'Regular price', 'woo-alidropship' ) ?></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody class="<?php self::set_params( 'price_rule_container', true ) ?> ui-sortable">
							<?php
							$decimals      = wc_get_price_decimals();
							$decimals_unit = 1;
							if ( $decimals > 0 ) {
								$decimals_unit = pow( 10, ( - 1 * $decimals ) );
							}
							$price_from       = self::$settings->get_params( 'price_from' );
							$price_default    = self::$settings->get_params( 'price_default' );
							$price_to         = self::$settings->get_params( 'price_to' );
							$plus_value       = self::$settings->get_params( 'plus_value' );
							$plus_sale_value  = self::$settings->get_params( 'plus_sale_value' );
							$plus_value_type  = self::$settings->get_params( 'plus_value_type' );
							$price_from_count = count( $price_from );
							if ( $price_from_count > 0 ) {
								/*adjust price rules since version 1.0.1.1*/
								if ( ! is_array( $price_to ) || count( $price_to ) !== $price_from_count ) {
									if ( $price_from_count > 1 ) {
										$price_to   = array_values( array_slice( $price_from, 1 ) );
										$price_to[] = '';
									} else {
										$price_to = array( '' );
									}
								}
								for ( $i = 0; $i < count( $price_from ); $i ++ ) {
									switch ( $plus_value_type[ $i ] ) {
										case 'fixed':
											$value_label_left  = '+';
											$value_label_right = '$';
											break;
										case 'percent':
											$value_label_left  = '+';
											$value_label_right = '%';
											break;
										case 'multiply':
											$value_label_left  = 'x';
											$value_label_right = '';
											break;
										default:
											$value_label_left  = '=';
											$value_label_right = '$';
									}
									?>
                                    <tr class="<?php self::set_params( 'price_rule_row', true ) ?>">
                                        <td>
                                            <div class="equal width fields">
                                                <div class="field">
                                                    <div class="vi-ui left labeled input fluid">
                                                        <label for="amount" class="vi-ui label">$</label>
                                                        <input
                                                                step="any"
                                                                type="number"
                                                                min="0"
                                                                value="<?php echo esc_attr( $price_from[ $i ] ); ?>"
                                                                name="<?php self::set_params( 'price_from', false, true ); ?>"
                                                                class="<?php self::set_params( 'price_from', true ); ?>">
                                                    </div>
                                                </div>
                                                <span class="<?php self::set_params( 'price_from_to_separator', true ); ?>">-</span>
                                                <div class="field">
                                                    <div class="vi-ui left labeled input fluid">
                                                        <label for="amount" class="vi-ui label">$</label>
                                                        <input
                                                                step="any"
                                                                type="number"
                                                                min="0"
                                                                value="<?php echo esc_attr( $price_to[ $i ] ); ?>"
                                                                name="<?php self::set_params( 'price_to', false, true ); ?>"
                                                                class="<?php self::set_params( 'price_to', true ); ?>">
                                                    </div>
                                                </div>

                                            </div>
                                        </td>
                                        <td>
                                            <select name="<?php self::set_params( 'plus_value_type', false, true ); ?>"
                                                    class="vi-ui fluid dropdown <?php self::set_params( 'plus_value_type', true ); ?>">
                                                <option value="fixed" <?php selected( $plus_value_type[ $i ], 'fixed' ) ?>><?php esc_html_e( 'Increase by Fixed amount($)', 'woo-alidropship' ) ?></option>
                                                <option value="percent" <?php selected( $plus_value_type[ $i ], 'percent' ) ?>><?php esc_html_e( 'Increase by Percentage(%)', 'woo-alidropship' ) ?></option>
                                                <option value="multiply" <?php selected( $plus_value_type[ $i ], 'multiply' ) ?>><?php esc_html_e( 'Multiply with', 'woo-alidropship' ) ?></option>
                                                <option value="set_to" <?php selected( $plus_value_type[ $i ], 'set_to' ) ?>><?php esc_html_e( 'Set to', 'woo-alidropship' ) ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="vi-ui right labeled input fluid">
                                                <label for="amount"
                                                       class="vi-ui label <?php self::set_params( 'value-label-left', true ); ?>"><?php echo esc_html( $value_label_left ) ?></label>
                                                <input type="number" min="-1" step="any"
                                                       value="<?php echo esc_attr( $plus_sale_value[ $i ] ); ?>"
                                                       name="<?php self::set_params( 'plus_sale_value', false, true ); ?>"
                                                       class="<?php self::set_params( 'plus_sale_value', true ); ?>">
                                                <div class="vi-ui basic label <?php self::set_params( 'value-label-right', true ); ?>"><?php echo esc_html( $value_label_right ) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="vi-ui right labeled input fluid">
                                                <label for="amount"
                                                       class="vi-ui label <?php self::set_params( 'value-label-left', true ); ?>"><?php echo esc_html( $value_label_left ) ?></label>
                                                <input type="number" min="0" step="any"
                                                       value="<?php echo esc_attr( $plus_value[ $i ] ); ?>"
                                                       name="<?php self::set_params( 'plus_value', false, true ); ?>"
                                                       class="<?php self::set_params( 'plus_value', true ); ?>">
                                                <div class="vi-ui basic label <?php self::set_params( 'value-label-right', true ); ?>"><?php echo esc_html( $value_label_right ) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="">
                                                <span class="vi-ui button icon negative mini <?php self::set_params( 'price_rule_remove', true ) ?>"
                                                      title="<?php esc_attr_e( 'Remove', 'woo-alidropship' ) ?>"><i
                                                            class="icon trash"></i></span>
                                            </div>
                                        </td>
                                    </tr>
									<?php
								}
							}
							?>
                            </tbody>
                            <tfoot>
							<?php
							$plus_value_type_d = isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply';
							$plus_sale_value_d = isset( $price_default['plus_sale_value'] ) ? $price_default['plus_sale_value'] : 1;
							$plus_value_d      = isset( $price_default['plus_value'] ) ? $price_default['plus_value'] : 2;
							switch ( $plus_value_type_d ) {
								case 'fixed':
									$value_label_left  = '+';
									$value_label_right = '$';
									break;
								case 'percent':
									$value_label_left  = '+';
									$value_label_right = '%';
									break;
								case 'multiply':
									$value_label_left  = 'x';
									$value_label_right = '';
									break;
								default:
									$value_label_left  = '=';
									$value_label_right = '$';
							}
							?>
                            <tr class="<?php echo esc_attr( self::set( array( 'price-rule-row-default' ) ) ) ?>">
                                <th><?php esc_html_e( 'Default', 'woo-alidropship' ) ?></th>
                                <th>
                                    <select name="<?php self::set_params( 'price_default[plus_value_type]', false ); ?>"
                                            class="vi-ui fluid dropdown <?php self::set_params( 'plus_value_type', true ); ?>">
                                        <option value="fixed" <?php selected( $plus_value_type_d, 'fixed' ) ?>><?php esc_html_e( 'Increase by Fixed amount($)', 'woo-alidropship' ) ?></option>
                                        <option value="percent" <?php selected( $plus_value_type_d, 'percent' ) ?>><?php esc_html_e( 'Increase by Percentage(%)', 'woo-alidropship' ) ?></option>
                                        <option value="multiply" <?php selected( $plus_value_type_d, 'multiply' ) ?>><?php esc_html_e( 'Multiply with', 'woo-alidropship' ) ?></option>
                                        <option value="set_to" <?php selected( $plus_value_type_d, 'set_to' ) ?>><?php esc_html_e( 'Set to', 'woo-alidropship' ) ?></option>
                                    </select>
                                </th>
                                <th>
                                    <div class="vi-ui right labeled input fluid">
                                        <label for="amount"
                                               class="vi-ui label <?php self::set_params( 'value-label-left', true ); ?>"><?php echo esc_html( $value_label_left ) ?></label>
                                        <input type="number" min="-1" step="any"
                                               value="<?php echo esc_attr( $plus_sale_value_d ); ?>"
                                               name="<?php self::set_params( 'price_default[plus_sale_value]', false ); ?>"
                                               class="<?php self::set_params( 'plus_sale_value', true ); ?>">
                                        <div class="vi-ui basic label <?php self::set_params( 'value-label-right', true ); ?>"><?php echo esc_html( $value_label_right ) ?></div>
                                    </div>
                                </th>
                                <th>
                                    <div class="vi-ui right labeled input fluid">
                                        <label for="amount"
                                               class="vi-ui label <?php self::set_params( 'value-label-left', true ); ?>"><?php echo esc_html( $value_label_left ) ?></label>
                                        <input type="number" min="0" step="any"
                                               value="<?php echo esc_attr( $plus_value_d ); ?>"
                                               name="<?php self::set_params( 'price_default[plus_value]', false ); ?>"
                                               class="<?php self::set_params( 'plus_value', true ); ?>">
                                        <div class="vi-ui basic label <?php self::set_params( 'value-label-right', true ); ?>"><?php echo esc_html( $value_label_right ) ?></div>
                                    </div>
                                </th>
                                <th>
                                </th>
                            </tr>
                            </tfoot>
                        </table>
                        <span class="<?php self::set_params( 'price_rule_add', true ) ?> vi-ui button icon positive mini"
                              title="<?php esc_attr_e( 'Add a new range', 'woo-alidropship' ) ?>"><i
                                    class="icon add"></i></span>
                    </div>
                    <div class="vi-ui segment">
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <td colspan="2">
                                    <div class="vi-ui positive small message">
                                        <div class="header">
											<?php esc_html_e( 'How does it work?', 'woo-alidropship' ); ?>
                                        </div>
                                        <ul class="list">
                                            <li><?php esc_html_e( 'Rules will be looped from top to bottom grouped by Compared part to find matches', 'woo-alidropship' ); ?></li>
                                            <li><?php esc_html_e( 'Your input price can only be applied by 1 rule for each part(fraction/integer)=>maximum 2 rules in total(1 for Integer part and 1 for Fraction part)', 'woo-alidropship' ); ?></li>
                                            <li><?php esc_html_e( 'Rules for Fraction part will be applied before rules for Integer part', 'woo-alidropship' ); ?></li>
                                        </ul>
                                        <div class="header">
											<?php esc_html_e( 'Rules for Fraction part', 'woo-alidropship' ); ?>
                                        </div>
                                        <ul class="list">
                                            <li><?php echo wp_kses_post(__( 'Leave Price range <strong>empty</strong> to apply to all prices that have decimal part matches the Compared part range', 'woo-alidropship' )); ?></li>
                                            <li><?php echo wp_kses_post(__( 'Leave Compared part range <strong>empty</strong> to apply to all prices in the Price range', 'woo-alidropship' )); ?></li>
                                            <li><?php echo wp_kses_post(__( 'Can use an <strong>x</strong> in New value of compared part to remain the respective digit in the Compared part of input price', 'woo-alidropship') ); ?></li>
                                            <li><?php echo wp_kses_post(sprintf( _n( 'New value of compared part can contain maximum %s digit which is the Number of decimals in your <a href="admin.php?page=wc-settings#woocommerce_price_num_decimals" target="_blank">WooCommerce settings</a>', 'New value of compared part can contain maximum %s digits which is the Number of decimals in your <a href="admin.php?page=wc-settings#woocommerce_price_num_decimals" target="_blank">WooCommerce settings</a>', wc_get_price_decimals(), 'woo-alidropship' ), wc_get_price_decimals() )); //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></li>
                                        </ul>
                                        <div class="header">
											<?php esc_html_e( 'Rules for Integer part', 'woo-alidropship' ); ?>
                                        </div>
                                        <ul class="list">
                                            <li><?php esc_html_e( 'Maximum number of digits of Compared part range is 1 subtracted from the minimum number of digits of Price range', 'woo-alidropship' ); ?></li>
                                            <li><?php esc_html_e( 'Maximum number of digits of New value of compared part is the maximum number of digits of Compared part range', 'woo-alidropship' ); ?></li>
                                            <li><?php echo wp_kses_post(__( 'Leave Compared part range <strong>empty</strong> to apply to all prices in the Price range', 'woo-alidropship' )); ?></li>
                                        </ul>
                                        <div class="vi-ui segment">
                                            <div class="vi-ui accordion">
                                                <div class="title"><?php esc_html_e( 'View detailed example with explanation', 'woo-alidropship' ) ?></div>
                                                <div class="content"><img
                                                            src="<?php echo esc_url( VI_WOO_ALIDROPSHIP_IMAGES . 'price-format-rules.png' ); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label for="<?php self::set_params( 'format_price_rules_enable', true ) ?>">
										<?php esc_html_e( 'Price format', 'woo-alidropship' ) ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="vi-ui toggle checkbox">
                                        <input id="<?php self::set_params( 'format_price_rules_enable', true ) ?>"
                                               type="checkbox" <?php checked( self::$settings->get_params( 'format_price_rules_enable' ), 1 ) ?>
                                               tabindex="0" class="hidden" value="1"
                                               name="<?php self::set_params( 'format_price_rules_enable' ) ?>"/>
                                        <label><?php esc_html_e( 'Adjust product prices following below rules after prices are calculated with above rules', 'woo-alidropship' ) ?></label>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
						<?php
						if ( $decimals < 1 ) {
							?>
                            <div class="vi-ui message">
								<?php echo wp_kses_post(sprintf( __( 'Rules for Fraction part will not take effect because you set %s for Number of decimals in your <a href="admin.php?page=wc-settings#woocommerce_price_num_decimals" target="_blank">WooCommerce settings</a>', 'woo-alidropship' ), $decimals )); //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?>
                            </div>
							<?php
						}
						?>
                        <table class="vi-ui table <?php self::set_params( 'format_price_rules_table', true ) ?>">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'No.', 'woo-alidropship' ) ?></th>
                                <th><?php esc_html_e( 'Price range', 'woo-alidropship' ) ?></th>
                                <th class="<?php self::set_params( 'format_price_rules_col', true ) ?>"><?php esc_html_e( 'Compared part', 'woo-alidropship' ) ?></th>
                                <th><?php esc_html_e( 'Compared part range', 'woo-alidropship' ) ?>
                                <th class="<?php self::set_params( 'format_price_rules_col', true ) ?>"><?php esc_html_e( 'New value of compared part', 'woo-alidropship' ) ?></th>
                            </tr>
                            </thead>
                            <tbody class="<?php self::set_params( 'format_price_rules_container', true ) ?> ui-sortable">
							<?php
							$format_price_rules = self::$settings->get_params( 'format_price_rules' );

							if ( ! is_array( $format_price_rules ) || ! count( $format_price_rules ) ) {
								$format_price_rules = array(
									array(
										'from'       => '0',
										'to'         => '0',
										'part'       => 'fraction',
										'value_from' => '0',
										'value_to'   => '0',
										'value'      => '0',
									)
								);
							}
							foreach ( $format_price_rules as $rule_no => $format_price_rule ) {
								$label_class    = self::set( 'format-price-rules-label' );
								$label_class    .= $format_price_rule['part'] === 'fraction' ? ' left' : ' right';
								$label_integer  = '.0';
								$label_fraction = '0.';
								?>
                                <tr>
                                    <th>
                                        <span class="<?php self::set_params( 'format_price_rules_number', true ); ?>"><?php echo esc_html( $rule_no + 1 ); ?></span>
                                    </th>
                                    <td>
                                        <div class="equal width fields">
                                            <div class="field <?php self::set_params( 'error-message-parent', true ); ?>">
                                                <div class="vi-ui left labeled input fluid">
                                                    <label for="amount" class="vi-ui label">$</label>
                                                    <input
                                                            type="number"
                                                            step="<?php echo esc_attr( $decimals_unit ) ?>"
                                                            min="0"
                                                            value="<?php echo esc_attr( $format_price_rule['from'] ) ?>"
                                                            name="<?php self::set_params( 'format_price_rules[from]', false, true ); ?>"
                                                            class="<?php self::set_params( 'format_price_rules_from', true ); ?>">
                                                </div>
                                                <div class="<?php self::set_params( 'error-message', true ); ?>"></div>
                                            </div>
                                            <span class="<?php self::set_params( 'price_from_to_separator', true ); ?>">-</span>
                                            <div class="field <?php self::set_params( 'error-message-parent', true ); ?>">
                                                <div class="vi-ui left labeled input fluid">
                                                    <label for="amount" class="vi-ui label">$</label>
                                                    <input
                                                            type="number"
                                                            min="0"
                                                            step="<?php echo esc_attr( $decimals_unit ) ?>"
                                                            value="<?php echo esc_attr( $format_price_rule['to'] ) ?>"
                                                            name="<?php self::set_params( 'format_price_rules[to]', false, true ); ?>"
                                                            class="<?php self::set_params( 'format_price_rules_to', true ); ?>">
                                                </div>
                                                <div class="<?php self::set_params( 'error-message', true ); ?>"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="<?php self::set_params( 'format_price_rules[part]', false, true ); ?>"
                                                class="vi-ui fluid dropdown <?php self::set_params( 'format_price_rules_part', true ); ?>">
                                            <option value="integer" <?php selected( $format_price_rule['part'], 'integer' ) ?>><?php esc_html_e( 'Integer', 'woo-alidropship' ) ?></option>
                                            <option value="fraction" <?php selected( $format_price_rule['part'], 'fraction' ) ?>><?php esc_html_e( 'Fraction', 'woo-alidropship' ) ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="equal width fields">
                                            <div class="field <?php self::set_params( 'error-message-parent', true ); ?>">
                                                <div class="vi-ui <?php echo esc_attr( $label_class ) ?> labeled input fluid">
                                                    <label for="amount"
                                                           class="vi-ui label <?php self::set_params( 'format_price_rules_label_fraction', true ); ?>"><?php echo esc_html( $label_fraction ) ?></label>
                                                    <input
                                                            type="number"
                                                            step="1"
                                                            min="0"
                                                            value="<?php echo esc_attr( $format_price_rule['value_from'] ) ?>"
                                                            name="<?php self::set_params( 'format_price_rules[value_from]', false, true ); ?>"
                                                            class="<?php self::set_params( 'format_price_rules_value_from', true ); ?>">
                                                    <label for="amount"
                                                           class="vi-ui label <?php self::set_params( 'format_price_rules_label_integer', true ); ?>"><?php echo esc_html( $label_integer ) ?></label>
                                                </div>
                                                <div class="<?php self::set_params( 'error-message', true ); ?>"></div>
                                            </div>
                                            <span class="<?php self::set_params( 'price_from_to_separator', true ); ?>">-</span>
                                            <div class="field <?php self::set_params( 'error-message-parent', true ); ?>">
                                                <div class="vi-ui <?php echo esc_attr( $label_class ) ?> labeled input fluid">
                                                    <label for="amount"
                                                           class="vi-ui label <?php self::set_params( 'format_price_rules_label_fraction', true ); ?>"><?php echo esc_html( $label_fraction ) ?></label>
                                                    <input
                                                            type="number"
                                                            step="1"
                                                            min="0"
                                                            value="<?php echo esc_attr( $format_price_rule['value_to'] ) ?>"
                                                            name="<?php self::set_params( 'format_price_rules[value_to]', false, true ); ?>"
                                                            class="<?php self::set_params( 'format_price_rules_value_to', true ); ?>">
                                                    <label for="amount"
                                                           class="vi-ui label <?php self::set_params( 'format_price_rules_label_integer', true ); ?>"><?php echo esc_html( $label_integer ) ?></label>
                                                </div>
                                                <div class="<?php self::set_params( 'error-message', true ); ?>"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="<?php echo esc_attr( self::set( array(
										'format-price-rules-value-td',
										'error-message-parent'
									) ) ); ?>">
                                        <div class="vi-ui <?php echo esc_attr( $label_class ) ?> labeled input fluid">
                                            <label for="amount"
                                                   class="vi-ui label <?php self::set_params( 'format_price_rules_label_fraction', true ); ?>"><?php echo esc_html( $label_fraction ) ?></label>
                                            <input type="text"
                                                   value="<?php echo esc_attr( $format_price_rule['value'] ) ?>"
                                                   name="<?php self::set_params( 'format_price_rules[value]', false, true ); ?>"
                                                   class="<?php self::set_params( 'format_price_rules_value', true ); ?>">
                                            <label for="amount"
                                                   class="vi-ui label <?php self::set_params( 'format_price_rules_label_integer', true ); ?>"><?php echo esc_html( $label_integer ) ?></label>
                                        </div>
                                        <div class="<?php self::set_params( 'format_price_rules_action_buttons', true ) ?>">
                                            <i class="vi-ui icon copy green <?php self::set_params( 'format_price_rules_duplicate', true ) ?>"
                                               title="<?php esc_attr_e( 'Duplicate this row', 'woo-alidropship' ) ?>"></i>
                                            <i class="vi-ui icon trash red <?php self::set_params( 'format_price_rules_remove', true ) ?>"
                                               title="<?php esc_attr_e( 'Remove this row', 'woo-alidropship' ) ?>"></i>
                                        </div>
                                        <div class="<?php self::set_params( 'error-message', true ); ?>"></div>
                                    </td>
                                </tr>
								<?php
							}
							?>
                            </tbody>
                        </table>
                        <div class="equal width fields">
                            <div class="field">
                                <div class="vi-ui right labeled input fluid">
                                    <input type="number"
                                           placeholder="<?php esc_attr_e( 'Enter a price to test', 'woo-alidropship' ) ?>"
                                           step="<?php echo esc_attr( $decimals_unit ) ?>"
                                           min="0"
                                           value="<?php echo esc_attr( self::$settings->get_params( 'format_price_rules_test' ) ) ?>"
                                           name="<?php self::set_params( 'format_price_rules_test', false, false ); ?>"
                                           class="<?php self::set_params( 'format_price_rules_test', true ); ?>">
                                    <label for="amount" class="vi-ui label"><span
                                                class="vi-ui positive button tiny <?php self::set_params( 'format_price_rules_test_button', true ); ?>"><?php esc_html_e( 'View result', 'woo-alidropship' ) ?></span></label>
                                </div>
                            </div>
                            <div class="field <?php self::set_params( 'format_price_rules_test_result_container', true ); ?>">
                                <span class="<?php self::set_params( 'format_price_rules_test_result', true ); ?>"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="vi-ui bottom attached tab segment <?php self::set_params( 'tab-content', true ) ?>"
                     data-tab="product_update">
                    <div class="vi-ui message positive">
						<?php esc_html_e( 'Configure what the plugin will do when you update product manually with the chrome extension', 'woo-alidropship' ) ?>
                    </div>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'update_product_auto', true ) ?>"><?php esc_html_e( 'Sync product automatically', 'woo-alidropship' ) ?></label>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'update_product_quantity', true ) ?>"><?php esc_html_e( 'Update quantity', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'update_product_price', true ) ?>"><?php esc_html_e( 'Update price', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'send_email_if', true ) ?>"><?php esc_html_e( 'Notification email', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                                <p><?php esc_html_e( 'When updating product with chrome extension , send email to admin if AliExpress product is no longer available/is out of stock/changes price', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment <?php echo esc_attr( self::set( array(
					'attributes-tab',
					'tab-content'
				) ) ) ?>"
                     data-tab="attributes">
                    <div class="vi-ui message positive">
						<?php esc_attr_e( 'This feature is to automatically replace original attribute term with respective value in the Import list. This does not apply to products whose attributes were edited.', 'woo-alidropship' ) ?>
                    </div>
                    <div class="vi-ui labeled left input fluid">
                        <label class="vi-ui label green"><?php esc_attr_e( 'Search term', 'woo-alidropship' ) ?></label>
                        <input type="text" class="<?php echo esc_attr( self::set( 'product-attribute-search' ) ) ?>"
                               placeholder="<?php esc_attr_e( 'Enter attribute term to search', 'woo-alidropship' ) ?>">
                    </div>
                    <div class="<?php echo esc_attr( self::set( 'attributes-mapping-table-container' ) ) ?>">
                        <table class="vi-ui table celled <?php echo esc_attr( self::set( 'attributes-mapping-table' ) ) ?>">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'Attribute slug', 'woo-alidropship' ) ?></th>
                                <th><?php esc_html_e( 'Original attribute term(case-insensitive)', 'woo-alidropship' ) ?></th>
                                <th><?php esc_html_e( 'Replacement', 'woo-alidropship' ) ?></th>
                            </tr>
                            </thead>
                            <tbody class="<?php echo esc_attr( self::set( 'attributes-mapping' ) ) ?>">
                            <tr>
                                <td colspan="3">
                                    <a class="vi-ui button" target="_blank"
                                       href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="<?php echo esc_attr( self::set( array(
						'overlay',
					) ) ) ?>">
                        <div class="vi-ui indicating progress standard active <?php echo esc_attr( self::set( 'attributes-mapping-progress' ) ) ?>">
                            <div class="label"></div>
                            <div class="bar">
                                <div class="progress"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="override">
                    <div class="vi-ui positive small message">
						<?php esc_html_e( 'Below options are used when you override a product', 'woo-alidropship' ); ?>
                    </div>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'override_keep_product', true ) ?>"><?php esc_html_e( 'Keep Woo product', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'override_keep_product', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'override_keep_product' ), 1 ) ?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'override_keep_product', true ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'override_keep_product' ) ?>"/>
                                    <label><?php esc_html_e( 'Instead of deleting old product to create a new one, it will update the overridden old product\'s prices/stock/attributes/variations based on the new data. This way, data such as reviews, metadata... will not be lost.', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'override_find_in_orders', true ) ?>"><?php esc_html_e( 'Find in unfulfilled orders', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'override_find_in_orders', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'override_find_in_orders' ), 1 ) ?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'override_find_in_orders', true ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'override_find_in_orders' ) ?>"/>
                                    <label><?php esc_html_e( 'Check for existence of overridden product in unfulfilled orders before overriding', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Override title', 'woo-alidropship' ) ?></th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'override_title', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'override_title' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'override_title' ) ?>"/>
                                    <label><?php esc_html_e( 'Replace title of overridden product with new product\'s title', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Override images', 'woo-alidropship' ) ?></th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'override_images', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'override_images' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'override_images' ) ?>"/>
                                    <label><?php esc_html_e( 'Replace images and gallery of overridden product with new product\'s images and gallery', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Override description', 'woo-alidropship' ) ?></th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'override_description', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'override_description' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'override_description' ) ?>"/>
                                    <label><?php esc_html_e( 'Replace description and short description of overridden product with new product\'s description and short description', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Hide options', 'woo-alidropship' ) ?></th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'override_hide', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'override_hide' ), 1 ) ?>
                                           tabindex="0" class="hidden" value="1"
                                           name="<?php self::set_params( 'override_hide' ) ?>"/>
                                    <label><?php esc_html_e( 'Do not show these options when overriding product', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment" data-tab="fulfill">
                    <div class="vi-ui message positive">
                        <ul class="list">
                            <li><?php esc_html_e( 'Access token is used to bulk fulfill AliExpress orders/automatically sync product price and stock without using chrome extension', 'woo-alidropship' ) ?></li>
                        </ul>
                    </div>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'fulfill_billing_fields_in_latin', true ) ?>">
									<?php esc_html_e( 'Require billing fields in Latin', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php self::set_params( 'fulfill_billing_fields_in_latin' ) ?>"
                                           id="<?php self::set_params( 'fulfill_billing_fields_in_latin', true ) ?>"
                                           class="<?php self::set_params( 'fulfill_billing_fields_in_latin', true ) ?>"
                                           value="1" <?php checked( self::$settings->get_params( 'fulfill_billing_fields_in_latin' ), 1 ) ?>>
                                    <label></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="auto-update-key"><?php esc_html_e( 'AliExpress API', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <table class="vi-ui table celled <?php echo esc_attr( self::set( 'access-token-table' ) ) ?>">
                                    <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'AliExpress account', 'woo-alidropship' ) ?></th>
                                        <th><?php esc_html_e( 'Expire time', 'woo-alidropship' ) ?></th>
                                        <th><?php esc_html_e( 'Default', 'woo-alidropship' ) ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td colspan="3">
                                            <a class="vi-ui button" target="_blank"
                                               href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Carrier company', 'woo-alidropship' ) ?></th>
                            <td>
                                <select class="vi-ui fluid dropdown"
                                        name="<?php self::set_params( 'fulfill_default_carrier' ) ?>">
									<?php
									$saved = self::$settings->get_params( 'fulfill_default_carrier' );
									foreach ( $shipping_companies as $key => $value ) {
										?>
                                        <option value="<?php echo esc_attr( $key ) ?>" <?php selected( $saved, $key ) ?>><?php echo esc_html( $value ) ?></option>
										<?php
									}
									?>
                                </select>
                                <p><?php esc_html_e( 'Default carrier company', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'fulfill_default_phone_number', true ) ?>">
									<?php esc_html_e( 'Default phone number', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui labeled left input fluid <?php self::set_params( 'fulfill_default_phone_number_container', true ) ?>">
                                    <label class="vi-ui label">
                                        <select class="vi-ui dropdown search"
                                                name="<?php self::set_params( 'fulfill_default_phone_country' ) ?>"
                                                class="<?php self::set_params( 'fulfill_default_phone_country', true ) ?>"
                                                id="<?php self::set_params( 'fulfill_default_phone_country', true ) ?>">
											<?php
											$phone_country   = self::$settings->get_params( 'fulfill_default_phone_country' );
											$phone_countries = VI_WOO_ALIDROPSHIP_Admin_API::get_phone_country_code();
											ksort( $phone_countries );
											foreach ( $phone_countries as $phone_country_k => $phone_country_v ) {
												?>
                                                <option value="<?php echo esc_attr( $phone_country_k ) ?>" <?php selected( $phone_country, $phone_country_k ) ?>><?php echo esc_html( $phone_country_v ? "{$phone_country_k}({$phone_country_v})" : $phone_country_k ) ?></option>
												<?php
											}
											?>
                                        </select>
                                    </label>
                                    <input type="tel"
                                           id="<?php self::set_params( 'fulfill_default_phone_number', true ) ?>"
                                           name="<?php self::set_params( 'fulfill_default_phone_number' ) ?>"
                                           value="<?php echo esc_attr( self::$settings->get_params( 'fulfill_default_phone_number' ) ) ?>">
                                </div>
                                <p><?php esc_html_e( 'If an order does not have phone number, this number will be used when fulfilling AliExpress order', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'fulfill_default_phone_number_override', true ) ?>"><?php esc_html_e( 'Override customer phone number', 'woo-alidropship' ) ?></label>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input type="checkbox"
                                           name="<?php self::set_params( 'fulfill_default_phone_number_override' ) ?>"
                                           id="<?php self::set_params( 'fulfill_default_phone_number_override', true ) ?>"
                                           class="<?php self::set_params( 'fulfill_default_phone_number_override', true ) ?>"
                                           value="1" <?php checked( self::$settings->get_params( 'fulfill_default_phone_number_override' ), 1 ) ?>>
                                    <label><?php esc_html_e( 'Always use Default phone number when fulfilling AliExpress order no matter your customers have phone number or not', 'woo-alidropship' ) ?></label>
                                </div>
                                <p><?php echo wp_kses_post(__( '<strong>*Note:</strong> This only overrides a customer\'s phone number if the default phone country is the same as the customer\'s country', 'woo-alidropship') ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'cpf_custom_meta_key', true ) ?>"><?php esc_html_e( 'CPF meta field', 'woo-alidropship' ) ?></label>
                            <td>
                                <input type="text"
                                       name="<?php self::set_params( 'cpf_custom_meta_key' ) ?>"
                                       id="<?php self::set_params( 'cpf_custom_meta_key', true ) ?>"
                                       class="<?php self::set_params( 'cpf_custom_meta_key', true ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'cpf_custom_meta_key' ) ) ?>">
                                <p><?php esc_html_e( 'The order meta field that a 3rd party plugin uses to store customer\'s CPF field.', 'woo-alidropship' ) ?></p>
                                <p><?php esc_html_e( 'This is used only for Customers from Brazil. If empty, billing company will be used as CPF when fulfilling AliExpress orders.', 'woo-alidropship' ) ?></p>
                                <p><?php echo wp_kses_post(__( 'If you use <a target="_blank" href="https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/">Brazilian Market on WooCommerce</a>, please fill this option with <strong>_billing_cpf</strong>', 'woo-alidropship')) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'billing_number_meta_key', true ) ?>"><?php esc_html_e( 'Billing number meta field', 'woo-alidropship' ) ?></label>
                            <td>
                                <input type="text"
                                       name="<?php self::set_params( 'billing_number_meta_key' ) ?>"
                                       id="<?php self::set_params( 'billing_number_meta_key', true ) ?>"
                                       class="<?php self::set_params( 'billing_number_meta_key', true ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'billing_number_meta_key' ) ) ?>">
                                <p><?php esc_html_e( 'If you customize checkout fields to add the billing number field, please enter the order meta field which is used to store billing number here.', 'woo-alidropship' ) ?></p>
                                <p><?php echo wp_kses_post(__( 'If you use <a target="_blank" href="https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/">Brazilian Market on WooCommerce</a>, please fill this option with <strong>_billing_number</strong>', 'woo-alidropship' )) ?></p>
                                <p><?php echo wp_kses_post(__( '<strong>*Caution: </strong>If you already use a custom PHP snippet to append billing number to order address via <strong>vi_wad_fulfillment_customer_info</strong> filter hook, please leave this field empty to avoid billing number being added twice to order address which makes the address become incorrect.', 'woo-alidropship' )) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'shipping_number_meta_key', true ) ?>"><?php esc_html_e( 'Shipping number meta field', 'woo-alidropship' ) ?></label>
                            <td>
                                <input type="text"
                                       name="<?php self::set_params( 'shipping_number_meta_key' ) ?>"
                                       id="<?php self::set_params( 'shipping_number_meta_key', true ) ?>"
                                       class="<?php self::set_params( 'shipping_number_meta_key', true ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'shipping_number_meta_key' ) ) ?>">
                                <p><?php esc_html_e( 'If you customize checkout fields to add the shipping number field, please enter the order meta field which is used to store shipping number here.', 'woo-alidropship' ) ?></p>
                                <p><?php echo wp_kses_post(__( 'If you use <a target="_blank" href="https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/">Brazilian Market on WooCommerce</a>, please fill this option with <strong>_shipping_number</strong>', 'woo-alidropship' )) ?></p>
                                <p><?php echo wp_kses_post(__( '<strong>*Caution: </strong>If you already use a custom PHP snippet to append shipping number to order address via <strong>vi_wad_fulfillment_customer_info</strong> filter hook, please leave this field empty to avoid shipping number being added twice to order address which makes the address become incorrect.', 'woo-alidropship' )) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'billing_neighborhood_meta_key', true ) ?>"><?php esc_html_e( 'Billing neighborhood meta field', 'woo-alidropship' ) ?></label>
                            <td>
                                <input type="text"
                                       name="<?php self::set_params( 'billing_neighborhood_meta_key' ) ?>"
                                       id="<?php self::set_params( 'billing_neighborhood_meta_key', true ) ?>"
                                       class="<?php self::set_params( 'billing_neighborhood_meta_key', true ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'billing_neighborhood_meta_key' ) ) ?>">
                                <p><?php esc_html_e( 'If you customize checkout fields to add the billing neighborhood field, please enter the order meta field which is used to store billing neighborhood here.', 'woo-alidropship' ) ?></p>
                                <p><?php echo wp_kses_post(__( 'If you use <a target="_blank" href="https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/">Brazilian Market on WooCommerce</a>, please fill this option with <strong>_billing_neighborhood</strong>', 'woo-alidropship' )) ?></p>
                                <p><?php echo wp_kses_post(__( '<strong>*Caution: </strong>If you already use a custom PHP snippet to append billing neighborhood to order address via <strong>vi_wad_fulfillment_customer_info</strong> filter hook, please leave this field empty to avoid billing neighborhood being added twice to order address which makes the address become incorrect.', 'woo-alidropship' )) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'shipping_neighborhood_meta_key', true ) ?>"><?php esc_html_e( 'Shipping neighborhood meta field', 'woo-alidropship' ) ?></label>
                            <td>
                                <input type="text"
                                       name="<?php self::set_params( 'shipping_neighborhood_meta_key' ) ?>"
                                       id="<?php self::set_params( 'shipping_neighborhood_meta_key', true ) ?>"
                                       class="<?php self::set_params( 'shipping_neighborhood_meta_key', true ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'shipping_neighborhood_meta_key' ) ) ?>">
                                <p><?php esc_html_e( 'If you customize checkout fields to add the shipping neighborhood field, please enter the order meta field which is used to store shipping neighborhood here.', 'woo-alidropship' ) ?></p>
                                <p><?php echo wp_kses_post(__( 'If you use <a target="_blank" href="https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/">Brazilian Market on WooCommerce</a>, please fill this option with <strong>_shipping_neighborhood</strong>', 'woo-alidropship' )) ?></p>
                                <p><?php echo wp_kses_post(__( '<strong>*Caution: </strong>If you already use a custom PHP snippet to append shipping neighborhood to order address via <strong>vi_wad_fulfillment_customer_info</strong> filter hook, please leave this field empty to avoid shipping neighborhood being added twice to order address which makes the address become incorrect.', 'woo-alidropship' )) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'rut_meta_key', true ) ?>"><?php esc_html_e( 'RUT meta field', 'woo-alidropship' ) ?></label>
                            <td>
                                <input type="text"
                                       name="<?php self::set_params( 'rut_meta_key' ) ?>"
                                       id="<?php self::set_params( 'rut_meta_key', true ) ?>"
                                       class="<?php self::set_params( 'rut_meta_key', true ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'rut_meta_key' ) ) ?>">
                                <p><?php esc_html_e( 'The order meta field that a 3rd party plugin uses to store customer\'s RUT number.', 'woo-alidropship' ) ?></p>
                                <p><?php esc_html_e( 'RUT number is required when you fulfill orders of Customers from Chile.', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'fulfill_order_note', true ) ?>">
									<?php esc_html_e( 'AliExpress Order note', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                               <textarea type="text" id="<?php self::set_params( 'fulfill_order_note', true ) ?>"
                                         name="<?php self::set_params( 'fulfill_order_note' ) ?>"><?php echo wp_kses_post( self::$settings->get_params( 'fulfill_order_note' ) ) ?></textarea>
                                <p><?php esc_html_e( 'Add this note to AliExpress order when fulfilling with Chrome extension', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Show action', 'woo-alidropship' ) ?></th>
                            <td>
                                <select class="vi-wad-order-status-for-fulfill vi-ui fluid dropdown" multiple="multiple"
                                        name="<?php self::set_params( 'order_status_for_fulfill', false, true ) ?>">
									<?php
									$saved = self::$settings->get_params( 'order_status_for_fulfill' );
									foreach ( wc_get_order_statuses() as $key => $value ) {
										$selected = '';
										if ( is_array( $saved ) ) {
											$selected = in_array( $key, $saved ) ? 'selected' : '';
										}
										?>
                                        <option value="<?php echo esc_attr( $key ) ?>" <?php echo esc_attr( $selected ) ?>><?php echo esc_html( $value ) ?></option>
										<?php
									}
									?>
                                </select>
                                <p><?php esc_html_e( 'Only show action buttons for orders with status among these', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Change order status', 'woo-alidropship' ) ?></th>
                            <td>
								<?php
								$saved = self::$settings->get_params( 'order_status_after_sync' );
								?>
                                <select class="vi-wad-order-status-after-sync vi-ui fluid dropdown"
                                        name="<?php self::set_params( 'order_status_after_sync', false, false ) ?>">
                                    <option><?php esc_html_e( 'No change', 'woo-alidropship' ); ?></option>
									<?php
									foreach ( wc_get_order_statuses() as $key => $value ) {
										$selected = $key == $saved ? 'selected' : '';
										?>
                                        <option value="<?php echo esc_attr( $key ) ?>" <?php echo esc_attr( $selected ) ?>><?php echo esc_html( $value ) ?></option>
										<?php
									}
									?>
                                </select>
                                <p><?php esc_html_e( 'Automatically change order status after order id & tracking number of an order are synced successfully', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'update_order_auto', true ) ?>"><?php esc_html_e( 'Get tracking number automatically', 'woo-alidropship' ) ?></label>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                                <p><?php esc_html_e( 'When fulfilling orders, tracking number is not available yet. This function helps you check and sync tracking number automatically', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment <?php self::set_params( 'tab-content', true ) ?>"
                     data-tab="shipping">
                    <div class="vi-ui message positive">
                        <ul class="list">
                            <li><?php esc_html_e( 'This feature allows your customers to select shipping method for each item like you do on AliExpress', 'woo-alidropship' ) ?></li>
                            <li><?php esc_html_e( 'Shipping cost of all cart items will be calculated and applied to the cart so you should not add shipping cost to product price when importing AliExpress products to avoid making the final price of products paid by your customers too high', 'woo-alidropship' ) ?></li>
                            <li><?php echo wp_kses_post(sprintf( __( 'You have to create at least 1 shipping method in <a target="_blank" href="%s">WooCommerce settings/Shipping</a>', 'woo-alidropship' ), admin_url( 'admin.php?page=wc-settings&tab=shipping' ) )) //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></li>
                        </ul>
                    </div>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'ali_shipping', true ) ?>"><?php esc_html_e( 'Enable', 'woo-alidropship' ) ?></label>
                            <td>
                                <a class="vi-ui button" target="_blank"
                                   href="https://1.envato.market/PeXrM"><?php esc_html_e( 'Upgrade This Feature', 'woo-alidropship' ) ?></a>
                                <p class="description"><?php esc_html_e( 'All options below will only work if this option is enabled', 'woo-alidropship' ) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
				<?php
				if ( $this->orders_tracking_active ) {
					?>
                    <div class="vi-ui bottom attached tab segment" data-tab="tracking_carrier">
                        <div class="vi-ui positive tiny message">
                            <div class="header">
								<?php esc_html_e( 'Search and Replace', 'woo-alidropship' ); ?>
                            </div>
                            <ul class="list">
                                <li><?php echo wp_kses_post(__( 'This feature is used for <strong>Orders Tracking for WooCommerce</strong> plugin when syncing tracking info.', 'woo-alidropship' )); ?></li>
                                <li><?php echo wp_kses_post(__( 'When syncing orders with AliExpress, if Orders Tracking for WooCommerce plugin is active, it will automatically search for carrier URL in the existing carriers of this plugin (The <strong>Search and Replace</strong> function runs right before this step). If found, it will save tracking info with that carrier; otherwise, a new <strong>Custom carrier</strong> will be created.', 'woo-alidropship' )); ?></li>
                                <li><?php echo wp_kses_post(__( 'Skip if carrier is <strong>AliExpress Standard Shipping</strong>', 'woo-alidropship' )); ?></li>
                            </ul>
                        </div>
                        <div class="vi-ui segment string-replace-url">
                            <div class="vi-ui blue tiny message">
                                <div class="header">
									<?php esc_html_e( 'Replace carrier URL', 'woo-alidropship' ); ?>
                                </div>
                                <ul class="list">
                                    <li><?php esc_html_e( 'Replace carrier URL with respective URL below if DOMAIN of original carrier URL contains search strings(case-insensitive).', 'woo-alidropship' ); ?></li>
                                    <li><?php esc_html_e( 'Search will take place with priority from top to bottom and will STOP after first match.', 'woo-alidropship' ); ?></li>
                                </ul>
                            </div>
                            <table class="vi-ui table">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Search', 'woo-alidropship' ); ?></th>
                                    <th><?php esc_html_e( 'Replace carrier URL with', 'woo-alidropship' ); ?></th>
                                    <th style="width: 1%"><?php esc_html_e( 'Remove', 'woo-alidropship' ); ?></th>
                                </tr>
                                </thead>
                                <tbody>
								<?php
								$carrier_url_replaces       = self::$settings->get_params( 'carrier_url_replaces' );
								$carrier_url_replaces_count = 1;
								if ( ! empty( $carrier_url_replaces['from_string'] ) && ! empty( $carrier_url_replaces['to_string'] ) && is_array( $carrier_url_replaces['from_string'] ) ) {
									$carrier_url_replaces_count = count( $carrier_url_replaces['from_string'] );
								}
								for ( $i = 0; $i < $carrier_url_replaces_count; $i ++ ) {
									?>
                                    <tr class="clone-source">
                                        <td>
                                            <input type="text"
                                                   value="<?php echo esc_attr( isset( $carrier_url_replaces['from_string'][ $i ] ) ? $carrier_url_replaces['from_string'][ $i ] : '' ) ?>"
                                                   name="<?php echo esc_attr( self::set( 'carrier_url_replaces[from_string][]' ) ) ?>">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   placeholder="<?php esc_html_e( 'URL of a replacement carrier', 'woo-alidropship' ); ?>"
                                                   value="<?php echo esc_attr( isset( $carrier_url_replaces['to_string'][ $i ] ) ? $carrier_url_replaces['to_string'][ $i ] : '' ) ?>"
                                                   name="<?php echo esc_attr( self::set( 'carrier_url_replaces[to_string][]' ) ) ?>">
                                        </td>
                                        <td>
                                            <span class="vi-ui button negative tiny delete-string-replace-rule"><i
                                                        class="dashicons dashicons-trash"></i></span>
                                        </td>
                                    </tr>
									<?php
								}
								?>
                                </tbody>
                                <tfoot>
                                <tr>
                                    <th colspan="4">
                                        <span class="vi-ui button positive tiny add-string-replace-rule-url"><?php esc_html_e( 'Add', 'woo-alidropship' ); ?></span>
                                    </th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="vi-ui segment string-replace-name">
                            <div class="vi-ui blue tiny message">
                                <div class="header">
									<?php esc_html_e( 'Search and replace strings in Carrier name', 'woo-alidropship' ); ?>
                                </div>
                                <ul class="list">
                                    <li><?php esc_html_e( 'Search for strings in Carrier name and replace found strings with respective values.', 'woo-alidropship' ); ?></li>
                                    <li><?php echo wp_kses_post(__( 'This only works when new <strong>Custom carrier</strong> is created', 'woo-alidropship' )); ?></li>
                                </ul>
                            </div>
                            <table class="vi-ui table">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Search', 'woo-alidropship' ); ?></th>
                                    <th><?php esc_html_e( 'Case Sensitive', 'woo-alidropship' ); ?></th>
                                    <th><?php esc_html_e( 'Replace', 'woo-alidropship' ); ?></th>
                                    <th style="width: 1%"><?php esc_html_e( 'Remove', 'woo-alidropship' ); ?></th>
                                </tr>
                                </thead>
                                <tbody>
								<?php
								$carrier_name_replaces       = self::$settings->get_params( 'carrier_name_replaces' );
								$carrier_name_replaces_count = 1;
								if ( ! empty( $carrier_name_replaces['from_string'] ) && ! empty( $carrier_name_replaces['to_string'] ) && is_array( $carrier_name_replaces['from_string'] ) ) {
									$carrier_name_replaces_count = count( $carrier_name_replaces['from_string'] );
								}
								for ( $i = 0; $i < $carrier_name_replaces_count; $i ++ ) {
									$checked = $case_sensitive = '';
									if ( ! empty( $carrier_name_replaces['sensitive'][ $i ] ) ) {
										$checked        = 'checked';
										$case_sensitive = 1;
									}
									?>
                                    <tr class="clone-source">
                                        <td>
                                            <input type="text"
                                                   value="<?php echo esc_attr( isset( $carrier_name_replaces['from_string'][ $i ] ) ? $carrier_name_replaces['from_string'][ $i ] : '' ) ?>"
                                                   name="<?php echo esc_attr( self::set( 'carrier_name_replaces[from_string][]' ) ) ?>">
                                        </td>
                                        <td>
                                            <div class="<?php echo esc_attr( self::set( 'string-replace-sensitive-container' ) ) ?>">
                                                <input type="checkbox"
                                                       value="1" <?php echo esc_attr( $checked ) ?>
                                                       class="<?php echo esc_attr( self::set( 'string-replace-sensitive' ) ) ?>">
                                                <input type="hidden"
                                                       class="<?php echo esc_attr( self::set( 'string-replace-sensitive-value' ) ) ?>"
                                                       value="<?php echo esc_attr( $case_sensitive ) ?>"
                                                       name="<?php echo esc_attr( self::set( 'carrier_name_replaces[sensitive][]' ) ) ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   placeholder="<?php esc_html_e( 'Leave blank to delete matches', 'woo-alidropship' ); ?>"
                                                   value="<?php echo esc_attr( isset( $carrier_name_replaces['to_string'][ $i ] ) ? $carrier_name_replaces['to_string'][ $i ] : '' ) ?>"
                                                   name="<?php echo esc_attr( self::set( 'carrier_name_replaces[to_string][]' ) ) ?>">
                                        </td>
                                        <td>
                                            <span class="vi-ui button negative tiny delete-string-replace-rule">
                                                <i class="dashicons dashicons-trash"> </i>
                                            </span>
                                        </td>
                                    </tr>
									<?php
								}
								?>
                                </tbody>
                                <tfoot>
                                <tr>
                                    <th colspan="4">
                                        <span class="vi-ui button positive tiny add-string-replace-rule-name"><?php esc_html_e( 'Add', 'woo-alidropship' ); ?></span>
                                    </th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
					<?php
				}
				?>
                <p class="<?php echo esc_attr( self::set( 'save-settings-container' ) ) ?>">
                    <button type="submit" class="vi-ui button labeled icon primary <?php echo esc_attr( self::set( 'save-settings' ) ) ?>" name="<?php echo esc_attr( self::set( 'save-settings', true ) ) ?>">
                        <i class="save icon"> </i>
						<?php esc_html_e( 'Save Settings', 'woo-alidropship' ) ?>
                    </button>
					<?php VI_WOO_ALIDROPSHIP_DATA::chrome_extension_buttons(); ?>
                </p>
            </form>
			<?php do_action( 'villatheme_support_woo-alidropship' ) ?>
        </div>
		<?php
	}

	/**
	 *
	 */
	protected function set_nonce() {
		wp_nonce_field( 'wooaliexpressdropship_save_settings', '_wooaliexpressdropship_nonce' );
	}

	public static function set_params( $name = '', $class = false, $multiple = false ) {
		if ( $name ) {
			if ( $class ) {
				echo esc_attr( 'vi-wad-' . str_replace( '_', '-', $name ) );
			} else {
				if ( $multiple ) {
					echo esc_attr( 'wad_' . $name . '[]' );
				} else {
					echo esc_attr( 'wad_' . $name );
				}
			}
		}
	}

	/**
	 * Register a custom menu page.
	 */
	public function admin_menu() {
		add_submenu_page(
			'woo-alidropship-import-list',
			esc_html__( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce Settings', 'woo-alidropship' ),
			esc_html__( 'Settings', 'woo-alidropship' ),
			'manage_options',
			'woo-alidropship',
			array( $this, 'page_callback' )
		);
	}

	public static function create_ajax_nonce() {
		return apply_filters( 'vi_wad_admin_ajax_nonce', wp_create_nonce( 'woo_alidropship_admin_ajax' ) );
	}

	public static function check_ajax_referer( $page = 'woo-alidropship' ) {
		if ( ! apply_filters( 'vi_wad_verify_ajax_nonce', false, $page ) ) {
			check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
		}
	}
}