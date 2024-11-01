<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'VI_WOO_ALIDROPSHIP_ADMIN', VI_WOO_ALIDROPSHIP_DIR . "admin" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_FRONTEND', VI_WOO_ALIDROPSHIP_DIR . "frontend" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_LANGUAGES', VI_WOO_ALIDROPSHIP_DIR . "languages" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_TEMPLATES', VI_WOO_ALIDROPSHIP_DIR . "templates" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_PLUGINS', VI_WOO_ALIDROPSHIP_DIR . "plugins" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_CLASS', VI_WOO_ALIDROPSHIP_INCLUDES . "class" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_CACHE', VI_WOO_ALIDROPSHIP_DIR . "cache" . DIRECTORY_SEPARATOR );
$plugin_url = plugins_url( '', __FILE__ );
$plugin_url = str_replace( '/includes', '', $plugin_url );
define( 'VI_WOO_ALIDROPSHIP_ASSETS', $plugin_url . "/assets/" );
define( 'VI_WOO_ALIDROPSHIP_ASSETS_DIR', VI_WOO_ALIDROPSHIP_DIR . "assets" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_PACKAGES', VI_WOO_ALIDROPSHIP_ASSETS_DIR . "packages" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_CSS', VI_WOO_ALIDROPSHIP_ASSETS . "css/" );
define( 'VI_WOO_ALIDROPSHIP_CSS_DIR', VI_WOO_ALIDROPSHIP_DIR . "css" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_JS', VI_WOO_ALIDROPSHIP_ASSETS . "js/" );
define( 'VI_WOO_ALIDROPSHIP_JS_DIR', VI_WOO_ALIDROPSHIP_DIR . "js" . DIRECTORY_SEPARATOR );
define( 'VI_WOO_ALIDROPSHIP_IMAGES', VI_WOO_ALIDROPSHIP_ASSETS . "images/" );
define( 'VI_WOO_ALIDROPSHIP_EXTENSION_VERSION', '1.0' );


/*Constants for AliExpress dropshipping API*/
define( 'VI_WOOCOMMERCE_ALIDROPSHIP_GET_SIGNATURE_SEARCH_PRODUCT', 'https://api.villatheme.com/wp-json/aliexpress/search' );
/*Include functions file*/
$require_files=[
	VI_WOO_ALIDROPSHIP_ADMIN =>['class-villatheme-admin-show-message.php'],
	VI_WOO_ALIDROPSHIP_INCLUDES =>[
		'functions.php',
		'class-ald-post.php',
		'support.php',
		'wp-async-request.php',
		'wp-background-process.php',
		'data.php',
		'ali-product-query.php',
		'class-vi-wad-draft-product.php',
		'class-vi-wad-error-images-table.php',
		'class-vi-wad-background-download-images.php',
		'class-vi-wad-background-migrate-new-table.php',
		'class-vi-wad-background-import.php',
		'class-vi-wad-background-download-description.php',
		'setup-wizard.php',
	]
];
foreach ($require_files as $k => $v){
	foreach ($v as $file_name){
		$file = "{$k}{$file_name}";
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
vi_include_folder( VI_WOO_ALIDROPSHIP_ADMIN, 'VI_WOO_ALIDROPSHIP_Admin_' );
vi_include_folder( VI_WOO_ALIDROPSHIP_CLASS );
vi_include_folder( VI_WOO_ALIDROPSHIP_FRONTEND, 'VI_WOO_ALIDROPSHIP_Frontend_' );
vi_include_folder( VI_WOO_ALIDROPSHIP_PLUGINS, 'VI_WOO_ALIDROPSHIP_Plugins_' );