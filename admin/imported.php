<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOO_ALIDROPSHIP_Admin_Imported
 */
class VI_WOO_ALIDROPSHIP_Admin_Imported {
	private static $settings;
	private $product_count;

	public function __construct() {
		self::$settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();
		add_action( 'admin_init', array( $this, 'cancel_overriding' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 999999 );
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
		add_action( 'wp_ajax_vi_wad_override_product', array( $this, 'override_product' ) );
		add_action( 'wp_ajax_vi_wad_trash_product', array( $this, 'trash' ) );
		add_action( 'wp_ajax_vi_wad_restore_product', array( $this, 'restore' ) );
		add_action( 'admin_head', array( $this, 'menu_product_count' ), 999 );
		add_action( 'wp_ajax_vi_wad_delete_product', array( $this, 'delete' ) );
	}

	public function delete() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		vi_wad_set_time_limit();
		$product_id         = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
		$woo_product_id     = isset( $_POST['woo_product_id'] ) ? sanitize_text_field( $_POST['woo_product_id'] ) : '';
		$delete_woo_product = isset( $_POST['delete_woo_product'] ) ? sanitize_text_field( $_POST['delete_woo_product'] ) : '';
		if ( $delete_woo_product != self::$settings->get_params( 'delete_woo_product' ) ) {
			$args                       = self::$settings->get_params();
			$args['delete_woo_product'] = $delete_woo_product;
			update_option( 'wooaliexpressdropship_params', $args );
		}
		$response = array(
			'status'  => 'success',
			'message' => '',
		);
		if ( $product_id ) {
			if ( Ali_Product_Table::get_post( $product_id ) ) {
				$delete = Ali_Product_Table::wp_delete_post( $product_id, true );
				if ( false === $delete ) {
					$response['status']  = 'error';
					$response['message'] = esc_html__( 'Can not delete product', 'woo-alidropship' );
				}
			}

			if ( $woo_product_id && get_post( $woo_product_id ) ) {
				delete_post_meta( $woo_product_id, '_vi_wad_aliexpress_product_id' );
				delete_post_meta( $woo_product_id, '_vi_wad_aliexpress_variation_attr' );
				delete_post_meta( $woo_product_id, '_vi_wad_aliexpress_variation_ship_from' );
				delete_post_meta( $woo_product_id, '_vi_wad_migrate_from_id' );
				if ( 1 == $delete_woo_product ) {
					$delete = wp_delete_post( $woo_product_id, true );
					if ( false === $delete ) {
						$response['status']  = 'error';
						$response['message'] = esc_html__( 'Can not delete product', 'woo-alidropship' );
					}
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( $response );
	}

	public function cancel_overriding() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $page === 'woo-alidropship-imported-list' ) {
			$overridden_product = isset( $_REQUEST['overridden_product'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['overridden_product'] ) ) : '';
			$cancel_overriding  = isset( $_REQUEST['cancel_overriding'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cancel_overriding'] ) ) : '';
			$_wpnonce           = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( $overridden_product && $cancel_overriding && wp_verify_nonce( $_wpnonce, 'cancel_overriding_nonce' ) ) {
				$product = Ali_Product_Table::get_post( $cancel_overriding );
				if ( $product && $product->post_status === 'override' && $product->post_parent == $overridden_product ) {
					Ali_Product_Table::wp_update_post( array(
						'ID'          => $cancel_overriding,
						'post_parent' => '',
						'post_status' => 'draft',
					) );
				}
				wp_safe_redirect( remove_query_arg( array( 'cancel_overriding', '_wpnonce', 'overridden_product' ) ) );
				exit();
			}
		}
	}

	public function admin_enqueue_scripts() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		global $pagenow;
		if ( $pagenow !== 'admin.php' ) {
			return;
		}
		if ( $page === 'woo-alidropship-imported-list' ) {
			VI_WOO_ALIDROPSHIP_Admin_Settings::enqueue_semantic();
			wp_enqueue_style( 'woo-alidropship-admin-imported-style', VI_WOO_ALIDROPSHIP_CSS . 'imported-list.css', '', VI_WOO_ALIDROPSHIP_VERSION );
			wp_enqueue_script( 'woo-alidropship-imported-list', VI_WOO_ALIDROPSHIP_JS . 'imported-list.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false );
			wp_localize_script( 'woo-alidropship-imported-list', 'vi_wad_imported_list_params', array(
					'url'                => admin_url( 'admin-ajax.php' ),
					'_vi_wad_ajax_nonce' => VI_WOO_ALIDROPSHIP_Admin_Settings::create_ajax_nonce(),
					'check'              => esc_attr__( 'Check', 'woo-alidropship' ),
					'override'           => esc_attr__( 'Override', 'woo-alidropship' ),
				)
			);
			add_action( 'admin_footer', array( $this, 'delete_product_options' ) );
		}
	}

	public function delete_product_options() {
		?>
        <div class="<?php echo esc_attr( self::set( array(
			'delete-product-options-container',
			'hidden'
		) ) ) ?>">
            <div class="<?php echo esc_attr( self::set( 'overlay' ) ) ?>"></div>
            <div class="<?php echo esc_attr( self::set( 'delete-product-options-content' ) ) ?>">
                <div class="<?php echo esc_attr( self::set( 'delete-product-options-content-header' ) ) ?>">
                    <h2 class="<?php echo esc_attr( self::set( array( 'delete-product-options-content-header-delete', 'hidden' ) ) ) ?>">
						<?php esc_html_e( 'Delete: ', 'woo-alidropship' ) ?>
                        <span class="<?php echo esc_attr( self::set( 'delete-product-options-product-title' ) ) ?>"> </span>
                    </h2>
                    <span class="<?php echo esc_attr( self::set( 'delete-product-options-close' ) ) ?>"> </span>
                    <h2 class="<?php echo esc_attr( self::set( array( 'delete-product-options-content-header-override', 'hidden' ) ) ) ?>">
						<?php esc_html_e( 'Override: ', 'woo-alidropship' ) ?>
                        <span class="<?php echo esc_attr( self::set( 'delete-product-options-product-title' ) ) ?>"> </span>
                    </h2>
                </div>
                <div class="<?php echo esc_attr( self::set( 'delete-product-options-content-body' ) ) ?>">
                    <div class="<?php echo esc_attr( self::set( 'delete-product-options-content-body-row' ) ) ?>">
                        <div class="<?php echo esc_attr( self::set( array( 'delete-product-options-delete-woo-product-wrap', 'hidden' ) ) ) ?>">
                            <input type="checkbox" <?php checked( self::$settings->get_params( 'delete_woo_product' ), 1 ) ?>
                                   value="1"
                                   id="<?php echo esc_attr( self::set( 'delete-product-options-delete-woo-product' ) ) ?>"
                                   class="<?php echo esc_attr( self::set( 'delete-product-options-delete-woo-product' ) ) ?>">
                            <label for="<?php echo esc_attr( self::set( 'delete-product-options-delete-woo-product' ) ) ?>"><?php esc_html_e( 'Also delete product from your WooCommerce store.', 'woo-alidropship' ) ?></label>
                        </div>
                        <div class="<?php echo esc_attr( self::set( array( 'delete-product-options-override-product-wrap', 'hidden' ) ) ) ?>">
                            <label for="<?php echo esc_attr( self::set( 'delete-product-options-override-product' ) ) ?>"><?php esc_html_e( 'AliExpress Product ID:', 'woo-alidropship' ) ?></label>
                            <input type="text"
                                   id="<?php echo esc_attr( self::set( 'delete-product-options-override-product' ) ) ?>"
                                   class="<?php echo esc_attr( self::set( 'delete-product-options-override-product' ) ) ?>">
                            <div class="<?php echo esc_attr( self::set( array( 'delete-product-options-override-product-new-wrap', 'hidden' ) ) ) ?>">
                                <span class="<?php echo esc_attr( self::set( 'delete-product-options-override-product-new-close' ) ) ?>"> </span>
                                <div class="<?php echo esc_attr( self::set( 'delete-product-options-override-product-new-image' ) ) ?>">
                                    <img src="<?php echo esc_url( VI_WOO_ALIDROPSHIP_IMAGES . 'loading.gif' ) ?>">
                                </div>
                                <div class="<?php echo esc_attr( self::set( 'delete-product-options-override-product-new-title' ) ) ?>"></div>
                            </div>
                        </div>
                        <div class="<?php echo esc_attr( self::set( 'delete-product-options-override-product-message' ) ) ?>"></div>
                    </div>
                </div>
                <div class="<?php echo esc_attr( self::set( 'delete-product-options-content-footer' ) ) ?>">
                    <span class="vi-ui button positive mini <?php echo esc_attr( self::set( array( 'delete-product-options-button-override', 'hidden' ) ) ) ?>"
                          data-product_id="" data-woo_product_id="">
                            <?php esc_html_e( 'Check', 'woo-alidropship' ) ?>
                        </span>
                    <span class="vi-ui button mini negative <?php echo esc_attr( self::set( array( 'delete-product-options-button-delete', 'hidden' ) ) ) ?>"
                          data-product_id="" data-woo_product_id="">
                            <?php esc_html_e( 'Delete', 'woo-alidropship' ) ?>
                        </span>
                    <span class="vi-ui button mini <?php echo esc_attr( self::set( 'delete-product-options-button-cancel' ) ) ?>">
                            <?php esc_html_e( 'Cancel', 'woo-alidropship' ) ?>
                        </span>
                </div>
            </div>
            <div class="<?php echo esc_attr( self::set( 'saving-overlay' ) ) ?>"></div>
        </div>
		<?php
	}

	/**
	 * Adds the order processing count to the menu.
	 */
	public function menu_product_count() {
		global $submenu;
		if ( isset( $submenu['woo-alidropship-import-list'] ) ) {
			// Add count if user has access.
			if ( apply_filters( 'woo_aliexpress_dropship_product_count_in_menu', true ) && current_user_can( 'manage_options' ) ) {
				$product_count = $this->get_product_count();
				foreach ( $submenu['woo-alidropship-import-list'] as $key => $menu_item ) {
					if ( 0 === strpos( $menu_item[0], _x( 'Imported', 'Admin menu name', 'woo-alidropship' ) ) ) {
						$submenu['woo-alidropship-import-list'][ $key ][0] .= ' <span class="update-plugins count-' . esc_attr( $product_count->publish ) . '"><span class="' . self::set( 'imported-list-count' ) . '">' . number_format_i18n( $product_count->publish ) . '</span></span>'; // WPCS: override ok.
						break;
					}
				}
			}
		}
	}

	public function get_product_count() {
		if ( $this->product_count === null ) {
			$this->product_count = Ali_Product_Table::wp_count_posts( 'vi_wad_draft_product' );
		}

		return $this->product_count;
	}

	public function restore() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		vi_wad_set_time_limit();
		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
		$response   = array(
			'status'  => 'success',
			'message' => '',
		);
		if ( $product_id ) {
			$post = Ali_Product_Table::get_post( $product_id );
			Ali_Product_Table::wp_publish_post( $post );
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( $response );
	}

	/**
	 * Delete imported products
	 */
	public function trash() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		vi_wad_set_time_limit();
		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
		$response   = array(
			'status'  => 'success',
			'message' => '',
		);
		if ( $product_id ) {
			$reslut = wp_trash_post( $product_id );
			if ( ! $reslut ) {
				$response['status']  = 'error';
				$response['message'] = esc_html__( 'Can not delete product', 'woo-alidropship' );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json( $response );
	}

	public function override_product() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		self::check_ajax_referer();
		// phpcs:disable WordPress.Security.NonceVerification
		vi_wad_set_time_limit();
		$override_product_url = isset( $_POST['override_product_url'] ) ? sanitize_text_field( stripslashes( $_POST['override_product_url'] ) ) : '';
		$step                 = isset( $_POST['step'] ) ? sanitize_text_field( stripslashes( $_POST['step'] ) ) : '';
		$product_id           = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
		$response             = array(
			'status'           => 'error',
			'message'          => '',
			'image'            => '',
			'title'            => '',
			'data'             => '',
			'exist_product_id' => '',
		);
		$product_sku = $override_product_url;
        if (!$product_sku){
	        $response['message'] = esc_html__( 'Please enter AliExpress Product ID( the product must be imported to the import list before checking)', 'woo-alidropship' );
	        wp_send_json( $response );
        }
		if ( $product_sku == Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_sku', true ) ) {
			$response['message'] = esc_html__( 'Can not override itself', 'woo-alidropship' );
			wp_send_json( $response );
		}
		$exist_product_id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_aliexpress_id( $product_sku );
        if (!$exist_product_id){
	        $response['message'] = esc_html__( 'Not found. Please make sure the product is added to the import list before checking', 'woo-alidropship' );
	        wp_send_json( $response );
        }
		$exist_product                = Ali_Product_Table::get_post( $exist_product_id );
		if (!$exist_product){
			$response['message'] = esc_html__( 'Not found product data. Please make sure the product is added to the import list before checking', 'woo-alidropship' );
			wp_send_json( $response );
		}
        if ($exist_product->post_status !== 'draft' ){
	        if ( $exist_product->post_status === 'publish' ) {
		        $response['status']  = 'exist';
		        $response['message'] = esc_html__( 'This product has already been imported', 'woo-alidropship' );
	        } else {
		        $response['status']  = 'override';
		        $response['message'] = esc_html__( 'This product is overriding an other product.', 'woo-alidropship' );
	        }
            if ($step !== 'check'){
                $response['status'] ='error';
            }
	        wp_send_json( $response );
        }
		if ( $step === 'check' ) {
			$response['exist_product_id'] = $exist_product_id;
			$response['title']            = $exist_product->post_title;
			$gallery                      = Ali_Product_Table::get_post_meta( $exist_product_id, '_vi_wad_gallery', true );
			$response['image']            = ( is_array( $gallery ) && count( $gallery ) ) ? $gallery[0] : wc_placeholder_img_src();
			$response['status'] = 'success';
		} else {
			$post = Ali_Product_Table::get_post( $product_id );
			if ( $post ) {
				$update_post = Ali_Product_Table::wp_update_post( array(
						'ID'          => $exist_product_id,
						'post_status' => 'override',
						'post_parent' => $product_id,
						'edit_date'   => true,
					)
				);
				if ( ! is_wp_error( $update_post ) ) {
					$title                            = $exist_product->post_title;
					$response['status']               = 'success';
					$response['button_override_html'] = self::button_override_html( $product_id, $exist_product_id );
					$response['data']                 = '<div class="vi-ui message"><span>' . sprintf( __( 'This product is being overridden by: %1$s. Please go to %2$s to complete the process.', 'woo-alidropship' ), '<strong>' . $title . '</strong>', '<a target="_blank" href="' . admin_url( 'admin.php?page=woo-alidropship-import-list&vi_wad_search_id=' . $exist_product_id ) . '">Import list</a>' ) . '</span></div>';//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				} else {
					$response['message'] = $update_post->get_error_message();
				}
			} else {
				$response['message'] = esc_html__( 'Not find this product data to override', 'woo-alidropship' );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification

		wp_send_json( $response );
	}

	private static function set( $name, $set_name = false ) {
		return VI_WOO_ALIDROPSHIP_DATA::set( $name, $set_name );
	}

	/**
	 * @param $status
	 * @param $option
	 * @param $value
	 *
	 * @return mixed
	 */
	public function save_screen_options( $status, $option, $value ) {
		if ( $option === 'vi_wad_imported_per_page' ) {
			return $value;
		}

		return $status;
	}

	public function admin_menu() {
		$imported_list = add_submenu_page(
			'woo-alidropship-import-list',
			esc_html__( 'Imported Products - ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce', 'woo-alidropship' ),
			esc_html__( 'Imported', 'woo-alidropship' ),
			'manage_options',
			'woo-alidropship-imported-list',
			array( $this, 'imported_list_callback' )
		);
		add_action( "load-$imported_list", array( $this, 'screen_options_page_imported' ) );
	}

	public function screen_options_page_imported() {
		add_screen_option( 'per_page', array(
			'label'   => esc_html__( 'Number of items per page', 'wp-admin' ),
			'default' => 5,
			'option'  => 'vi_wad_imported_per_page'
		) );
	}

	public function imported_list_callback() {
		$user     = get_current_user_id();
		$screen   = get_current_screen();
		$option   = $screen->get_option( 'per_page', 'option' );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty ( $per_page ) || $per_page < 1 ) {
			$per_page = $screen->get_option( 'per_page', 'default' );
		}

		$paged  = isset( $_GET['paged'] ) ? sanitize_text_field( $_GET['paged'] ) : 1;// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = ! empty( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : 'publish';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'All imported products', 'woo-alidropship' ) ?></h2>
			<?php
			$args             = array(
				'post_type'      => 'vi_wad_draft_product',
				'post_status'    => $status,
				'order'          => 'DESC',
				'orderby'        => 'meta_value_num',
				'fields'         => 'ids',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'and',
					array(
						'key'     => '_vi_wad_woo_id',
						'compare' => 'exists',
					)
				),
			);
			$keyword          = isset( $_GET['vi_wad_search'] ) ? sanitize_text_field( $_GET['vi_wad_search'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$vi_wad_search_id = isset( $_GET['vi_wad_search_woo_id'] ) ? sanitize_text_field( $_GET['vi_wad_search_woo_id'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $vi_wad_search_id ) {
				$args['meta_value']     = $vi_wad_search_id;// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				$args['posts_per_page'] = 1;
				$keyword                = '';
			} else if ( $keyword ) {
				$args['s'] = $keyword;
			}
			//			$the_query     = new WP_Query( $args );
			$the_query     = VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? new Ali_Product_Query( $args ) : new WP_Query( $args );
			$count         = $the_query->found_posts;
			$total_page    = $the_query->max_num_pages;
			$paged         = $total_page >= intval( $paged ) ? $paged : 1;
			$product_count = $this->get_product_count();
			if ( $the_query->have_posts() ) {
				ob_start();
				?>
                <form method="get" class="<?php echo esc_attr( self::set( 'imported-products-' . $status ) ) ?>">
                    <input type="hidden" name="page" value="woo-alidropship-imported-list">
                    <input type="hidden" name="post_status" value="<?php echo esc_attr( $status ) ?>">
                    <div class="tablenav top">
                        <div class="subsubsub">
                            <ul>
                                <li class="<?php echo esc_attr( self::set( 'imported-products-count-publish-container' ) ) ?>">
                                    <a href="<?php echo esc_attr( admin_url( 'admin.php?page=woo-alidropship-imported-list' ) ) ?>">
										<?php esc_html_e( 'Publish', 'woo-alidropship' ); ?></a>
                                    (<span class="<?php echo esc_attr( self::set( 'imported-products-count-publish' ) ) ?>"><?php echo esc_html( $product_count->publish ) ?></span>)
                                </li>
                                |
                                <li class="<?php echo esc_attr( self::set( 'imported-products-count-trash-container' ) ) ?>">
                                    <a href="<?php echo esc_attr( admin_url( 'admin.php?page=woo-alidropship-imported-list&post_status=trash' ) ) ?>">
										<?php esc_html_e( 'Trash', 'woo-alidropship' ); ?></a>
                                    (<span class="<?php echo esc_attr( self::set( 'imported-products-count-trash' ) ) ?>"><?php echo esc_html( $product_count->trash ) ?></span>)
                                </li>
                            </ul>
                        </div>
                        <div class="tablenav-pages">
                            <div class="pagination-links">
								<?php
								if ( $paged > 2 ) {
									?>
                                    <a class="prev-page button" href="<?php echo esc_url( add_query_arg(
										array(
											'page'          => 'woo-alidropship-imported-list',
											'paged'         => 1,
											'vi_wad_search' => $keyword,
											'post_status'   => $status,
										), admin_url( 'admin.php' )
									) ) ?>"><span
                                                class="screen-reader-text"><?php esc_html_e( 'First Page', 'woo-alidropship' ) ?></span><span
                                                aria-hidden="true">«</span></a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
									<?php
								}
								/*Previous button*/
								if ( $per_page * $paged > $per_page ) {
									$p_paged = $paged - 1;
								} else {
									$p_paged = 0;
								}
								if ( $p_paged ) {
									$p_url = add_query_arg(
										array(
											'page'          => 'woo-alidropship-imported-list',
											'paged'         => $p_paged,
											'vi_wad_search' => $keyword,
											'post_status'   => $status,
										), admin_url( 'admin.php' )
									);
									?>
                                    <a class="prev-page button" href="<?php echo esc_url( $p_url ) ?>"><span
                                                class="screen-reader-text"><?php esc_html_e( 'Previous Page', 'woo-alidropship' ) ?></span><span
                                                aria-hidden="true">‹</span></a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
									<?php
								}
								?>
                                <span class="screen-reader-text"><?php esc_html_e( 'Current Page', 'woo-alidropship' ) ?></span>
                                <span id="table-paging" class="paging-input">
                                    <span class="tablenav-paging-text">
                                        <input class="current-page" type="text" name="paged" size="1"
                                               value="<?php echo esc_html( $paged ) ?>"><span
                                                class="tablenav-paging-text"><?php esc_html_e( ' of ', 'woo-alidropship' ) ?>
                                            <span
                                                    class="total-pages"><?php echo esc_html( $total_page ) ?></span>
                                        </span>
                                    </span>
                                </span>
								<?php /*Next button*/
								if ( $per_page * $paged < $count ) {
									$n_paged = $paged + 1;
								} else {
									$n_paged = 0;
								}
								if ( $n_paged ) {
									$n_url = add_query_arg(
										array(
											'page'          => 'woo-alidropship-imported-list',
											'paged'         => $n_paged,
											'vi_wad_search' => $keyword,
											'post_status'   => $status,
										), admin_url( 'admin.php' )
									); ?>
                                    <a class="next-page button" href="<?php echo esc_url( $n_url ) ?>"><span
                                                class="screen-reader-text"><?php esc_html_e( 'Next Page', 'woo-alidropship' ) ?></span><span
                                                aria-hidden="true">›</span></a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
									<?php
								}
								if ( $total_page > $paged + 1 ) {
									?>
                                    <a class="next-page button" href="<?php echo esc_url( add_query_arg(
										array(
											'page'          => 'woo-alidropship-imported-list',
											'paged'         => $total_page,
											'vi_wad_search' => $keyword,
											'post_status'   => $status,
										), admin_url( 'admin.php' )
									) ) ?>"><span
                                                class="screen-reader-text"><?php esc_html_e( 'Last Page', 'woo-alidropship' ) ?></span><span
                                                aria-hidden="true">»</span></a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
									<?php
								}
								?>
                            </div>
                        </div>
                        <p class="search-box">
                            <input type="search" class="text short" name="vi_wad_search"
                                   placeholder="<?php esc_attr_e( 'Search imported product', 'woo-alidropship' ) ?>"
                                   value="<?php echo esc_attr( $keyword ) ?>">
                            <input type="submit" name="submit" class="button"
                                   value="<?php echo esc_attr( 'Search product', 'woo-alidropship' ) ?>">
                        </p>
                    </div>
                </form>
				<?php
				$pagination_html = ob_get_clean();
				echo wp_kses( $pagination_html, VI_WOO_ALIDROPSHIP_DATA::allow_html() );
				$key = 0;
				foreach ( $the_query->posts as $product_id ) {
					$product            = Ali_Product_Table::get_post( $product_id );
					$woo_product_id     = Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_woo_id', true );
					$title              = $product->post_title;
					$woo_product        = wc_get_product( $woo_product_id );
					$woo_product_status = '';
					$woo_product_name   = $title;
					$sku                = Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_sku', true );
					$woo_sku            = $sku;
					if ( $woo_product ) {
						$woo_sku            = $woo_product->get_sku();
						$woo_product_status = $woo_product->get_status();
						$woo_product_name   = $woo_product->get_name();
					}
					$gallery    = Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_gallery', true );
					$store_info = Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_store_info', true );
					$image      = wp_get_attachment_thumb_url( Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_product_image', true ) );
					if ( ! $image ) {
						$image = ( is_array( $gallery ) && count( $gallery ) ) ? array_shift( $gallery ) : '';
					}
					$variations         = Ali_Product_Table::get_post_meta( $product_id, '_vi_wad_variations', true );
					$overriding_product = VI_WOO_ALIDROPSHIP_DATA::get_overriding_product( $product_id );
					$accordion_active   = '';
					if ( $overriding_product ) {
						$accordion_active = 'active';
					}
					?>
                    <div class="vi-ui styled fluid accordion  <?php echo esc_attr( self::set( 'accordion' ) ); ?>"
                         id="<?php echo esc_attr( self::set( 'product-item-id-' . $product_id ) ) ?>">
                        <div class="title <?php echo esc_attr( $accordion_active ) ?>">
                            <i class="dropdown icon <?php echo esc_attr( self::set( 'accordion-title-icon' ) ); ?>"> </i>
                            <div class="<?php echo esc_attr( self::set( 'accordion-product-image-title-container' ) ) ?>">
                                <div class="<?php echo esc_attr( self::set( 'accordion-product-image-title' ) ) ?>">
                                    <img src="<?php echo esc_url( $image ? $image : wc_placeholder_img_src() ) ?>"
                                         class="<?php echo esc_attr( self::set( 'accordion-product-image' ) ) ?>">
                                    <div class="<?php echo esc_attr( self::set( 'accordion-product-title-container' ) ) ?>">
                                        <div class="<?php echo esc_attr( self::set( 'accordion-product-title' ) ) ?>"
                                             title="<?php echo esc_attr( $title ) ?>"><?php echo esc_html( $title ) ?></div>
										<?php
										if ( ! empty( $store_info['name'] ) ) {
											$store_name = $store_info['name'];
											if ( ! empty( $store_info['url'] ) ) {
												$store_name = '<a class="' . esc_attr( self::set( 'accordion-store-url' ) ) . '" href="' . esc_url( $store_info['url'] ) . '" target="_blank">' . $store_name . '</a>';
											}
											?>
                                            <div>
												<?php
												esc_html_e( 'Store: ', 'woo-alidropship' );
												echo wp_kses( $store_name, VI_WOO_ALIDROPSHIP_DATA::allow_html() );
												?>
                                            </div>
											<?php
										}
										?>
                                    </div>
                                </div>
                                <div class="<?php echo esc_attr( self::set( 'button-view-and-edit' ) ) ?>">
                                    <a href="<?php echo esc_url( "https://www.aliexpress.com/item/{$sku}.html" ); ?>"
                                       target="_blank" class="button"
                                       rel="nofollow"><?php esc_html_e( 'View on AliExpress', 'woo-alidropship' ) ?></a>
									<?php
									if ( $woo_product ) {
										if ( $woo_product_status !== 'trash' ) {
											echo VI_WOO_ALIDROPSHIP_Admin_Import_List::get_button_view_edit_html( $woo_product_id );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										} else {
											if ( $status !== 'trash' ) {
												?>
                                                <span class="vi-ui black button <?php echo esc_attr( self::set( 'button-trash' ) ) ?>"
                                                      title="<?php esc_attr_e( 'This product is trashed from your WooCommerce store.', 'woo-alidropship' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id=""><?php esc_html_e( 'Trash', 'woo-alidropship' ) ?>
                                                </span>
                                                <span class="vi-ui button negative <?php echo esc_attr( self::set( 'button-delete' ) ) ?>"
                                                      title="<?php esc_attr_e( 'Delete this product permanently', 'woo-alidropship' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>"><?php esc_html_e( 'Delete', 'woo-alidropship' ) ?>
                                                </span>
												<?php
											} else {
												?>
                                                <span class="vi-ui button positive <?php echo esc_attr( self::set( 'button-restore' ) ) ?>"
                                                      title="<?php esc_attr_e( 'Restore this product', 'woo-alidropship' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>"><?php esc_html_e( 'Restore', 'woo-alidropship' ) ?></span>
                                                <span class="vi-ui button negative <?php echo esc_attr( self::set( 'button-delete' ) ) ?>"
                                                      title="<?php esc_attr_e( 'Delete this product permanently', 'woo-alidropship' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>"><?php esc_html_e( 'Delete', 'woo-alidropship' ) ?></span>
												<?php
											}
										}
									} else {
										if ( $status !== 'trash' ) {
											?>
                                            <span class="vi-ui black button <?php echo esc_attr( self::set( 'button-trash' ) ) ?>"
                                                  title="<?php esc_attr_e( 'This product is deleted from your WooCommerce store.', 'woo-alidropship' ) ?>"
                                                  data-product_title="<?php echo esc_attr( $title ) ?>"
                                                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                  data-woo_product_id=""><?php esc_html_e( 'Trash', 'woo-alidropship' ) ?>
                                            </span>
                                            <span class="vi-ui button negative <?php echo esc_attr( self::set( 'button-delete' ) ) ?>"
                                                  title="<?php esc_attr_e( 'Delete this product permanently', 'woo-alidropship' ) ?>"
                                                  data-product_title="<?php echo esc_attr( $title ) ?>"
                                                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                  data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>"><?php esc_html_e( 'Delete', 'woo-alidropship' ) ?>
                                            </span>
											<?php
										} else {
											?>
                                            <span class="vi-ui button negative <?php echo esc_attr( self::set( 'button-delete' ) ) ?>"
                                                  title="<?php esc_attr_e( 'Delete this product permanently', 'woo-alidropship' ) ?>"
                                                  data-product_title="<?php echo esc_attr( $title ) ?>"
                                                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                  data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>"><?php esc_html_e( 'Delete', 'woo-alidropship' ) ?>
                                            </span>
											<?php
										}
									}
									?>
                                    <span class="vi-ui button negative mini loading <?php echo esc_attr( self::set( 'button-deleting' ) ) ?>"><?php esc_html_e( 'Delete', 'woo-alidropship' ) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="content <?php echo esc_attr( $accordion_active ) ?>">
							<?php
							if ( $overriding_product ) {
								$overriding_product_title = get_the_title( $overriding_product );
								?>
                                <div class="vi-ui message">
                                    <span><?php echo wp_kses_post(sprintf( __( 'This product is being overridden by: %1$s. Please go to %2$s to complete the process.', 'woo-alidropship' ), '<strong>' . $overriding_product_title . '</strong>', '<a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=woo-alidropship-import-list&vi_wad_search=' . urlencode( $overriding_product_title ) ) ) . '">Import list</a>' )) //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></span>
                                </div>
								<?php
							}
							?>
                            <div class="<?php echo esc_attr( self::set( 'message' ) ) ?>"></div>
                            <form class="vi-ui form <?php echo esc_attr( self::set( 'product-container' ) ) ?>"
                                  method="post">
                                <div class="field">
                                    <div class="fields">
                                        <div class="three wide field">
                                            <div class="<?php echo esc_attr( self::set( 'product-image' ) ) ?>">
                                                <img style="width: 100%"
                                                     src="<?php echo esc_url( $image ? $image : wc_placeholder_img_src() ) ?>"
                                                     class="<?php echo esc_attr( self::set( 'import-data-image' ) ) ?>">
                                                <input type="hidden"
                                                       name="<?php echo esc_attr( 'vi_wad_product[' . $product_id . '][image]' ) ?>"
                                                       value="<?php echo esc_attr( $image ? $image : wc_placeholder_img_src() ) ?>">
                                            </div>
                                        </div>
                                        <div class="thirteen wide field">
                                            <div class="field">
                                                <label><?php esc_html_e( 'WooCommerce product title' ) ?></label>
                                                <input type="text" value="<?php echo esc_attr( $woo_product_name ) ?>"
                                                       readonly
                                                       name="<?php echo esc_attr( 'vi_wad_product[' . $product_id . '][title]' ) ?>"
                                                       class="<?php echo esc_attr( self::set( 'import-data-title' ) ) ?>">
                                            </div>
                                            <div class="field">
                                                <div class="equal width fields">
                                                    <div class="field">
                                                        <label><?php esc_html_e( 'Sku', 'woo-alidropship' ) ?></label>
                                                        <input type="text" value="<?php echo esc_attr( $woo_sku ) ?>"
                                                               readonly
                                                               name="<?php echo esc_attr( 'vi_wad_product[' . $product_id . '][sku]' ) ?>"
                                                               class="<?php echo esc_attr( self::set( 'import-data-sku' ) ) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label><?php esc_html_e( 'Cost', 'woo-alidropship' ) ?></label>
                                                        <div class="<?php echo esc_attr( self::set( 'price-field' ) ) ?>">
															<?php
															if ( count( $variations ) == 1 ) {
																$variation_sale_price    = VI_WOO_ALIDROPSHIP_DATA::string_to_float( $variations[0]['sale_price'] );
																$variation_regular_price = VI_WOO_ALIDROPSHIP_DATA::string_to_float( $variations[0]['regular_price'] );
																$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
																echo wc_price( $price, array( 'currency'     => 'USD', 'price_format' => '%1$s&nbsp;%2$s' ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
															} else {
																$min_price = 0;
																$max_price = 0;
																foreach ( $variations as $variation_k => $variation_v ) {
																	$variation_sale_price    = VI_WOO_ALIDROPSHIP_DATA::string_to_float( $variation_v['sale_price'] );
																	$variation_regular_price = VI_WOO_ALIDROPSHIP_DATA::string_to_float( $variation_v['regular_price'] );
																	$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
																	if ( ! $min_price ) {
																		$min_price = $price;
																	}
																	if ( $price < $min_price ) {
																		$min_price = $price;
																	}
																	if ( $price > $max_price ) {
																		$max_price = $price;
																	}
																}
																if ( $min_price && $min_price != $max_price ) {
																	echo wc_price( $min_price, array(// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																			'currency'     => 'USD',
																			'price_format' => '%1$s&nbsp;%2$s'
																		) ) . ' - ' . wc_price( $max_price, array(// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																			'currency'     => 'USD',
																			'price_format' => '%1$s&nbsp;%2$s'
																		) );
																} elseif ( $max_price ) {
																	echo wc_price( $max_price, array(// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																		'currency'     => 'USD',
																		'price_format' => '%1$s&nbsp;%2$s'
																	) );
																}
															}
															?>
                                                        </div>
                                                    </div>
													<?php
													if ( $woo_product && $woo_product_status !== 'trash' ) {
														?>
                                                        <div class="field">
                                                            <label><?php esc_html_e( 'WooCommerce Price', 'woo-alidropship' ) ?></label>
                                                            <div class="<?php echo esc_attr( self::set( 'price-field' ) ) ?>">
																<?php
																echo wp_kses( $woo_product->get_price_html(), VI_WOO_ALIDROPSHIP_DATA::allow_html() );
																?>
                                                            </div>
                                                        </div>
														<?php
													}
													?>
                                                </div>
                                            </div>

                                            <div class="field">
                                                <div class="equal width fields">
                                                    <div class="field">
                                                        <div class="<?php echo esc_attr( self::set( 'button-override-container' ) ) ?>">
															<?php
															if ( $status !== 'trash' ) {
																if ( $woo_product && $woo_product_status !== 'trash' ) {
																	?>
                                                                    <span class="vi-ui button negative <?php echo esc_attr( self::set( 'button-delete' ) ) ?>"
                                                                          title="<?php esc_attr_e( 'Delete this product permanently', 'woo-alidropship' ) ?>"
                                                                          data-product_title="<?php echo esc_attr( $title ) ?>"
                                                                          data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                                          data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>"><?php esc_html_e( 'Delete', 'woo-alidropship' ) ?></span>
																	<?php
																	if ( ! $overriding_product ) {
																		?>
                                                                        <span class="vi-ui button positive <?php echo esc_attr( self::set( 'button-override' ) ) ?>"
                                                                              title="<?php esc_attr_e( 'Override this product', 'woo-alidropship' ) ?>"
                                                                              data-product_title="<?php echo esc_attr( $title ) ?>"
                                                                              data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                                              data-woo_product_id="<?php echo esc_attr( $woo_product_id ) ?>"><?php esc_html_e( 'Override', 'woo-alidropship' ) ?></span>
																		<?php
																	} else {
																		echo self::button_override_html( $product_id, $overriding_product );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																	}
																}
															}
															?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
					<?php
					$key ++;
				}
				echo wp_kses( $pagination_html, VI_WOO_ALIDROPSHIP_DATA::allow_html());
			}
			wp_reset_postdata();
			?>
        </div>
		<?php
	}

	public static function button_override_html( $product_id, $overriding_product ) {
		ob_start();
		?>
        <a title="<?php esc_attr_e( 'Go to import list to complete overriding', 'woo-alidropship' ) ?>"
           class="vi-ui button positive <?php echo esc_attr( self::set( 'button-complete-overriding' ) ) ?>"
           target="_blank"
           href="<?php echo esc_url( admin_url( 'admin.php?page=woo-alidropship-import-list&vi_wad_search_id=' . $overriding_product ) ) ?>"><?php esc_html_e( 'Complete overriding', 'woo-alidropship' ) ?></a>
        <a title="<?php esc_attr_e( 'Cancel overriding this product', 'woo-alidropship' ) ?>"
           class="vi-ui button <?php echo esc_attr( self::set( 'button-complete-overriding' ) ) ?>"
           target="_self"
           href="<?php echo esc_url( add_query_arg( array(
			   'page'               => 'woo-alidropship-imported-list',
			   'overridden_product' => $product_id,
			   'cancel_overriding'  => $overriding_product,
			   '_wpnonce'           => wp_create_nonce( 'cancel_overriding_nonce' )
		   ), admin_url( 'admin.php' ) ) ) ?>"><?php esc_html_e( 'Cancel overriding', 'woo-alidropship' ) ?></a>
		<?php
		return ob_get_clean();
	}

	private static function check_ajax_referer() {
		VI_WOO_ALIDROPSHIP_Admin_Settings::check_ajax_referer( 'woo-alidropship-imported-list' );
	}
}