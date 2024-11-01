<?php

use Automattic\WooCommerce\Admin\PluginsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Vi_Wad_Setup_Wizard' ) ) {
	class Vi_Wad_Setup_Wizard {
		protected static $settings;
		protected $data;
		protected $current_url;
		protected $plugins;

		public function __construct() {
			self::$settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();
			$this->plugins_init();
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
			add_action( 'admin_head', array( $this, 'setup_wizard' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'wp_ajax_vi_wad_setup_install_plugins', array( $this, 'install_plugins' ) );
			add_action( 'wp_ajax_vi_wad_setup_activate_plugins', array( $this, 'activate_plugins' ) );
		}

		public static function admin_menu() {
			add_submenu_page(
				'woo-alidropship-import-list',
				esc_html__( 'Run setup wizard for ALD - AliExpress Dropshipping and Fulfillment for WooCommerce', 'woo-alidropship' ),
				esc_html__( 'Setup Wizard', 'woo-alidropship' ),
				'manage_options',
				add_query_arg( array(
					'vi_wad_setup_wizard' => true,
					'_wpnonce'            => wp_create_nonce( 'vi_wad_setup' ),
				), admin_url() )
			);
		}

		public static function recommended_plugins() {
			return array(
				array(
					'slug' => 'woo-orders-tracking',
					'name' => 'Orders Tracking for WooCommerce',
					'desc' => __( 'Allows you to bulk add tracking code to WooCommerce orders. Then the plugin will send tracking email with tracking URLs to customers. The plugin also helps you to add tracking code and carriers name to your PayPal transactions. This option will save you tons of time and avoid mistake when adding tracking code to PayPal.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/woo-orders-tracking/assets/icon-128x128.jpg'
				),
				array(
					'slug' => 'exmage-wp-image-links',
					'name' => 'EXMAGE – WordPress Image Links',
					'desc' => __( 'Save storage by using external image URLs. This plugin is required if you want to use external URLs(AliExpress cdn image URLs) for product featured image, gallery images and variation image.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/exmage-wp-image-links/assets/icon-128x128.jpg'
				),
				array(
					'slug' => 'product-variations-swatches-for-woocommerce',
					'name' => 'Product Variations Swatches for WooCommerce',
					'desc' => __( 'Product Variations Swatches for WooCommerce is a professional plugin that allows you to show and select attributes for variation products. The plugin displays variation select options of the products under colors, buttons, images, variation images, radio so it helps the customers observe the products they need more visually, save time to find the wanted products than dropdown type for variations of a variable product.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/product-variations-swatches-for-woocommerce/assets/icon-128x128.jpg'
				),
				array(
					'slug' => 'bulky-bulk-edit-products-for-woo',
					'name' => 'Bulky – Bulk Edit Products for WooCommerce',
					'desc' => __( 'The plugin offers sufficient simple and advanced tools to help filter various available attributes of simple and variable products such as  ID, Title, Content, Excerpt, Slugs, SKU, Post date, range of regular price and sale price, Sale date, range of stock quantity, Product type, Categories.... Users can quickly search for wanted products fields and work with the product fields in bulk.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/bulky-bulk-edit-products-for-woo/assets/icon-128x128.png'
				),
				array(
					'slug' => 'woo-cart-all-in-one',
					'name' => 'Cart All In One For WooCommerce',
					'desc' => __( 'All cart features you need in one simple plugin', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/woo-cart-all-in-one/assets/icon-128x128.png'
				),
				array(
					'slug' => 'email-template-customizer-for-woo',
					'name' => 'Email Template Customizer for WooCommerce',
					'desc' => __( 'Customize WooCommerce emails to make them more beautiful and professional after only several mouse clicks.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/email-template-customizer-for-woo/assets/icon-128x128.jpg'
				),
				array(
					'slug' => 'woo-abandoned-cart-recovery',
					'name' => 'Abandoned Cart Recovery for WooCommerce',
					'desc' => __( 'Helps you to recovery unfinished order in your store. When a customer adds a product to cart but does not complete check out. After a scheduled time, the cart will be marked as “abandoned”. The plugin will start to send cart recovery email or facebook message to the customer, remind him/her to complete the order.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/woo-abandoned-cart-recovery/assets/icon-128x128.png'
				),
				array(
					'slug' => 'woo-photo-reviews',
					'name' => 'Photo Reviews for WooCommerce',
					'desc' => __( 'An ultimate review plugin for WooCommerce which helps you send review reminder emails, allows customers to post reviews include product pictures and send thank you emails with WooCommerce coupons to customers.', 'woo-alidropship' ),
					'img'  => 'https://ps.w.org/woo-photo-reviews/assets/icon-128x128.jpg'
				),
			);
		}

		protected function plugins_init() {
			return $this->plugins = self::recommended_plugins();
		}

		public function admin_enqueue_scripts() {
			if ( isset( $_GET['vi_wad_setup_wizard'], $_GET['_wpnonce'] ) && sanitize_text_field( $_GET['vi_wad_setup_wizard'] ) && wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'vi_wad_setup' ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$step = isset( $_GET['step'] ) ? sanitize_text_field( $_GET['step'] ) : 1;
				wp_dequeue_script( 'select-js' );//Causes select2 error, from ThemeHunk MegaMenu Plus plugin
				wp_dequeue_style( 'eopa-admin-css' );
				wp_enqueue_style( 'woo-alidropship-input', VI_WOO_ALIDROPSHIP_CSS . 'input.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-label', VI_WOO_ALIDROPSHIP_CSS . 'label.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-image', VI_WOO_ALIDROPSHIP_CSS . 'image.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
				wp_enqueue_style( 'woo-alidropship-transition', VI_WOO_ALIDROPSHIP_CSS . 'transition.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-form', VI_WOO_ALIDROPSHIP_CSS . 'form.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-icon', VI_WOO_ALIDROPSHIP_CSS . 'icon.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-dropdown', VI_WOO_ALIDROPSHIP_CSS . 'dropdown.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-checkbox', VI_WOO_ALIDROPSHIP_CSS . 'checkbox.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-segment', VI_WOO_ALIDROPSHIP_CSS . 'segment.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
				wp_enqueue_style( 'woo-alidropship-button', VI_WOO_ALIDROPSHIP_CSS . 'button.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-table', VI_WOO_ALIDROPSHIP_CSS . 'table.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_style( 'woo-alidropship-step', VI_WOO_ALIDROPSHIP_CSS . 'step.min.css' ,[], VI_WOO_ALIDROPSHIP_VERSION );
				wp_enqueue_style( 'select2', VI_WOO_ALIDROPSHIP_CSS . 'select2.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
				wp_enqueue_script( 'woo-alidropship-transition', VI_WOO_ALIDROPSHIP_JS . 'transition.min.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION ,false);
				wp_enqueue_script( 'woo-alidropship-dropdown', VI_WOO_ALIDROPSHIP_JS . 'dropdown.min.js', array( 'jquery' ) , VI_WOO_ALIDROPSHIP_VERSION ,false);
				wp_enqueue_script( 'woo-alidropship-checkbox', VI_WOO_ALIDROPSHIP_JS . 'checkbox.js', array( 'jquery' ), VI_WOO_ALIDROPSHIP_VERSION ,false );
				wp_enqueue_script( 'select2-v4', VI_WOO_ALIDROPSHIP_JS . 'select2.js', array( 'jquery' ), '4.0.3', false );
				wp_enqueue_style( 'woo-alidropship-admin-style', VI_WOO_ALIDROPSHIP_CSS . 'admin.css' ,[], VI_WOO_ALIDROPSHIP_VERSION);
				if ( $step == 1 || $step == 2 ) {
					wp_enqueue_script( 'woo-alidropship-admin', VI_WOO_ALIDROPSHIP_JS . 'setup-wizard.js', array( 'jquery' ) , VI_WOO_ALIDROPSHIP_VERSION ,false);
					wp_localize_script( 'woo-alidropship-admin', 'vi_wad_setup_wizard_params', array(
						'url'                => admin_url( 'admin-ajax.php' ),
						'_vi_wad_ajax_nonce' => VI_WOO_ALIDROPSHIP_Admin_Settings::create_ajax_nonce(),
					) );
				}
				wp_register_script('viwad-setup-wizard', false,['jquery'], VI_WOO_ALIDROPSHIP_VERSION, false);
				wp_enqueue_script('viwad-setup-wizard');
				wp_add_inline_script( 'viwad-setup-wizard', $this->print_script() );
			}
		}

		/**
		 * @throws Exception
		 */
		public function setup_wizard() {
			if ( isset( $_POST['submit'] ) && $_POST['submit'] === 'vi_wad_install_recommend_plugins' ) {
				$wc_install = new WC_Install();
				if ( is_array( $this->plugins ) && ! empty( $this->plugins ) ) {
					foreach ( $this->plugins as $plugin ) {
						$slug_name = $this->set_name( $plugin['slug'] );
						if ( ! empty( $_POST[ $slug_name ] ) ) {
							$wc_install::background_installer(
								$plugin['slug'],
								array(
									'name'      => $plugin['name'],
									'repo-slug' => $plugin['slug'],
								)
							);
						}
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=woo-alidropship-import-list#aldShowModal' ) );
				exit;
			}

			if ( isset( $_GET['vi_wad_setup_wizard'], $_GET['_wpnonce'] ) && sanitize_text_field( $_GET['vi_wad_setup_wizard'] ) && wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'vi_wad_setup' ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$step = isset( $_GET['step'] ) ? intval( sanitize_text_field( $_GET['step'] ) ) : 1;
				$func = 'set_up_step_' . $step;

				if ( method_exists( $this, $func ) ) {
                    delete_option('viwad_setup_wizard');
					$this->current_url = remove_query_arg( 'step', esc_url_raw( $_SERVER['REQUEST_URI'] ) );
					$steps_state       = array(
						'extensions'     => '',
						'product'        => '',
						'recommendation' => '',
					);
					if ( $step === 2 ) {
						$steps_state['extensions']     = '';
						$steps_state['product']        = 'active';
						$steps_state['recommendation'] = 'disabled';
					} elseif ( $step === 3 ) {
						$steps_state['extensions']     = '';
						$steps_state['product']        = '';
						$steps_state['recommendation'] = 'active';
					} else {
						$steps_state['extensions']     = 'active';
						$steps_state['product']        = 'disabled';
						$steps_state['recommendation'] = 'disabled';
					}
					?>
                    <div id="vi-wad-setup-wizard">
                        <div class="vi-wad-logo">
                            <img src="<?php echo esc_url( VI_WOO_ALIDROPSHIP_IMAGES . 'icon.gif' ) ?>"
                                 alt="<?php esc_attr_e( 'ALD - Aliexpress Dropshipping and Fulfillment for WooCommerce icon', 'woo-alidropship' ); ?>"
                                 width="80"/>
                        </div>
                        <h1><?php esc_html_e( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce Setup Wizard' ); ?></h1>
                        <div class="vi-wad-wrapper vi-ui segment">
                            <div class="vi-ui steps fluid">
                                <div class="step <?php echo esc_attr( $steps_state['extensions'] ) ?>">
                                    <div class="content">
                                        <div class="title"><?php esc_html_e( '1. Chrome Extension', 'woo-alidropship' ); ?></div>
                                    </div>
                                </div>
                                <div class="step <?php echo esc_attr( $steps_state['product'] ) ?>">
                                    <div class="content">
                                        <div class="title"><?php esc_html_e( '2. Product Settings', 'woo-alidropship' ); ?></div>
                                    </div>
                                </div>
                                <div class="step <?php echo esc_attr( $steps_state['recommendation'] ) ?>">
                                    <div class="content">
                                        <div class="title"><?php esc_html_e( '3. Recommendation', 'woo-alidropship' ); ?></div>
                                    </div>
                                </div>
                            </div>
							<?php
							$this->$func();
							?>
                        </div>

                    </div>
					<?php
				}
				exit;
			}
		}

		public function skip_button() {
			?>
            <a class="vi-wad-skip-btn vi-ui button" href="<?php echo esc_url( admin_url( 'admin.php?page=woo-alidropship-import-list#aldShowModal' ) ) ?>">
				<?php esc_html_e( 'Return to dashboard', 'woo-alidropship' ); ?>
            </a>
			<?php
		}

		public function set_up_step_1() {
			?>
            <div class="vi-wad-step-1">
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th><?php esc_html_e( 'Install Chrome Extension', 'woo-alidropship' ); ?></th>
                        <td>
                            <a href="https://downloads.villatheme.com/?download=alidropship-extension"
                               target="_blank">
								<?php esc_html_e( 'WooCommerce AliExpress Dropshipping Extension', 'woo-alidropship' ); ?>
                            </a>
                            <p>
                                <strong>*</strong><?php esc_html_e( 'To import AliExpress products, this chrome extension is required.', 'woo-alidropship' ) ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Site URL', 'woo-alidropship' ); ?></th>
                        <td class="vi-wad relative">
                            <div class="vi-ui left labeled input fluid">
                                <label class="vi-ui label">
                                    <div class="vi-wad-buttons-group">
                                        <span class="vi-wad-copy-secretkey"
                                              title="<?php esc_attr_e( 'Copy Site URL', 'woo-alidropship' ) ?>">
                                                <i class="dashicons dashicons-admin-page"></i></span>
                                    </div>
                                </label>
                                <input type="text" readonly
                                       value="<?php echo esc_url( site_url() ); ?>"
                                       class="<?php self::set_params( 'secret_key', true ) ?>">
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
                                                <i class="dashicons dashicons-admin-page"></i></span>
                                    </div>
                                </label>
                                <input type="text" readonly
                                       value="<?php echo esc_attr(self::$settings->get_params( 'secret_key' )) ?>"
                                       class="<?php self::set_params( 'secret_key', true ) ?>">
                            </div>
                            <p><?php esc_html_e( 'Secret key is one of the two ways to connect the chrome extension with your store. The other way is to use WooCommerce authentication.', 'woo-alidropship' ) ?></p>
                            <p class="vi-wad-connect-extension-desc vi-wad-hidden"><?php esc_html_e( 'To let the chrome extension connect with this store, please click the "Connect the Extension" button below.', 'woo-alidropship' ) ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Video guide', 'woo-alidropship' ); ?></th>
                        <td>
                            <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/eO_C_b4ZQmo"
                                    title="YouTube video player" frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="vi-wad-btn-group">
				<?php $this->skip_button(); ?>
				<?php VI_WOO_ALIDROPSHIP_DATA::chrome_extension_buttons(); ?>
                <a href="<?php echo esc_url( $this->current_url . '&step=2' ) ?>"
                   class="vi-ui button primary right labeled icon"><i
                            class="icon step forward"></i>
					<?php esc_html_e( 'Next', 'woo-alidropship' ); ?>
                </a>
            </div>
			<?php
		}

		public function set_up_step_2() {
			?>
            <form method="post" action="" class="vi-ui form setup-wizard">
                <div class="vi-wad-step-2">
					<?php wp_nonce_field( 'wooaliexpressdropship_save_settings', '_wooaliexpressdropship_nonce' ) ?>
                    <input type="hidden" name="vi_wad_setup_redirect"
                           value="<?php echo esc_url( $this->current_url . '&step=3' ) ?>">
                    <table class="form-table">
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
                                           tabindex="0"
                                           class="<?php self::set_params( 'show_shipping_option', true ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'show_shipping_option' ) ?>"/>
                                    <label><?php esc_html_e( 'Shipping cost will be added to price of original product before applying price rules. You can select shipping country/company to calculate shipping cost of products before importing.', 'woo-alidropship' ) ?></label>
                                    <p><?php echo wp_kses_post(__( '<strong>*Note:</strong> This is not shipping cost/method that your customers see at your store.', 'woo-alidropship' )) ?></p>
                                </div>
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
                                           tabindex="0"
                                           class="<?php self::set_params( 'shipping_cost_after_price_rules', true ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'shipping_cost_after_price_rules' ) ?>"/>
                                    <label><?php esc_html_e( 'Shipping cost will be added to price of original product after applying price rules.', 'woo-alidropship' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo wp_kses_post(sprintf( esc_html__( 'Exchange rate - USD/%s', 'woo-alidropship' ), get_option( 'woocommerce_currency' )) ) //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></th>
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
                                    <input type="number" <?php checked( self::$settings->get_params( 'import_currency_rate_RUB' ), 1 ) ?>
                                           step="0.001"
                                           min="0.001"
                                           id="<?php self::set_params( 'import_currency_rate_RUB', true ) ?>"
                                           class="<?php self::set_params( 'import_currency_rate_RUB', true ) ?>"
                                           value="<?php echo esc_attr(self::$settings->get_params( 'import_currency_rate_RUB' )) ?>"
                                           name="<?php self::set_params( 'import_currency_rate_RUB' ) ?>"/>
                                    <p><?php esc_html_e( 'If you want to import products from aliexpress.ru, this is required', 'woo-alidropship' ) ?></p>
                                </td>
                            </tr>
							<?php
						}
						?>
                        <tr class="<?php self::set_params( 'price_rule_wrapper', true ) ?>">
                            <th colspan="2">
                                <table class="vi-ui celled table price-rule">
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
                                                <span class="vi-ui button icon negative mini <?php self::set_params( 'price_rule_remove', true ) ?>"><i
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
                                    <tr class="<?php echo esc_attr( VI_WOO_ALIDROPSHIP_DATA::set( array( 'price-rule-row-default' ) ) ) ?>">
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
                                <span class="<?php self::set_params( 'price_rule_add', true ) ?> vi-ui button icon positive mini"><i
                                            class="icon add"></i></span>
                            </th>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'product_description', true ) ?>"><?php esc_html_e( 'Product description', 'woo-alidropship' ) ?></label>
                            </th>
                            <td>
                                <select name="<?php self::set_params( 'product_description' ) ?>"
                                        id="<?php self::set_params( 'product_description', true ) ?>"
                                        class="<?php self::set_params( 'product_description', true ) ?> vi-ui dropdown">
                                    <option value="none" <?php selected( self::$settings->get_params( 'product_description' ), 'none' ) ?>><?php esc_html_e( 'None', 'woo-alidropship' ) ?></option>
                                    <option value="item_specifics" <?php selected( self::$settings->get_params( 'product_description' ), 'item_specifics' ) ?>><?php esc_html_e( 'Item specifics', 'woo-alidropship' ) ?></option>
                                    <option value="description" <?php selected( self::$settings->get_params( 'product_description' ), 'description' ) ?>><?php esc_html_e( 'Product Description', 'woo-alidropship' ) ?></option>
                                    <option value="item_specifics_and_description" <?php selected( self::$settings->get_params( 'product_description' ), 'item_specifics_and_description' ) ?>><?php esc_html_e( 'Item specifics & Product Description', 'woo-alidropship' ) ?></option>
                                </select>
                                <p><?php esc_html_e( 'Default product description when adding product to import list', 'woo-alidropship' ) ?></p>
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
                                <label for="<?php self::set_params( 'use_global_attributes', true ) ?>">
									<?php esc_html_e( 'Use global attributes', 'woo-alidropship' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'use_global_attributes', true ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'use_global_attributes' ), 1 ) ?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'use_global_attributes', true ) ?>" value="1"
                                           name="<?php self::set_params( 'use_global_attributes' ) ?>"/>
                                    <label><?php echo wp_kses_post(__( 'Global attributes will be used instead of custom attributes. More details about <a href="https://woocommerce.com/document/managing-product-taxonomies/#product-attributes" target="_blank">Product attributes</a>', 'woo-alidropship' )) ?></label>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="vi-wad-btn-group">
	                <?php $this->skip_button(); ?>
                    <?php VI_WOO_ALIDROPSHIP_DATA::chrome_extension_buttons(); ?>
                    <a href="<?php echo esc_url( $this->current_url . '&step=1' ) ?>" class="vi-ui button labeled icon"><i
                                class="icon step backward"></i>
						<?php esc_html_e( 'Back', 'woo-alidropship' ); ?>
                    </a>
                    <button type="submit"
                            name="<?php echo esc_attr( VI_WOO_ALIDROPSHIP_DATA::set( 'save-settings', true ) ) ?>"
                            class="vi-ui button primary right labeled icon"
                            value="vi_wad_wizard_submit"><i
                                class="icon step forward"></i><?php esc_html_e( 'Next', 'woo-alidropship' ); ?></button>
                </div>
            </form>
			<?php
		}

		public function set_up_step_3() {
			$plugins = $this->plugins;
			?>
            <form method="post" style="margin-bottom: 0"
                  action="<?php echo esc_url( admin_url( 'admin.php?page=woo-alidropship' ) ) ?>">
                <div class="vi-wad-step-3">
                    <div class="">
                        <table id="status" class="vi-ui table">
                            <thead>
                            <tr>
                                <th></th>
                                <th></th>
                                <th><?php esc_html_e( 'Recommended plugins', 'woo-alidropship' ) ?></th>
                            </tr>
                            </thead>
                            <tbody>
							<?php
							foreach ( $plugins as $plugin ) {
								$plugin_url = "https://wordpress.org/plugins/{$plugin['slug']}";
								?>
                                <tr>
                                    <td>
                                        <input type="checkbox" value="1" checked class="vi-wad-select-plugin"
                                               data-plugin_slug="<?php echo esc_attr( $plugin['slug'] ) ?>"
                                               name="<?php echo esc_attr( $this->set_name( $plugin['slug'] ) ) ?>">
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( $plugin_url ) ?>" target="_blank">
                                            <img src="<?php echo esc_attr( $plugin['img'] ) ?>" width="60" height="60">
                                        </a>
                                    </td>
                                    <td>
                                        <div class="vi-wad-plugin-name">
                                            <a href="<?php echo esc_url( $plugin_url ) ?>" target="_blank">
                                                <span> <?php echo esc_html( $plugin['name'] ) ?></span>
                                            </a>
                                        </div>
                                        <div class="vi-wad-plugin-desc"><?php echo esc_html( $plugin['desc'] ) ?></div>
                                    </td>
                                </tr>
								<?php
							}
							?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="vi-wad-btn-group">
	                <?php $this->skip_button(); ?>
                    <?php VI_WOO_ALIDROPSHIP_DATA::chrome_extension_buttons(); ?>
                    <a href="<?php echo esc_url( $this->current_url . '&step=2' ) ?>" class="vi-ui button labeled icon">
                        <i class="icon step backward"> </i>
						<?php esc_html_e( 'Back', 'woo-alidropship' ); ?>
                    </a>
                    <button type="submit" class="vi-ui button primary labeled icon vi-wad-finish" name="submit"
                            value="vi_wad_install_recommend_plugins">
                        <i class="icon check"></i>
						<?php esc_html_e( 'Install & Return to Import list', 'woo-alidropship' ); ?>
                    </button>
                </div>
            </form>
			<?php
		}

		public function set_name( $slug ) {
			return esc_attr( 'vi_install_' . str_replace( '-', '_', $slug ) );
		}

		public function print_script() {
			ob_start();
			?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    'use strict';
                    $('.vi-wad-select-plugin').on('change', function () {
                        let checkedCount = $('.vi-wad-select-plugin:checked').length;
                        if (checkedCount === 0) {
                            $('.vi-wad-finish').text('<?php esc_html_e( 'Return to Import list', 'woo-alidropship' );?>');
                        } else {
                            $('.vi-wad-finish').text(<?php echo wp_json_encode( __( 'Install & Return to Import list', 'woo-alidropship' ) )?>);
                        }
                    });
                    $('.vi-wad-toggle-select-plugin').on('change', function () {
                        let checked = $(this).prop('checked');
                        $('.vi-wad-select-plugin').prop('checked', checked);
                        if (!checked) {
                            $('.vi-wad-finish').text('<?php esc_html_e( 'Return to Import list', 'woo-alidropship' );?>');
                        } else {
                            $('.vi-wad-finish').text(<?php echo wp_json_encode( __( 'Install & Return to Import list', 'woo-alidropship' ) )?>);
                        }
                    });

                    $('.vi-wad-finish').on('click', function () {
                        let $button = $(this), install_plugins = [];
                        $('.vi-wad-select-plugin').map(function () {
                            let $plugin = $(this);
                            if ($plugin.prop('checked')) {
                                install_plugins.push($plugin.data('plugin_slug'));
                            }
                        });
                        if (install_plugins.length > 0) {
                            $button.addClass('loading');
                            $.ajax({
                                url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) );?>',
                                type: 'POST',
                                dataType: 'JSON',
                                data: {
                                    action: 'vi_wad_setup_install_plugins',
                                    _vi_wad_ajax_nonce: '<?php echo wp_create_nonce( 'woo_alidropship_admin_ajax' ) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
                                    install_plugins: install_plugins,
                                },
                                success: function (response) {

                                },
                                error: function (err) {

                                },
                                complete: function () {
                                    $.ajax({
                                        url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) );?>',
                                        type: 'POST',
                                        dataType: 'JSON',
                                        data: {
                                            action: 'vi_wad_setup_activate_plugins',
                                            _vi_wad_ajax_nonce: '<?php echo wp_create_nonce( 'woo_alidropship_admin_ajax' ) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>',
                                            install_plugins: install_plugins,
                                        },
                                        success: function (response) {

                                        },
                                        error: function (err) {

                                        },
                                        complete: function () {
                                            $button.removeClass('loading');
                                            window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=woo-alidropship-import-list#aldShowModal' ) )?>';
                                        }
                                    })
                                }
                            })
                        } else {
                            window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=woo-alidropship-import-list#aldShowModal' ) )?>';
                        }
                        return false;
                    });
                });
            </script>
			<?php
			return  preg_replace( '#<script[^>]*>(.*)</script>#is', '$1', ob_get_clean() ) ;
		}

		public function install_plugins() {
			check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
			$plugins = isset( $_POST['install_plugins'] ) ? stripslashes_deep( $_POST['install_plugins'] ) : array();
			if ( ! is_array( $plugins ) && ! count( $plugins ) ) {
				wp_send_json_error();
			}

			include_once ABSPATH . '/wp-admin/includes/admin.php';
			include_once ABSPATH . '/wp-admin/includes/plugin-install.php';
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
			include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . '/wp-admin/includes/class-plugin-upgrader.php';

			$existing_plugins  = PluginsHelper::get_installed_plugins_paths();
			$installed_plugins = array();

			foreach ( $plugins as $plugin ) {
				$slug = sanitize_key( $plugin );

				if ( isset( $existing_plugins[ $slug ] ) ) {
					$installed_plugins[] = $plugin;
					continue;
				}

				$api = plugins_api(
					'plugin_information',
					array(
						'slug'   => $slug,
						'fields' => array(
							'sections' => false,
						),
					)
				);

				if ( ! is_wp_error( $api ) ) {
					$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
					$result   = $upgrader->install( $api->download_link );
					if ( ! is_wp_error( $result ) && ! is_null( $result ) ) {
						$installed_plugins[] = $plugin;
					}
				}
			}
			if ( count( $installed_plugins ) ) {
				wp_send_json_success( array( 'installed_plugins' => $installed_plugins ) );
			} else {
				wp_send_json_error();
			}
		}

		public function activate_plugins() {
			check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
			$plugin_paths = PluginsHelper::get_installed_plugins_paths();
			$plugins      = isset( $_POST['install_plugins'] ) ? stripslashes_deep( $_POST['install_plugins'] ) : array();
			if ( ! is_array( $plugins ) && ! count( $plugins ) ) {
				wp_send_json_error();
			}
			$activated_plugins = array();
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			// the mollie-payments-for-woocommerce plugin calls `WP_Filesystem()` during it's activation hook, which crashes without this include.
			require_once ABSPATH . 'wp-admin/includes/file.php';

			foreach ( $plugins as $plugin ) {
				$slug = $plugin;
				$path = isset( $plugin_paths[ $slug ] ) ? $plugin_paths[ $slug ] : false;
				if ( $path ) {
					$result = activate_plugin( $path );
					if ( is_null( $result ) ) {
						$activated_plugins[] = $plugin;
					}
				}
			}
			if ( count( $activated_plugins ) ) {
				wp_send_json_success( array( 'activated_plugins' => $activated_plugins ) );
			} else {
				wp_send_json_error();
			}
		}

		private static function set_params( $name = '', $class = false, $multiple = false ) {
			VI_WOO_ALIDROPSHIP_Admin_Settings::set_params( $name, $class, $multiple );
		}
	}
}

new Vi_Wad_Setup_Wizard();