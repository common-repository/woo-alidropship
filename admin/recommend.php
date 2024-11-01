<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOO_ALIDROPSHIP_Admin_System
 */
class VI_WOO_ALIDROPSHIP_Admin_Recommend {
	protected $dismiss;

	public function __construct() {
		$this->dismiss = 'wad_install_recommended_plugins_dismiss';
		add_action( 'admin_menu', array( $this, 'menu_page' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'fix_dismiss_notice' ) );
	}

	public function fix_dismiss_notice() {
		$dismiss = get_option( $this->dismiss );
		if ( $dismiss ) {
			if ( $dismiss > 1600852690 ) {//2020-09-23 09:18:10 version 1.0.3.3 update time
				update_option( "{$this->dismiss}__email-template-customizer-for-woo", $dismiss );
			}
			update_option( "{$this->dismiss}__product-variations-swatches-for-woocommerce", $dismiss );
			delete_option( $this->dismiss );
		}
	}

	public static function admin_notices_html( $message, $button, $plugin_slug ) {
		?>
        <div class="villatheme-dashboard updated" style="border-left: 4px solid #ffba00">
            <div class="villatheme-content">
                <form action="" method="get">
                    <p><?php echo wp_kses( $message ,VI_WOO_ALIDROPSHIP_DATA::allow_html())  ?></p>
                    <p><?php echo wp_kses_post( $button ) ?></p>
                    <a href="<?php echo esc_url( add_query_arg( array(
						'wad_dismiss_nonce' => wp_create_nonce( 'wad_dismiss_nonce' ),
						'plugin'            => $plugin_slug,
					) ) ) ?>" target="_self"
                       class="button notice-dismiss vi-button-dismiss"><?php esc_html_e( 'Dismiss', 'woo-alidropship' ) ?></a>
                </form>
            </div>
        </div>
		<?php
	}

	public function admin_notices() {
		global $pagenow;
		$action              = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_plugin             = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$recommended_plugins = array(
			array(
				'slug'                => 'exmage-wp-image-links',
				'pro'                 => '',
				'name'                => 'EXMAGE – WordPress Image Links',
				'message_not_install' => __( 'Need to save your server storage? <strong>EXMAGE – WordPress Image Links</strong> will help you solve the problem by using external image URLs. </br>When this plugin is active, "Use external links for images" option will be available in the ALD plugin settings/Product which allows to use original AliExpress product image URLs for featured image, gallery images and variation image of imported AliExpress products.', 'woo-alidropship' ),
				'message_not_active'  => __( '<strong>EXMAGE – WordPress Image Links</strong> is currently inactive, external images added by this plugin(Post/product featured image, product gallery images...) will no longer work properly.', 'woo-alidropship' ),
			),
			array(
				'slug'                => 'bulky-bulk-edit-products-for-woo',
				'pro'                 => '',
				'name'                => 'Bulky – Bulk Edit Products for WooCommerce',
				'message_not_install' => __( 'Quickly and easily edit your products in bulk with <strong>Bulky – Bulk Edit Products for WooCommerce</strong>', 'woo-alidropship' ),
//				'message_not_active'  => __( '<strong>Bulky – Bulk Edit Products for WooCommerce</strong> is currently inactive. Activate it to quickly edit your products in bulk', 'woo-alidropship' ),
			),
			array(
				'slug'                => 'email-template-customizer-for-woo',
				'pro'                 => 'woocommerce-email-template-customizer',
				'name'                => 'Email Template Customizer for WooCommerce',
				'message_not_install' => __( 'Try our brand new <strong>Email Template Customizer for WooCommerce</strong> plugin to easily customize your WooCommerce emails and make them more beautiful and professional.', 'woo-alidropship' ),
//				'message_not_active'  => __( '<strong>Email Template Customizer for WooCommerce</strong> is currently inactive. Activate it to customize WooCommerce emails with ease and make your customers more satisfied when receiving your emails.', 'woo-alidropship' ),
			),
			array(
				'slug'                => 'product-variations-swatches-for-woocommerce',
				'pro'                 => 'woocommerce-product-variations-swatches',
				'name'                => 'Product Variations Swatches for WooCommerce',
				'message_not_install' => __( 'Need a variations swatches plugin that works perfectly with ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce? <strong>Product Variations Swatches for WooCommerce</strong> is what you need.', 'woo-alidropship' ),
				'message_not_active'  => __( '<strong>Product Variations Swatches for WooCommerce</strong> is currently inactive, this prevents variable products from displaying beautifully.', 'woo-alidropship' ),
			),
		);
		$plugins             = get_plugins();
		foreach ( $recommended_plugins as $recommended_plugin ) {
			$plugin_slug = $recommended_plugin['slug'];
			if ( ! get_option( "{$this->dismiss}__{$plugin_slug}" ) ) {
				if ( ! empty( $recommended_plugin['pro'] ) && is_plugin_active( "{$recommended_plugin['pro']}/{$recommended_plugin['pro']}.php" ) ) {
					continue;
				}
				$plugin = "{$plugin_slug}/{$plugin_slug}.php";
				if ( ! isset( $plugins[ $plugin ] ) ) {
					if ( ! ( $pagenow === 'update.php' && $action === 'install-plugin' && $_plugin === $plugin_slug ) ) {
						$button = '<a href="' . esc_url( wp_nonce_url( self_admin_url( "update.php?action=install-plugin&plugin={$plugin_slug}" ), "install-plugin_{$plugin_slug}" ) ) . '" target="_self" class="button button-primary">' . esc_html__( 'Install now', 'woo-alidropship' ) . '</a>';
						self::admin_notices_html( $recommended_plugin['message_not_install'], $button, $plugin_slug );
					}
				} elseif ( ! is_plugin_active( $plugin ) && ! empty( $recommended_plugin['message_not_active'] ) ) {
					$button = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array(
							'action' => 'activate',
							'plugin' => $plugin
						), admin_url( 'plugins.php' ) ), "activate-plugin_{$plugin}" ) ) . '" target="_self" class="button button-primary">' . esc_html__( 'Activate now', 'woo-alidropship' ) . '</a>';
					self::admin_notices_html( $recommended_plugin['message_not_active'], $button, $plugin_slug );
				}
			}
		}
	}

	public function admin_enqueue_scripts() {
		global $pagenow;
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $pagenow === 'admin.php' && $page === 'woo-alidropship-recommend' ) {
			wp_dequeue_style( 'eopa-admin-css' );
			wp_enqueue_style( 'vi-woo-alidropship-form', VI_WOO_ALIDROPSHIP_CSS . 'form.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			wp_enqueue_style( 'vi-woo-alidropship-table', VI_WOO_ALIDROPSHIP_CSS . 'table.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			wp_enqueue_style( 'vi-woo-alidropship-icon', VI_WOO_ALIDROPSHIP_CSS . 'icon.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			wp_enqueue_style( 'vi-woo-alidropship-segment', VI_WOO_ALIDROPSHIP_CSS . 'segment.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			wp_enqueue_style( 'vi-woo-alidropship-button', VI_WOO_ALIDROPSHIP_CSS . 'button.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			if (!wp_style_is('viwad-recommended_plugins')){
				wp_register_style('viwad-recommended_plugins',false,[],VI_WOO_ALIDROPSHIP_VERSION);
				wp_enqueue_style('viwad-recommended_plugins');
				wp_add_inline_style('viwad-recommended_plugins','.fist-col { min-width: 300px;}.vi-wad-plugin-name {font-weight: 600;}.vi-wad-plugin-name a { text-decoration: none;}');
			}
		} else {
			$wad_dismiss_nonce = isset( $_REQUEST['wad_dismiss_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wad_dismiss_nonce'] ) ) : '';
			$dismiss_plugin    = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
			if ( wp_verify_nonce( $wad_dismiss_nonce, 'wad_dismiss_nonce' ) ) {
				$option = $dismiss_plugin ? "{$this->dismiss}__{$dismiss_plugin}" : $this->dismiss;
				if ( ! get_option( $option ) ) {
					update_option( $option, time() );
				}
			}
			if ( ! get_option( $this->dismiss ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}
	}

	public function page_callback() {
		$plugins = Vi_Wad_Setup_Wizard::recommended_plugins();
		?>
        <div class="">
            <h2><?php esc_html_e( 'Recommended plugins', 'woo-alidropship' ) ?></h2>
            <table cellspacing="0" id="status" class="vi-ui celled table">
                <thead>
                <tr>
                    <th></th>
                    <th><?php esc_html_e( 'Plugins', 'woo-alidropship' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'woo-alidropship' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				$installed_plugins = get_plugins();
				foreach ( $plugins as $plugin ) {
					$plugin_id = "{$plugin['slug']}/{$plugin['slug']}.php";
					?>
                    <tr>
                        <td><a target="_blank"
                               href="<?php echo esc_url( "https://wordpress.org/plugins/{$plugin['slug']}" ) ?>"><img
                                        src="<?php echo esc_url( $plugin['img'] ) ?>" width="60" height="60"></a></td>
                        <td class="fist-col">
                            <div class="vi-wad-plugin-name">
                                <a target="_blank"
                                   href="<?php echo esc_url( "https://wordpress.org/plugins/{$plugin['slug']}" ) ?>"><strong><?php echo esc_html( $plugin['name'] ) ?></strong></a>
                            </div>
                            <div>
								<?php
								if ( ! isset( $installed_plugins[ $plugin_id ] ) ) {
									?>
                                    <a href="<?php echo esc_url( wp_nonce_url( self_admin_url( "update.php?action=install-plugin&plugin={$plugin['slug']}" ), "install-plugin_{$plugin['slug']}" ) ) ?>"
                                       target="_blank"><?php esc_html_e( 'Install', 'woo-alidropship' ); ?></a>
									<?php
								} elseif ( ! is_plugin_active( $plugin_id ) ) {
									?>
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
										'action' => 'activate',
										'plugin' => $plugin_id
									), admin_url( 'plugins.php' ) ), "activate-plugin_{$plugin_id}" ) ) ?>"
                                       target="_blank"><?php esc_html_e( 'Activate', 'woo-alidropship' ); ?></a>
									<?php
								} else {
									esc_html_e( 'Currently active', 'woo-alidropship' );
								}
								?>
                            </div>
                        </td>
                        <td><?php echo esc_html( $plugin['desc'] ) ?></td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
        </div>
		<?php
	}

	/**
	 * Register a custom menu page.
	 */
	public function menu_page() {
		add_submenu_page(
			'woo-alidropship-import-list',
			esc_html__( 'Recommended plugins for ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce', 'woo-alidropship' ),
			esc_html__( 'Recommended plugins', 'woo-alidropship' ),
			'manage_options',
			'woo-alidropship-recommend',
			array( $this, 'page_callback' )
		);
	}
}
