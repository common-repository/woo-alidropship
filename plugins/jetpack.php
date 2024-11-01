<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Jetpack
 * Prevent "Write posts or pages in plain-text Markdown syntax" option from wrapping product description with pre and code tag
 */
if ( ! class_exists( 'VI_WOO_ALIDROPSHIP_Plugins_Jetpack' ) ) {
	class VI_WOO_ALIDROPSHIP_Plugins_Jetpack {
		protected static $settings;

		public function __construct() {
			add_action( 'vi_wad_import_list_before_import', array(
				$this,
				'unload_markdown_for_posts'
			) );
		}

		/**
		 * Unload markdown before pushing products from Import list to WooCommerce
		 */
		public function unload_markdown_for_posts() {
			if ( class_exists( 'WPCom_Markdown' ) && is_callable( 'WPCom_Markdown::get_instance' ) ) {
				$markdown = WPCom_Markdown::get_instance();
				$markdown->unload_markdown_for_posts();
			}
		}
	}
}
