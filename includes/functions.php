<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Function include all files in folder
 *
 * @param $path   Directory address
 * @param $ext    array file extension what will include
 * @param $prefix string Class prefix
 */
if ( ! function_exists( 'vi_include_folder' ) ) {
	function vi_include_folder( $path, $prefix = '', $ext = array( 'php' ) ) {

		/*Include all files in payment folder*/
		if ( ! is_array( $ext ) ) {
			$ext = explode( ',', $ext );
			$ext = array_map( 'trim', $ext );
		}
		$sfiles = scandir( $path );
		foreach ( $sfiles as $sfile ) {
			if ( $sfile != '.' && $sfile != '..' ) {
				if ( is_file( $path . "/" . $sfile ) ) {
					$ext_file  = pathinfo( $path . "/" . $sfile );
					$file_name = $ext_file['filename'];
					if ( $ext_file['extension'] ) {
						if ( in_array( $ext_file['extension'], $ext ) ) {
							$class = preg_replace( '/\W/i', '_', $prefix . ucfirst( $file_name ) );

							if ( ! class_exists( $class ) ) {
								require_once $path . $sfile;
								if ( class_exists( $class ) ) {
									new $class;
								}
							}
						}
					}
				}
			}
		}
	}
}

if ( ! function_exists( 'vi_wad_set_catalog_visibility' ) ) {
	function vi_wad_set_catalog_visibility( $product_id, $catalog_visibility ) {
		$terms = array();
		switch ( $catalog_visibility ) {
			case 'hidden':
				$terms[] = 'exclude-from-search';
				$terms[] = 'exclude-from-catalog';
				break;
			case 'catalog':
				$terms[] = 'exclude-from-search';
				break;
			case 'search':
				$terms[] = 'exclude-from-catalog';
				break;
		}
		if ( count( $terms ) && ! is_wp_error( wp_set_post_terms( $product_id, $terms, 'product_visibility', false ) ) ) {
			delete_transient( 'wc_featured_products' );
			do_action( 'woocommerce_product_set_visibility', $product_id, $catalog_visibility );
		}
	}

}
if ( ! function_exists( 'vi_wad_upload_image' ) ) {
	function vi_wad_upload_image( $url, $post_parent = 0, $exclude = array(), $post_title = '', $desc = null ) {
		preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG|webp|WEBP)/', $url, $matches );
		if ( is_array( $matches ) && count( $matches ) ) {
			if ( ! in_array( strtolower( $matches[1] ), $exclude ) ) {
				add_filter( 'big_image_size_threshold', '__return_false' );
				//add product image:
				if ( ! function_exists( 'media_handle_upload' ) ) {
					require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
					require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
					require_once( ABSPATH . "wp-admin" . '/includes/media.php' );
				}
				// Download file to temp location
				$tmp                    = download_url( $url );
				$img_name = trim(wp_parse_url($url)['path']??'','/');
				if ($img_name){
					$img_name = str_replace('/','-',$img_name);
					$img_name = substr($img_name,0,strpos($img_name,$matches[1]));
				}
				if (!$img_name){
					$img_name = basename( $matches[0] );
					$img_name = substr($img_name,0,strpos($img_name,$matches[1])).time();
				}
				$file_array['name']     = apply_filters( 'vi_wad_image_file_name', $img_name.$matches[1], $post_parent,$post_title );
				$file_array['tmp_name'] = $tmp;

				// If error storing temporarily, unlink
				if ( is_wp_error( $tmp ) ) {

					return $tmp;
				}
				$args = array();
				if ( $post_parent ) {
					$args['post_parent'] = $post_parent;
				}
				if ( $post_title ) {
					$args['post_title'] = $post_title;
				}
				//use media_handle_sideload to upload img:
				$thumbid = media_handle_sideload( $file_array, '', $desc, $args );
				// If error storing permanently, unlink
				if ( is_wp_error( $thumbid ) ) {
					wp_delete_file( $file_array['tmp_name'] );
				}

				return $thumbid;
			} else {
				return new WP_Error( 'vi_wad_file_type_not_permitted', esc_html__( 'File type is not permitted', 'woo-alidropship' ) );
			}
		} else {
			return new WP_Error( 'vi_wad_file_type_not_permitted', esc_html__( 'Can not detect file type', 'woo-alidropship' ) );
		}
	}
}
if ( ! function_exists( 'woocommerce_version_check' ) ) {
	function woocommerce_version_check( $version = '3.0' ) {
		global $woocommerce;

		if ( version_compare( $woocommerce->version, $version, ">=" ) ) {
			return true;
		}

		return false;
	}
}
if ( ! function_exists( 'vi_wad_json_decode' ) ) {
	function vi_wad_json_decode( $json, $assoc = true, $depth = 512, $options = 2 ) {
		if (is_array($json)){
			return $json;
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$json = mb_convert_encoding( $json, 'UTF-8', 'UTF-8' );
		}

		return json_decode( $json, $assoc, $depth, $options );
	}
}
if ( ! function_exists( 'vi_wad_set_time_limit' ) ) {
	function vi_wad_set_time_limit() {
		ini_set( 'max_execution_time', '3000' );
		ini_set( 'max_input_time', '3000' );
		ini_set( 'default_socket_timeout', '3000' );
		@set_time_limit( 0 );
	}
}