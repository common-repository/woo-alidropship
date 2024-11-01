<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'VIALD_CLASS_Parse_PT_Data' ) ) {
	class VIALD_CLASS_Parse_PT_Data {
		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_action_module( $data ) {
			$action_module = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 14 ) === 'actionButtons_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'actionButtons' ) {
						if ( isset( $value['fields'] ) ) {
							$action_module = $value['fields'];
							break;
						}
					}
				}
				$action_module = self::aliexpress_pt_get_action_module( $value );
				if ( isset( $action_module ) ) {
					break;
				}
			}

			return $action_module;
		}
		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_description( $data ) {
			$desc = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 12 ) === 'description_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'description' ) {
						if ( isset( $value['fields'], $value['fields']['detailDesc'] ) ) {
							$desc = $value['fields']['detailDesc'];
							break;
						}
					}
				}
				$desc = self::aliexpress_pt_get_description( $value );
				if ( isset( $desc ) ) {
					break;
				}
			}

			return $desc;
		}

		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_specs_module( $data ) {
			$specs = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 10 ) === 'specsInfo_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'specsInfo' ) {
						if ( isset( $value['fields'], $value['fields']['specs'] ) ) {
							$specs = $value['fields']['specs'];
							break;
						}
					}
				}
				$specs = self::aliexpress_pt_get_specs_module( $value );
				if ( isset( $specs ) ) {
					break;
				}
			}

			return $specs;
		}
		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_store_info( $data ) {
			$store_info = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 15 ) === 'storeRecommend_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'storeRecommend' ) {
						if ( isset( $value['fields'] ) ) {
							$store_info = $value['fields'];
							break;
						}
					}
				}
				$store_info = self::aliexpress_pt_get_store_info( $value );
				if ( isset( $store_info ) ) {
					break;
				}
			}

			return $store_info;
		}
		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_image_view( $data ) {
			$image_view = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 10 ) === 'imageView_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'imageView' ) {
						if ( isset( $value['fields'] ) ) {
							$image_view = $value['fields'];
							break;
						}
					}
				}
				$image_view = self::aliexpress_pt_get_image_view( $value );
				if ( isset( $image_view ) ) {
					break;
				}
			}

			return $image_view;
		}

		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_sku_module( $data ) {
			$sku_module = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 4 ) === 'sku_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'sku' ) {
						if ( isset( $value['fields'] ) ) {
							$sku_module = $value['fields'];
							break;
						}
					}
				}
				$sku_module = self::aliexpress_pt_get_sku_module( $value );
				if ( isset( $sku_module ) ) {
					break;
				}
			}

			return $sku_module;
		}
		/**
		 * @param $data
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_pt_get_title_module( $data ) {
			$title_module = null;
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 12 ) === 'titleBanner_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'titleBanner' ) {
						if ( isset( $value['fields'] ) ) {
							$title_module = $value['fields'];
							break;
						}
					}
				}
				$title_module = self::aliexpress_pt_get_title_module( $value );
				if ( isset( $title_module ) ) {
					break;
				}
			}

			return $title_module;
		}

		/**
		 * @param $data
		 *
		 * @return mixed|string
		 */
		public static function aliexpress_pt_get_trade_currency( $data ) {
			$currency = '';
			foreach ( $data as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( substr( $key, 0, 9 ) === 'shipping_' ) {
					if ( isset( $value['type'] ) && $value['type'] === 'shipping' ) {
						if ( isset( $value['fields'], $value['fields']['tradeCurrency'] ) && $value['fields']['tradeCurrency'] ) {
							$currency = $value['fields']['tradeCurrency'];
							break;
						}
					}
				}
				$currency = self::aliexpress_pt_get_trade_currency( $value );
				if ( $currency ) {
					break;
				}
			}

			return $currency;
		}
	}
}