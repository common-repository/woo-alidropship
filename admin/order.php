<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_ALIDROPSHIP_Admin_Order {
	private $settings;
	private $is_orders_tracking_active;

	public function __construct() {
		$this->settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();
		//Add column in Order page
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'column_callback_order' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'wc_orders_column_callback_order' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'woocommerce_order_item_display_meta_key' ), 99, 3 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'woocommerce_order_item_display_meta_value' ), 99, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'woocommerce_hidden_order_itemmeta' ) );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'woocommerce_after_order_itemmeta' ), 10, 3 );
		add_action( 'wp_ajax_vi_wad_manually_update_ali_order_id', array( $this, 'update_ali_order_id' ) );
		add_action( 'wp_ajax_vi_wad_ali_order_detail', array( $this, 'get_ali_order_detail' ) );
		add_filter( 'posts_where', array( $this, 'filter_where' ), 10, 2 );
		add_action( 'woocommerce_new_order_item', array( $this, 'add_order_item_meta' ), 10, 2 );
		add_filter( 'views_edit-shop_order', array( $this, 'ali_filter' ) );
		add_filter( 'views_woocommerce_page_wc-orders', array( $this, 'wc_orders_ali_filter' ) );
		add_action( 'woocommerce_order_actions_end', array( $this, 'order_ali_button' ) );

//		add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'woocommerce_shop_order_search_ali_order' ) );
		add_filter( 'posts_where', array( $this, 'posts_where' ), 1, 2 );
		add_filter( 'woocommerce_orders_table_query_clauses', [ $this, 'add_items_query' ] );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', [ $this, 'add_order_filter_status' ] );
	}


	public function posts_join( $join, $wp_query ) {
		global $wpdb;
		$join .= " LEFT JOIN {$wpdb->prefix}woocommerce_order_items as vi_wad_woocommerce_order_items ON $wpdb->posts.ID=vi_wad_woocommerce_order_items.order_id";
		$join .= " LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as vi_wad_woocommerce_order_itemmeta ON vi_wad_woocommerce_order_items.order_item_id=vi_wad_woocommerce_order_itemmeta.order_item_id";

		return $join;
	}

	public function posts_where( $where, $wp_query ) {
		global $wpdb;
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if (  $post_type === 'shop_order'  &&  $post_status === 'ali_filter' ) {
			$order_status_for_fulfill = $this->settings->get_params( 'order_status_for_fulfill' );
			$search    = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$where .= $wpdb->prepare( " OR (vi_wad_woocommerce_order_itemmeta.meta_key='_vi_wad_aliexpress_order_id' AND vi_wad_woocommerce_order_itemmeta.meta_value = %s)", $search );
			if ( $order_status_for_fulfill ) {
				$where .= " AND wp_posts.post_status IN ( '" . implode( "','", $order_status_for_fulfill ) . "' )";
			}
            add_filter( 'posts_join', array( $this, 'posts_join' ), 10, 2 );
			add_filter( 'posts_distinct', array( $this, 'posts_distinct' ), 10, 2 );
		}

		return $where;
	}

	public function posts_distinct( $join, $wp_query ) {
		return 'DISTINCT';
	}

	public function woocommerce_shop_order_search_ali_order( $search_fields ) {
		$search_fields[] = '_ali_order_index';

		return $search_fields;
	}

	/**
	 * Update Ali order ID manually
	 *
	 * @throws Exception
	 */
	public function update_ali_order_id() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
		$ali_order_id = isset( $_POST['ali_order_id'] ) ? trim( sanitize_text_field( $_POST['ali_order_id'] ) ) : '';
		$item_id      = isset( $_POST['item_id'] ) ? sanitize_text_field( $_POST['item_id'] ) : '';
		$response     = array(
			'status'          => 'error',
			'message'         => '',
			'text'            => '',
			'delete_tracking' => 'no',
		);
		if ( $item_id ) {
			if ( wc_update_order_item_meta( $item_id, '_vi_wad_aliexpress_order_id', $ali_order_id ) ) {
				if ( $ali_order_id ) {
					wc_update_order_item_meta( $item_id, '_vi_wad_aliexpress_order_item_status', 'processing' );
					$response['text'] = $this->status_switch( 'processing' );
				} else {
					wc_update_order_item_meta( $item_id, '_vi_wad_aliexpress_order_item_status', 'pending' );
					$response['text'] = $this->status_switch( 'pending' );
				}
				$item_tracking_data = wc_get_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', true );
				if ( $item_tracking_data ) {
					$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
					$current_tracking_data = array_pop( $item_tracking_data );
					if ( $current_tracking_data['tracking_number'] || ( $current_tracking_data['carrier_slug'] && $current_tracking_data['carrier_url'] && $current_tracking_data['carrier_name'] ) ) {
						$item_tracking_data[] = array(
							'tracking_number' => '',
							'carrier_slug'    => '',
							'carrier_url'     => '',
							'carrier_name'    => '',
							'carrier_type'    => '',
							'time'            => time(),
						);
						if ( wc_update_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', wp_json_encode( $item_tracking_data ) ) ) {
							$response['delete_tracking'] = 'yes';
						}
					}
				}

				$response['status'] = 'success';
			}
		}
		wp_send_json( $response );
	}

	public function status_switch( $stt ) {
		$pattern = array(
			'pending'    => array( __( 'To Order', 'woo-alidropship' ), 'red' ),
			'processing' => array( __( 'Processing', 'woo-alidropship' ), '#0089F7' ),
			'shipped'    => array( __( 'Shipped', 'woo-alidropship' ), '#00B400' ),
		);

		return isset( $pattern[ $stt ] ) ? $pattern[ $stt ] : $pattern['pending'];
	}

	public function admin_enqueue_scripts( $page ) {
		global $post_type;
		$screen = get_current_screen();
		if ( ( $page === 'post.php' && $screen->id === 'shop_order' ) || ( $page == 'woocommerce_page_wc-orders' && ! empty( $_GET['action'] ) && $_GET['action'] == 'edit' ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_enqueue_style( 'woo-alidropship-admin-edit-order', VI_WOO_ALIDROPSHIP_CSS . 'admin-order.css', '', VI_WOO_ALIDROPSHIP_VERSION );
			wp_enqueue_script( 'woo-alidropship-admin-edit-order', VI_WOO_ALIDROPSHIP_JS . 'admin-order.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false );
			if ( class_exists( 'VIWOT_WOO_ORDERS_TRACKING' ) || class_exists( 'VI_WOOCOMMERCE_ORDERS_TRACKING_DATA' ) ) {
				$this->is_orders_tracking_active = true;
			} else {
				$this->is_orders_tracking_active = false;
			}
			wp_localize_script( 'woo-alidropship-admin-edit-order', 'vi_wad_edit_order', array(
				'url'                => admin_url( 'admin-ajax.php' ),
				'_vi_wad_ajax_nonce' => VI_WOO_ALIDROPSHIP_Admin_Settings::create_ajax_nonce(),
			) );
		} elseif ( ( $page === 'edit.php' && $post_type === 'shop_order' ) || ( $page == 'woocommerce_page_wc-orders' && empty( $_GET['action'] ) ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_enqueue_style( 'woo-alidropship-popup', VI_WOO_ALIDROPSHIP_CSS . 'popup.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			wp_enqueue_style( 'woo-alidropship-order-status', VI_WOO_ALIDROPSHIP_CSS . 'order-status.css', '', VI_WOO_ALIDROPSHIP_VERSION );
			wp_enqueue_script( 'woo-alidropship-order-status', VI_WOO_ALIDROPSHIP_JS . 'order-status.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION , false);
			wp_localize_script( 'woo-alidropship-order-status', 'orderStt', array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'_vi_wad_ajax_nonce' => VI_WOO_ALIDROPSHIP_Admin_Settings::create_ajax_nonce(),
			) );
			wp_add_inline_script( 'woo-alidropship-order-status', $this->sync_orders_button() );
		}
	}
	public function sync_orders_button() {
		ob_start();
		?>
        <script type="text/javascript">
            'use strict';
            jQuery(document).ready(function ($) {
                jQuery(".wrap .page-title-action").eq(0).after("<a class='page-title-action' target='_blank' href='<?php echo esc_url( VI_WOO_ALIDROPSHIP_DATA::get_get_tracking_url() ) ?>'><?php esc_html_e( 'AliExpress sync', 'woo-alidropship' ) ?></a>");
            });
        </script>
		<?php
		return  preg_replace( '#<script[^>]*>(.*)</script>#is', '$1', ob_get_clean() ) ;
	}

	public function woocommerce_hidden_order_itemmeta( $hidden_order_itemmeta ) {
		$hidden_order_itemmeta[] = '_vi_wad_match_aliexpress_order_id';
		$hidden_order_itemmeta[] = '_vi_wad_aliexpress_order_id';
		$hidden_order_itemmeta[] = '_vi_order_item_tracking_code';
		$hidden_order_itemmeta[] = '_vi_wad_aliexpress_order_item_status';
		$hidden_order_itemmeta[] = '_vi_wot_order_item_tracking_data';

		return $hidden_order_itemmeta;
	}

	/**
	 * @param $item_id
	 * @param $item
	 * @param $product WC_Product
	 *
	 * @throws Exception
	 */
	public function woocommerce_after_order_itemmeta( $item_id, $item, $product ) {
		global $theorder;

		if ( ! $theorder || ! is_a( $item, 'WC_Order_Item_Product' ) || ! is_object( $product ) ) {
			return;
		}

		$order_id   = $theorder->get_id();
		$product_id = $product->get_id();

		if ( ! get_post_meta( $product_id, '_vi_wad_aliexpress_product_id', true ) && ! get_post_meta( $product_id, '_vi_wad_aliexpress_variation_id', true ) ) {
			return;
		}

		$aliexpress_order_id = wc_get_order_item_meta( $item_id, '_vi_wad_aliexpress_order_id', true );
		$ali_order_detail    = $tracking_url = $tracking_url_btn = '';
		if ( $aliexpress_order_id ) {
			$ali_order_detail = "https://trade.aliexpress.com/order_detail.htm?orderId={$aliexpress_order_id}";
			$tracking_url     = "http://track.aliexpress.com/logisticsdetail.htm?tradeId={$aliexpress_order_id}";
			$tracking_url_btn = VI_WOO_ALIDROPSHIP_DATA::get_get_tracking_url( $aliexpress_order_id );
		}
		$item_tracking_data    = wc_get_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', true );
		$current_tracking_data = array(
			'tracking_number' => '',
			'carrier_slug'    => '',
			'carrier_url'     => '',
			'carrier_name'    => '',
			'carrier_type'    => '',
			'time'            => time(),
		);
		if ( $item_tracking_data ) {
			$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
			$current_tracking_data = array_pop( $item_tracking_data );
		}
		$tracking_number = apply_filters( 'vi_woo_orders_tracking_current_tracking_number', $current_tracking_data['tracking_number'], $item_id, $order_id );
		$carrier_url     = apply_filters( 'vi_woo_orders_tracking_current_tracking_url', $current_tracking_data['carrier_url'], $item_id, $order_id );
		$carrier_name    = apply_filters( 'vi_woo_orders_tracking_current_carrier_name', $current_tracking_data['carrier_name'], $item_id, $order_id );
		$carrier_slug    = apply_filters( 'vi_woo_orders_tracking_current_carrier_slug', $current_tracking_data['carrier_slug'], $item_id, $order_id );
		$get_tracking    = array( 'item-actions-get-tracking' );
		if ( ! $aliexpress_order_id ) {
			$get_tracking[] = 'hidden';
		}
		?>
        <div class="<?php echo esc_attr( self::set( 'container' ) ) ?>">
            <div class="<?php echo esc_attr( self::set( array(
				'item-details',
				'item-ali-order-id'
			) ) ) ?>"
                 data-product_item_id="<?php echo esc_attr( $item_id ) ?>">
                <div class="<?php echo esc_attr( self::set( 'item-label' ) ) ?>">
                    <span><?php esc_html_e( 'Ali Order ID', 'woo-alidropship' ) ?></span>
                </div>
                <div class="<?php echo esc_attr( self::set( 'item-value' ) ) ?>">
                    <a class="<?php echo esc_attr( self::set( 'ali-order-id' ) ) ?>"
                       href="<?php echo esc_attr( $ali_order_detail ) ?>"
                       data-old_ali_order_id="<?php echo esc_attr( $aliexpress_order_id ) ?>"
                       target="_blank">
                        <input readonly
                               class="<?php echo esc_attr( self::set( array( 'ali-order-id-input' ) ) ) ?>"
                               value="<?php echo esc_attr( $aliexpress_order_id ) ?>">
                    </a>
                </div>
                <div class="<?php echo esc_attr( self::set( 'item-actions' ) ) ?>">
                    <span class="dashicons dashicons-edit <?php echo esc_attr( self::set( 'item-actions-edit' ) ) ?>"
                          title="<?php esc_attr_e( 'Edit', 'woo-alidropship' ) ?>">
                    </span>
                    <span class="dashicons dashicons-yes <?php echo esc_attr( self::set( array(
						'item-actions-save',
						'hidden'
					) ) ) ?>"
                          title="<?php esc_attr_e( 'Save', 'woo-alidropship' ) ?>">
                    </span>
                    <span class="dashicons dashicons-no-alt <?php echo esc_attr( self::set( array(
						'item-actions-cancel',
						'hidden'
					) ) ) ?>"
                          title="<?php esc_attr_e( 'Cancel', 'woo-alidropship' ) ?>">
                    </span>
					<?php
					if ( $this->is_orders_tracking_active ) {
						?>
                        <a href="<?php echo esc_attr( $tracking_url_btn ) ?>" target="_blank">
                            <span class="dashicons dashicons-arrow-down-alt <?php echo esc_attr( self::set( $get_tracking ) ) ?>"
                                  title="<?php esc_attr_e( 'Get tracking', 'woo-alidropship' ) ?>">
                            </span>
                        </a>
						<?php
					}
					?>
                </div>
                <div class="<?php echo esc_attr( self::set( array(
					'item-value-overlay',
					'hidden'
				) ) ) ?>"></div>
            </div>
			<?php
			if ( ! $this->is_orders_tracking_active ) {
				?>
                <div class="<?php echo esc_attr( self::set( array(
					'item-details',
					'item-tracking-number'
				) ) ) ?>" data-product_item_id="<?php echo esc_attr( $item_id ) ?>">
                    <div class="<?php echo esc_attr( self::set( 'item-label' ) ) ?>">
                        <span><?php esc_html_e( 'Tracking number', 'woo-alidropship' ) ?></span>
                    </div>
                    <div class="<?php echo esc_attr( self::set( 'item-value' ) ) ?>">
                        <a class="<?php echo esc_attr( self::set( 'ali-tracking-number' ) ) ?>"
                           href="<?php echo esc_attr( $tracking_url ) ?>"
                           target="_blank">
                            <input readonly
                                   class="<?php echo esc_attr( self::set( array( 'ali-tracking-number-input' ) ) ) ?>"
                                   value="<?php echo esc_attr( $tracking_number ) ?>">
                        </a>
                    </div>
                    <div class="<?php echo esc_attr( self::set( 'item-actions' ) ) ?>">
                        <a href="<?php echo esc_attr( $tracking_url_btn ) ?>" target="_blank">
                            <span class="dashicons dashicons-arrow-down-alt <?php echo esc_attr( self::set( $get_tracking ) ) ?>"
                                  title="<?php esc_attr_e( 'Get tracking', 'woo-alidropship' ) ?>">
                            </span>
                        </a>
                    </div>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}

	private static function set( $name, $set_name = false ) {
		return VI_WOO_ALIDROPSHIP_DATA::set( $name, $set_name );
	}

	public function woocommerce_order_item_display_meta_key( $display_key, $meta, $item ) {
		if ( $meta->key === '_vi_wad_match_aliexpress_order_id' ) {
			$display_key = esc_html__( 'AliExpress order ID', 'woo-alidropship' );
		}

		return $display_key;
	}

	public function woocommerce_order_item_display_meta_value( $display_value, $meta, $item ) {
		if ( $meta->key === '_vi_wad_match_aliexpress_order_id' ) {
			$value = $meta->value;
			if ( $value ) {
				$display_value = sprintf( '<a target="_blank" href="https://trade.aliexpress.com/order_detail.htm?orderId=%s">%s</a>', $value, $value );
			}
		}

		return $display_value;
	}

	/**
	 * @param $item_id
	 * @param $values
	 *
	 * @throws Exception
	 */
	public function add_order_item_meta( $item_id, $values ) {
		$pid = $values['product_id'];
		if ( get_post_meta( $pid, '_vi_wad_aliexpress_product_id', true ) ) {
			wc_update_order_item_meta( $item_id, '_vi_wad_aliexpress_order_item_status', 'pending' );
			wc_update_order_item_meta( $item_id, '_vi_wad_aliexpress_order_id', '' );
		}
	}

	/**
	 * @param $col_id
	 * @param $order_id
	 *
	 * @throws Exception
	 */
	public function column_callback_order( $col_id, $order_id ) {
		if ( $col_id === 'order_number' ) {
			$order          = wc_get_order( $order_id );
			$statuses       = $this->settings->get_params( 'order_status_for_fulfill' );
			$order_items    = $order->get_items();
			$fulfill_action = $status = $ali_product_id = $ali_pid = '';
			$total          = $ordered = $shipped = $tracking_number = 0;
			$order_stt      = $color = '';

			if ( count( $order_items ) ) {
				foreach ( $order_items as $item_id => $order_item ) {
					$pid            = $order_item->get_data()['product_id'];
					$ali_product_id = get_post_meta( $pid, '_vi_wad_aliexpress_product_id', true );
					$ali_pid        = $ali_product_id ? $ali_product_id : $ali_pid;
					if ( $ali_product_id ) {
						$item_tracking_data = wc_get_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', true );
						if ( $item_tracking_data ) {
							$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
							$current_tracking_data = array_pop( $item_tracking_data );
							if ( $current_tracking_data['tracking_number'] ) {
								$tracking_number ++;
							}
						}
						if ( $order_item->get_meta( '_vi_wad_aliexpress_order_id' ) ) {
							$ordered ++;
						}
						if ( $order_item->get_meta( '_vi_wad_aliexpress_order_item_status' ) == 'shipped' ) {
							$shipped ++;
						}
						$total ++;
					}
				}

				if ( $total && $ali_pid && is_array( $statuses ) && in_array( 'wc-' . $order->get_status(), $statuses ) ) {

					$order_rate    = $ordered / $total;
					$tracking_rate = $tracking_number / $total;
					$shipped_rate  = $shipped / $total;

					$href   = add_query_arg( array(
						'fromDomain'  => urlencode( site_url() ),
						'orderID'     => $order_id,
						'fromProduct' => $ali_pid
					), 'https://www.aliexpress.com' );
					$target = '_blank';

					if ( $shipped_rate == 1 ) {
						$order_stt = __( 'Shipped', 'woo-alidropship' );
						$color     = 'shipped';
					} else {
						if ( $order_rate == 0 && $tracking_rate == 0 ) {
							$order_stt = __( 'To Order', 'woo-alidropship' );
							$color     = 'to-order';
						} elseif ( $order_rate < 1 && $tracking_rate <= 1 ) {
							$order_stt = __( 'Processing', 'woo-alidropship' );
							$color     = 'processing';
						} elseif ( $order_rate == 1 && $tracking_rate < 1 ) {
							$order_stt = __( 'Processing', 'woo-alidropship' );
							$color     = 'full-processing';
							$href      = 'javascript:void(0)';
							$target    = '';
						} elseif ( $order_rate == 1 && $tracking_rate == 1 ) {
							$order_stt = __( 'In transit', 'woo-alidropship' );
							$color     = 'completed';
							$href      = 'javascript:void(0)';
							$target    = '';
						}
					}

					$tooltip        = 'Light green: No order  &#xa;Orange: Not enough order & tracking code &#xa;Gray: Not enough tracking code &#xa;Light blue: Full tracking code';
					$fulfill_action = "<a data-tooltip='{$tooltip}' data-position='bottom center' data-inverted='' class='wad-fulfill-button' target='{$target}' href='" . esc_attr( $href ) . "'>" . $order_stt . "</a>";
					$status         = "<button type='button' class='wad-show-detail {$color}' data-id='{$order_id}'><i class='wad-icon dashicons dashicons-arrow-down wad-spinner'></i></button>"; //<span class='wad-shipped-status {$shipped_color}'>{$shipped_view} </span>
				}
			}

			echo wp_kses_post("<div class='wad-fulfill-group {$color}'>" . $fulfill_action . $status . '</div>');
		}
	}

	public function wc_orders_column_callback_order( $col_id, $order ) {
		$order_id = $order->get_id();
		$this->column_callback_order( $col_id, $order_id );
	}

	/**
	 * @throws Exception
	 */
	public function get_ali_order_detail() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
		$order_id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
		if ( ! $order_id ) {
			wp_die();
		}

		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();
		$res         = '';

		if ( ! empty( $order_items ) ) {
			foreach ( $order_items as $item ) {
				$item_id           = $item->get_id();
				$item_data         = $item->get_data();
				$vid               = $item_data['variation_id'] ? $item_data['variation_id'] : $item_data['product_id'];
				$pid               = $item_data['product_id'];
				$name              = $item_data['name'];
				$ali_pid           = get_post_meta( $pid, '_vi_wad_aliexpress_product_id', true );
				$variation_product = wc_get_product( $vid );
				if ( ! $variation_product ) {
					continue;
				}
				$link               = $variation_product->get_permalink();
				$sku                = $variation_product->get_sku();
				$ali_order_id       = $item->get_meta( '_vi_wad_aliexpress_order_id' );
				$item_tracking_data = wc_get_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', true );
				$tracking_number    = '';
				$carrier_name       = '';
				$carrier_url        = '';
				if ( $item_tracking_data ) {
					$item_tracking_data    = vi_wad_json_decode( $item_tracking_data );
					$current_tracking_data = array_pop( $item_tracking_data );
					$current_tracking_data = apply_filters( 'vi_woo_alidropship_order_item_tracking_data', $current_tracking_data, $item_id, $order_id );
					$tracking_number       = $current_tracking_data['tracking_number'];
					$carrier_name          = $current_tracking_data['carrier_name'];
					$carrier_url           = $current_tracking_data['carrier_url'];
				}
				$status = $item->get_meta( '_vi_wad_aliexpress_order_item_status' );
				if ( $ali_pid ) {
					$color = $item_stt = $manual = '';

					if ( $status == 'shipped' ) {
						$item_stt = "<span class='wad-item-stt shipped'>" . esc_html__( 'Shipped', 'woo-alidropship' ) . "</span>";
					} else {
						if ( ! $ali_order_id ) {
							$item_stt = "<span class='wad-item-stt to-order'>" . esc_html__( 'To order', 'woo-alidropship' ) . "</span>";
						} elseif ( $ali_order_id && ! $tracking_number ) {
							$item_stt = "<span class='wad-item-stt processing'>" . esc_html__( 'Processing', 'woo-alidropship' ) . "</span>";
						} elseif ( $ali_order_id && $tracking_number ) {
							$item_stt = "<span class='wad-item-stt completed'>" . esc_html__( 'In transit', 'woo-alidropship' ) . "</span>";
						}
					}

					if ( ! $ali_order_id ) {
						$manual = "<a class='wad-manual-btn' href='https://www.aliexpress.com/item/$ali_pid.html' target='_blank'>" . esc_html__( 'Manual', 'woo-alidropship' ) . "</a>";
					}
					ob_start();
					?>
                    <div class='wad-ali-order-item <?php echo esc_attr( $color ) ?>'>
                        <div>
							<?php echo VI_WOO_ALIDROPSHIP_DATA::wp_kses_post( $item_stt ), VI_WOO_ALIDROPSHIP_DATA::wp_kses_post( $manual ) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <a class="wad-order-item-name"
                               href="<?php echo esc_url( $link ) ?>"><?php echo esc_html( $name ) ?></a>
                        </div>
                        <div>
                            <table class="wad-list-ali-order-items">
                                <tr>
                                    <td>
										<?php
										esc_html_e( 'SKU: ', 'woo-alidropship' );
										echo esc_html( $sku );
										?>
                                    </td>
                                    <td>
										<?php
										esc_html_e( 'Order ID: ', 'woo-alidropship' );
										?>
                                        <a target="_blank"
                                           href="https://trade.aliexpress.com/order_detail.htm?orderId=<?php echo esc_attr( $ali_order_id ) ?>"
                                           class="wad-ali-product-link"><?php echo esc_html( $ali_order_id ) ?></a>
                                        <input type="text" name="wad_ali_order_ID" class="wad-ali-order-id"
                                               value="<?php echo esc_attr( $ali_order_id ) ?>">
                                        <span data-tooltip="Save" data-inverted=''>
                                            <i class="wad-icon dashicons dashicons-yes"></i>
                                        </span>
                                        <span data-tooltip="Edit" data-inverted=''>
                                            <i class="wad-icon dashicons dashicons-edit"></i>
                                        </span>
                                    </td>
                                    <td class="wad-column">
										<?php
										echo esc_html__( 'Tracking code: ', 'woo-alidropship' );
										if ( $carrier_url ) {
											?>
                                            <a href="<?php echo esc_attr( $carrier_url ) ?>"
                                               target="_blank"><?php echo esc_html( $tracking_number ) ?></a>
											<?php
										} else {
											?>
                                            <span><?php echo esc_html( $tracking_number ) ?></span>
											<?php
										}
										if ( $ali_order_id ) {
											?>
                                            <a href="<?php echo esc_url( VI_WOO_ALIDROPSHIP_DATA::get_get_tracking_url( $ali_order_id ) ) ?>"
                                               target="_blank" class="wad-get-tracking-code-manual">
                                                <i class="dashicons dashicons-arrow-down-alt"></i>
                                            </a>
											<?php
										}

										?>
                                    </td>
                                    <td>
										<?php
										if ( $carrier_name ) {
											esc_html_e( 'Carrier: ', 'woo-alidropship' );
											if ( $carrier_url ) {
												?>
                                                <a href="<?php echo esc_attr( $carrier_url ) ?>"
                                                   target="_blank"><?php echo esc_html( $carrier_name ) ?></a>
												<?php
											} else {
												?>
                                                <span><?php echo esc_html( $carrier_name ) ?></span>
												<?php
											}
										}
										?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
					<?php
					$res .= ob_get_clean();
				}
			}
			if ( $res ) {
				wp_send_json_success( $res );
			} else {
				wp_send_json_error();
			}
		}
		wp_die();
	}

	public function filter_order() {
		if ( get_current_screen()->id === 'edit-shop_order' ) {
			$stt = '';
			if ( isset( $_GET['vi_wad_order_stt'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$stt = esc_attr( sanitize_text_field( $_GET['vi_wad_order_stt'] ) );// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			$options = array(
				''           => __( 'Filter by AliExpress order status', 'woo-alidropship' ),
				'pending'    => __( 'To Order', 'woo-alidropship' ),
				'processing' => __( 'Processing', 'woo-alidropship' ),
				'shipped'    => __( 'Shipped', 'woo-alidropship' ),
			);
			?>
            <select name="vi_wad_order_stt" class="wad-order-filter">
				<?php
				foreach ( $options as $option => $show ) {
					?>
                    <option value="<?php echo esc_attr( $option ) ?>" <?php selected( $stt, $option ) ?>><?php echo esc_html( $show ) ?></option>
					<?php
				}
				?>
            </select>
			<?php
		}
	}

	public function filter_where( $where, $wp_q ) {
		if ( isset( $_GET['post_status'], $_GET['post_type'] ) && sanitize_text_field( $_GET['post_status'] ) == 'ali_filter' && sanitize_text_field( $_GET['post_type'] ) == 'shop_order' ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			global $wpdb;
			$t_order_items    = $wpdb->prefix . "woocommerce_order_items";
			$t_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";
			$order_stt        = $this->settings->get_params( 'order_status_for_fulfill' );
			$order_stt        = implode( "','", $order_stt );
			$query            = "SELECT  wp_posts.ID FROM  $wpdb->posts AS wp_posts LEFT JOIN $t_order_items ON wp_posts.ID=$t_order_items.order_id ";
			$query            .= "LEFT JOIN $t_order_itemmeta ON $t_order_items.order_item_id=$t_order_itemmeta.order_item_id ";
			$query            .= " WHERE wp_posts.post_status IN ( '$order_stt' )AND $t_order_itemmeta.meta_key='_vi_wad_aliexpress_order_id' AND $t_order_itemmeta.meta_value=''";
			$where            .= " AND $wpdb->posts.ID IN( $query)";
		}

		//		if ( is_search() ) {
		//			if ( ! empty( $_GET['vi_wad_order_stt'] ) ) {
		//				$stt   = sanitize_text_field( $_GET['vi_wad_order_stt'] );
		//				$where .= " AND $wpdb->posts.ID IN(";
		//				$where .= " SELECT  $wpdb->posts.ID FROM  $wpdb->posts LEFT JOIN $t_order_items ON $wpdb->posts.ID=$t_order_items.order_id LEFT JOIN $t_order_itemmeta ON $t_order_items.order_item_id=$t_order_itemmeta.order_item_id WHERE $t_order_itemmeta.meta_key='_vi_wad_aliexpress_order_item_status' AND $t_order_itemmeta.meta_value='{$stt}')";
		//			}
		//		}

		return $where;
	}


	public function ali_filter( $views ) {
		global $wpdb;
		$t_order_items    = $wpdb->prefix . "woocommerce_order_items";
		$t_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";
		$count_posts      = 0;
		$ids              = array();
		$order_stt        = $this->settings->get_params( 'order_status_for_fulfill' );
		if ( $order_stt ) {
			$order_stt   = implode( "','", $order_stt );
			$query       = "SELECT COUNT(DISTINCT wp_posts.ID) FROM  $wpdb->posts AS wp_posts LEFT JOIN $t_order_items ON wp_posts.ID=$t_order_items.order_id ";
			$query       .= "LEFT JOIN $t_order_itemmeta ON $t_order_items.order_item_id=$t_order_itemmeta.order_item_id ";
			$query       .= " WHERE wp_posts.post_status IN ( '$order_stt' )AND $t_order_itemmeta.meta_key='_vi_wad_aliexpress_order_id' AND $t_order_itemmeta.meta_value=''";
			$count_posts = $wpdb->get_var( $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		$views['ali_filter'] = "<a href='edit.php?post_status=ali_filter&post_type=shop_order'>" . __( 'To order', 'woo-alidropship' ) . "</a>(" . $count_posts . ")";

		return $views;
	}

	public function wc_orders_ali_filter( $views ) {
		$current = ! empty( $_GET['status'] ) && $_GET['status'] == 'ali_filter' ? 'current' : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$views['ali_filter'] = sprintf( "<a href='%s' class='%s'>%s <span class='count'>(%s)</span></a>",
			esc_url( admin_url( 'admin.php?page=wc-orders&status=ali_filter' ) ),
			esc_attr( $current ),
			esc_html__( 'To order', 'woo-alidropship' ),
			esc_html( VI_WOO_ALIDROPSHIP_DATA::get_ali_orders() )
		);

		return $views;
	}

	public function order_ali_button( $order_id ) {
		$order       = new WC_Order( $order_id );
		$order_items = $order->get_items();
		$ali_pid     = '';

		if ( count( $order_items ) ) {
			foreach ( $order_items as $order_item ) {
				$pid            = $order_item->get_data()['product_id'];
				$ali_product_id = get_post_meta( $pid, '_vi_wad_aliexpress_product_id', true );
				$ali_pid        = $ali_product_id ? $ali_product_id : $ali_pid;
			}
		}
		$statuses = $this->settings->get_params( 'order_status_for_fulfill' );
		if ( is_array( $statuses ) && $ali_pid && in_array( 'wc-' . $order->get_status(), $statuses ) ) {
			$href = add_query_arg( array(
				'fromDomain'  => urlencode( site_url() ),
				'orderID'     => $order_id,
				'fromProduct' => $ali_pid
			), 'https://www.aliexpress.com' );
			?>
            <li class="wide">
                <div class="vi-wad-ali-order-btn">
                    <a href="<?php echo esc_url( $href ); ?>" target="_blank"
                       class="button"><?php esc_html_e( 'To Order AliExpress', 'woo-alidropship' ); ?></a>
                </div>
            </li>
			<?php
		}
	}

	public function add_items_query( $args ) {
		if ( isset( $_GET['status'], $_GET['page'] ) && $_GET['status'] === 'ali_filter' && $_GET['page'] === 'wc-orders' ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			global $wpdb;
			$args['join']  .= " LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}wc_orders.id={$wpdb->prefix}woocommerce_order_items.order_id";
			$args['join']  .= " LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta ON {$wpdb->prefix}woocommerce_order_items.order_item_id={$wpdb->prefix}woocommerce_order_itemmeta.order_item_id";
			$args['where'] .= " AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_key='_vi_wad_aliexpress_order_id'";
		}

		return $args;
	}

	public function add_order_filter_status( $order_query_args ) {
		if (! empty( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wc-orders'){// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current = ! empty( $_GET['status'] ) && sanitize_text_field( wp_unslash( $_GET['status'] ) ) === 'ali_filter' ? 'current' : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}else{
			$current = ! empty( $_GET['post_status'] ) && sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) === 'ali_filter' ? 'current' : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if (!$current){
			return  $order_query_args;
		}
		$order_status_for_fulfill   = $this->settings->get_params( 'order_status_for_fulfill' );
		$order_query_args['status'] = $order_status_for_fulfill;

		return $order_query_args;
	}
}
