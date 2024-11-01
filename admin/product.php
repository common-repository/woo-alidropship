<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOO_ALIDROPSHIP_Admin_Product
 */
class VI_WOO_ALIDROPSHIP_Admin_Product {
	private $settings;

	public function __construct() {
		$this->settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'deleted_post' ) );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 20, 2 );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'woocommerce_product_after_variable_attributes' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'woocommerce_save_product_variation' ), 10, 2 );

		/*Need to check the case when removing attribute, variation is removed from _vi_wad_variations*/
		add_action( 'woocommerce_product_options_pricing', array( $this, 'woocommerce_product_options_pricing' ), 99 );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'woocommerce_process_product_meta_simple' ) );
	}

	/**
	 * @param $product_id
	 */
	public function woocommerce_process_product_meta_simple( $product_id ) {
		if ( ! wp_verify_nonce( $_POST['viwad-variation-nonce'] ?? "", 'viwad-variation-settings' ) ) {
			return;
		}
		$skuAttr = isset( $_POST['vi_wad_simple_variation_attr'] ) ? stripslashes( $_POST['vi_wad_simple_variation_attr'] ) : '';
		if ( $skuAttr ) {
			update_post_meta( $product_id, '_vi_wad_aliexpress_variation_attr', $skuAttr );
		}
		$skuID = isset( $_POST['vi_wad_simple_variation_id'] ) ? stripslashes( $_POST['vi_wad_simple_variation_id'] ) : '';
		if ( $skuID ) {
			update_post_meta( $product_id, '_vi_wad_aliexpress_variation_id', $skuID );
		}
	}

	/**
	 *
	 */
	public function woocommerce_product_options_pricing() {
		global $post;
		$product_id = $post->ID;
		if ( get_post_meta( $product_id, '_vi_wad_aliexpress_product_id', true ) ) {
			$from_id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_woo_id( $product_id, false, false, array( 'publish', 'override' ) );
			if ( $from_id ) {
				$variations = Ali_Product_Table::get_post_meta( $from_id, '_vi_wad_variations', true );
				$skuAttr    = get_post_meta( $product_id, '_vi_wad_aliexpress_variation_attr', true );
				if ( $skuAttr || count( $variations ) > 1 ) {
					$id = "vi-wad-original-attributes-simple-{$product_id}";
					wp_nonce_field( 'viwad-variation-settings', 'viwad-variation-nonce', false );
					?>
                    <p class="vi-wad-original-attributes vi-wad-original-attributes-simple form-field"><label
                                for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Original AliExpress variation', 'woo-alidropship' ); ?></label><?php echo wc_help_tip( __( 'If your customers buy this product, this selected AliExpress variation will be used when fulfilling AliExpress orders', 'woo-alidropship' ) ) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>
                        <select id="<?php echo esc_attr( $id ) ?>"
                                class="vi-wad-original-attributes-select"
                                name="vi_wad_simple_variation_attr">
                            <option value=""><?php esc_html_e( 'Please select original variation', 'woo-alidropship' ); ?></option>
							<?php
							foreach ( $variations as $key => $value ) {
								$attr_name = $value['skuAttr'];
								if ( isset( $value['attributes_sub'] ) && count( $value['attributes_sub'] ) > count( $value['attributes'] ) ) {
									$attr_name = implode( ', ', $value['attributes_sub'] );
								} elseif ( count( $value['attributes'] ) ) {
									$attr_name = implode( ', ', $value['attributes'] );
								}
								?>
                                <option value="<?php echo esc_attr( $value['skuAttr'] ) ?>"
                                        data-vi_wad_sku_id="<?php echo esc_attr( $value['skuId'] ) ?>" <?php selected( $value['skuAttr'], $skuAttr ) ?>><?php echo esc_html( $attr_name ) ?></option>
								<?php
							}
							?>
                        </select>
                        <input type="hidden" class="vi-wad-original-sku-id" name="vi_wad_simple_variation_id"
                               value="<?php echo esc_attr( get_post_meta( $product_id, '_vi_wad_aliexpress_variation_id', true ) ) ?>">
                    </p>
					<?php
				}
			}
		}
	}

	/**
	 * @param $variation_id
	 * @param $i
	 */
	public function woocommerce_save_product_variation( $variation_id, $i ) {
		if ( ! wp_verify_nonce( $_POST[ 'viwad-variation-' . $i . '-nonce' ] ?? "", 'viwad-variation-' . $i . '-settings' ) ) {
			return;
		}
		$skuAttr = isset( $_POST['vi_wad_variation_attr'], $_POST['vi_wad_variation_attr'][ $i ] ) ? stripslashes( $_POST['vi_wad_variation_attr'][ $i ] ) : '';
		if ( $skuAttr ) {
			update_post_meta( $variation_id, '_vi_wad_aliexpress_variation_attr', $skuAttr );
		}
		$skuID = isset( $_POST['vi_wad_variation_id'], $_POST['vi_wad_variation_id'][ $i ] ) ? stripslashes( $_POST['vi_wad_variation_id'][ $i ] ) : '';
		if ( $skuID ) {
			update_post_meta( $variation_id, '_vi_wad_aliexpress_variation_id', $skuID );
		}
	}

	/**
	 * @param $loop
	 * @param $variation_data
	 * @param $variation WP_Post
	 */
	public function woocommerce_product_after_variable_attributes( $loop, $variation_data, $variation ) {
		global $post;
		$product_id = $post->ID;
		if ( $variation && get_post_meta( $product_id, '_vi_wad_aliexpress_product_id', true ) ) {
			$from_id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_woo_id( $product_id, false, false, array(
				'publish',
				'override'
			) );
			if ( $from_id ) {
				$variation_id = $variation->ID;
				$variations   = Ali_Product_Table::get_post_meta( $from_id, '_vi_wad_variations', true );
				$skuAttr      = get_post_meta( $variation_id, '_vi_wad_aliexpress_variation_attr', true );
				$id           = "vi-wad-original-attributes-{$variation_id}";
				wp_nonce_field( 'viwad-variation-' . $loop . '-settings', 'viwad-variation-' . $loop . '-nonce', false );
				?>
                <div class="vi-wad-original-attributes vi-wad-original-attributes-variable"><label
                            for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Original AliExpress variation', 'woo-alidropship' ); ?></label><?php echo wc_help_tip( __( 'If your customers buy this product, this selected AliExpress variation will be used when fulfilling AliExpress orders', 'woo-alidropship' ) ) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>
                    <select id="<?php echo esc_attr( $id ) ?>"
                            class="vi-wad-original-attributes-select"
                            name="vi_wad_variation_attr[<?php echo esc_attr( $loop ) ?>]">
						<?php
						if ( ! $skuAttr ) {
							?>
                            <option value=""><?php esc_html_e( 'Please select original variation', 'woo-alidropship' ); ?></option>
							<?php
						}
						foreach ( $variations as $key => $value ) {
							$attr_name = $value['skuAttr'];
							if ( isset( $value['attributes_sub'] ) && count( $value['attributes_sub'] ) > count( $value['attributes'] ) ) {
								$attr_name = implode( ', ', $value['attributes_sub'] );
							} elseif ( count( $value['attributes'] ) ) {
								$attr_name = implode( ', ', $value['attributes'] );
							}
							?>
                            <option value="<?php echo esc_attr( $value['skuAttr'] ) ?>"
                                    data-vi_wad_sku_id="<?php echo esc_attr( $value['skuId'] ) ?>" <?php selected( $value['skuAttr'], $skuAttr ) ?>><?php echo esc_html( $attr_name ) ?></option>
							<?php
						}
						?>
                    </select>
                    <input class="vi-wad-original-sku-id" type="hidden"
                           name="vi_wad_variation_id[<?php echo esc_attr( $loop ) ?>]"
                           value="<?php echo esc_attr( get_post_meta( $variation_id, '_vi_wad_aliexpress_variation_id', true ) ) ?>">
                </div>
				<?php
			}
		}
	}

	/**
	 * @param $actions
	 * @param $post
	 *
	 * @return mixed
	 */
	public function post_row_actions( $actions, $post ) {
		if ( $post && $post->post_type === 'product' && $post->post_status !== 'trash' ) {
			$ali_sku = get_post_meta( $post->ID, '_vi_wad_aliexpress_product_id', true );
			if ( $ali_sku ) {
				$actions['vi_wad_view_on_aliexpress']    = '<a href="' . VI_WOO_ALIDROPSHIP_DATA::get_aliexpress_product_url( $ali_sku ) . '" title="' . esc_attr__( 'View product on AliExpress', 'woo-alidropship' ) . '" target="_blank">' . esc_html__( 'View on AliExpress', 'woo-alidropship' ) . '</a>';
				$href                                    = admin_url( "admin.php?page=woo-alidropship-imported-list&vi_wad_search_woo_id={$post->ID}" );
				$actions['vi_wad_view_on_imported_page'] = '<a href="' . $href . '" title="' . esc_attr__( 'View product on Imported page', 'woo-alidropship' ) . '" target="_blank">' . esc_html__( 'View on Imported', 'woo-alidropship' ) . '</a>';
			}
		}

		return $actions;
	}


	/**Set a product status
	 *
	 * @param $product_id
	 * @param string $status
	 */
	public static function set_status( $product_id, $status = 'trash' ) {
		$ali_sku = get_post_meta( $product_id, '_vi_wad_aliexpress_product_id', true );
		if ( $ali_sku ) {
			if ( $status === 'publish' ) {
				$id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_woo_id( $product_id, false, false, 'trash' );
			} else {
				$id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_woo_id( $product_id );
			}
			if ( $id ) {
				Ali_Product_Table::wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
			}
		}
	}

	/**Set a product status to trash when a WC product is deleted
	 *
	 * @param $product_id
	 */
	public function deleted_post( $product_id ) {
		self::set_status( $product_id, 'trash' );
	}

	/**Set a product status to trash when a WC product is trashed and set to publish when a trashed product is restored
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( 'product' === $post->post_type ) {
			$product_id = $post->ID;
			if ( 'trash' === $new_status ) {
				self::set_status( $product_id );
			} elseif ( $old_status === 'trash' ) {
				self::set_status( $product_id, 'publish' );
			}
		}
	}

	public function admin_enqueue_scripts( $page ) {
		global $post_type;
		if ( $post_type !== 'product' ) {
			return;
		}
		if ( $page === 'post.php' || $page === 'edit.php' ) {
			wp_enqueue_script( 'woo-alidropship-admin-edit-product', VI_WOO_ALIDROPSHIP_JS . 'admin-product.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION, false );
			wp_enqueue_style( 'woo-alidropship-admin-edit-product', VI_WOO_ALIDROPSHIP_CSS . 'admin-product.css', '', VI_WOO_ALIDROPSHIP_VERSION );
			add_action( 'post_submitbox_start', array( $this, 'post_submitbox_start' ) );
			wp_localize_script( 'woo-alidropship-admin-edit-product', 'vi_wad_admin_product_params', array(
				'import_page_url' => admin_url( 'admin.php?page=woo-alidropship-import-list' ),
			) );
		}
	}


	public function post_submitbox_start( $post ) {
		if ( $post ) {
			$product_id     = $post->ID;
			$ali_product_id = get_post_meta( $product_id, '_vi_wad_aliexpress_product_id', true );
			if ( $ali_product_id ) {
				?>
                <p class="vi-wad-view-original-product-button">
                    <a href="<?php echo esc_url( "https://www.aliexpress.com/item/{$ali_product_id}.html" ); ?>"
                       target="_blank"
                       class="button"><?php esc_html_e( 'View product on AliExpress', 'woo-alidropship' ); ?></a>
                </p>
				<?php
			}
		}
	}
}
