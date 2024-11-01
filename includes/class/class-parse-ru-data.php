<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'VIALD_CLASS_Parse_RU_Data' ) ) {
	class VIALD_CLASS_Parse_RU_Data{
		/**
		 * @param $widgets
		 *
		 * @return mixed|string
		 */
		public static function aliexpress_ru_get_currency( $widgets ) {
			global $wad_count;
			$wad_count ++;
			$currency = '';
			foreach ( $widgets as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( $key === 'currencyProps' ) {
					$currency = isset( $value['selected']['currencyType'] ) ? $value['selected']['currencyType'] : '';
					break;
				}
				$currency = self::aliexpress_ru_get_currency( $value );
				if ( $currency ) {
					break;
				}
			}

			return $currency;
		}
		/**
		 * @param $widgets
		 *
		 * @return mixed|string|null
		 */
		public static function aliexpress_ru_get_description( $widgets ) {
			$description = null;

			foreach ( $widgets as $key => $value ) {

				if ( $key === 'html' ) {
					$description = $value ? $value : '';
					break;
				}
				if ( is_array( $value ) ) {
					$description = self::aliexpress_ru_get_description( $value );
				}
				if ( isset( $description ) ) {
					break;
				}
			}

			return $description;
		}

		/**
		 * @param $widgets
		 *
		 * @return array|mixed|null
		 */
		public static function aliexpress_ru_get_specs_module( $widgets ) {
			$specs_module = null;
			foreach ( $widgets as $key => $value ) {
				if ( $key === 'char' ) {
					$specs_module = $value ? $value : array();
					break;
				}
				if ( is_array( $value ) ) {
					$specs_module = self::aliexpress_ru_get_specs_module( $value );
				}
				if ( isset( $specs_module ) ) {
					break;
				}
			}

			return $specs_module;
		}

		/**
		 * @param $widgets
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_ru_get_store_info( $widgets ) {
			$store_info = null;
			foreach ( $widgets as $key => $value ) {
				if ( $key === 'shop' ) {
					$store_info = $value;
					break;
				}
				if ( is_array( $value ) ) {
					$store_info = self::aliexpress_ru_get_store_info( $value );
				}
				if ( isset( $store_info ) ) {
					break;
				}
			}

			return $store_info;
		}
		/**
		 * @param $widgets
		 *
		 * @return array|mixed|string
		 */
		public static function aliexpress_ru_get_data( $widgets ) {
			$data = '';
			foreach ( $widgets as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( $key === 'props' ) {
					if ( isset( $value['id'], $value['skuInfo'], $value['itemStatus'], $value['sellerId'] ) ) {
						$data = $value;
						break;
					}
				}
				$data = self::aliexpress_ru_get_data( $value );
				if ( $data ) {
					break;
				}
			}

			return $data;
		}
		/**
		 * @param $widgets
		 * @param $id
		 *
		 * @return mixed|null
		 */
		public static function aliexpress_ru_get_store_name( $widgets, $id ) {
			$store_name = null;
			foreach ( $widgets as $key => $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				if ( $key === 'props' ) {
					if ( isset( $value['id'] ) && $id == $value['id'] ) {
						$store_name = $value['name'];
						break;
					}
				}
				$store_name = self::aliexpress_ru_get_store_name( $value, $id );
				if ( $store_name ) {
					break;
				}
			}

			return $store_name;
		}

	}
}