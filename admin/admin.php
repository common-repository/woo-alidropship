<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_ALIDROPSHIP_Admin_Admin {
	protected $settings;
	protected $characters_array;

	function __construct() {
		$this->settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();

		add_filter( 'plugin_action_links_woo-alidropship/woo-alidropship.php', array(
			$this,
			'settings_link'
		) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'vi_wad_print_scripts', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Link to Settings
	 *
	 * @param $links
	 *
	 * @return mixed
	 */
	public function settings_link( $links ) {

		array_unshift( $links, sprintf( "<a href='%s'>%s</a>",
			esc_url( admin_url( 'admin.php?page=woo-alidropship' ) ),
			esc_html__( 'Settings', 'woo-alidropship' ) ) );

		array_unshift( $links, sprintf( "<a href='%s'>%s</a>",
			esc_url( admin_url( 'admin.php?page=woo-alidropship-import-list#aldShowModal' ) ),
			esc_html__( 'Find Ali product', 'woo-alidropship' ) ) );
		$links['deactivate'] = str_replace('deactivate-ald-dropshipping-and-fulfillment-for-aliexpress-and-woocommerce', 'deactivate-woo-alidropship', $links['deactivate']);

		return $links;
	}


	/**
	 * Function init when run plugin+
	 */
	function init() {
		/*Register post type*/
		load_plugin_textdomain( 'woo-alidropship' );
		$this->load_plugin_textdomain();
		if ( class_exists( 'VillaTheme_Support' ) ) {
			new VillaTheme_Support( array(
				'support'    => 'https://wordpress.org/support/plugin/woo-alidropship/',
				'docs'       => 'http://docs.villatheme.com/?item=aliexpress-dropshipping-and-fulfillment-for-woocommerce',
				'review'     => 'https://wordpress.org/support/plugin/woo-alidropship/reviews/?rate=5#rate-response',
				'pro_url'    => 'https://1.envato.market/PeXrM',
				'css'        => VI_WOO_ALIDROPSHIP_CSS,
				'image'      => VI_WOO_ALIDROPSHIP_IMAGES,
				'slug'       => 'woo-alidropship',
				'menu_slug'  => 'woo-alidropship-import-list',
				'version'    => VI_WOO_ALIDROPSHIP_VERSION,
				'survey_url' => 'https://script.google.com/macros/s/AKfycbw9mebKbM9JjEYBeedEWSQ3hdrGgl6O5wwqnyqlovdrADAOLscBbY1W89oEmY7JjToTug/exec',
			) );
		}
	}


	/**
	 * load Language translate
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woo-alidropship' );
		// Admin Locale
		if ( is_admin() ) {
			load_textdomain( 'woo-alidropship', VI_WOO_ALIDROPSHIP_LANGUAGES . "woo-alidropship-$locale.mo" );
		}

		// Global + Frontend Locale
		load_textdomain( 'woo-alidropship', VI_WOO_ALIDROPSHIP_LANGUAGES . "woo-alidropship-$locale.mo" );
		load_plugin_textdomain( 'woo-alidropship', false, VI_WOO_ALIDROPSHIP_LANGUAGES );
	}

	public function dismiss_notice() {
		update_user_meta( get_current_user_id(), 'vi_wad_show_notice', VI_WOO_ALIDROPSHIP_VERSION );
	}
}
