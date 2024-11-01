<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'VIALD_CLASS_Parse_Other_Data' ) ) {
	class VIALD_CLASS_Parse_Other_Data {
		public static function parse_data_business( $data, $ignore_ship_from, $ignore_ship_from_default, &$result, &$error ) {
			if ( ! is_array( $result ) || empty( $result ) ) {
				$result = [ 'sku' => '' ];
			}
			$result['currency_code'] = $data['GLOBAL_DATA']['globalData']['currencyCode'] ?? '';
			$error                   = VIALD_CLASS_Parse_Ali_Data::error_currency_imported( $result['currency_code'] );
			if ( $error ) {
				return;
			}
			if ( ! empty( $data['GLOBAL_DATA']['globalData']['itemStatus'] ) ) {
				$error['error']   = 1;
				$error['message'] = esc_html__( 'Ali product is no longer available!', 'woocommerce-alidropship' );

				return;
			}
			$result['sku']             = $data['PRICE']['productId'] ?? $data['GLOBAL_DATA']['globalData']['productId'] ?? '';
			$result['name']            = $data['PRODUCT_TITLE']['text'] ?? $data['GLOBAL_DATA']['globalData']['subject'] ?? '';
			$result['specsModule']     = $data['PRODUCT_PROP_PC']['showedProps'] ?? [];
			$result['description_url'] = $data['DESC']['pcDescUrl'] ?? '';
			$result['gallery']         = $data['HEADER_IMAGE_PC']['imagePathList'] ?? [];
			if ( ! empty( $data['SHOP_CARD_PC']['storeName'] ) ) {
				$result['store_info'] = [
					'name' => $data['SHOP_CARD_PC']['storeName'] ?? '',
					'url'  => $data['SHOP_CARD_PC']['sellerInfo']['storeURL'] ?? '',
					'num'  => $data['SHOP_CARD_PC']['sellerInfo']['storeNum'] ?? '',
				];
			}
			$skuModule = $data['SKU'] ?? '';
			if ( is_array( $skuModule ) && ! empty( $skuModule ) ) {
				$listAttributes         = $listAttributesDisplayNames = array();
				$propertyValueNames     = array();
				$listAttributesNames    = $listAttributesSlug = $listAttributesIds = array();
				$variationImages        = $variations = array();
				$productSKUPropertyList = array();
				if ( isset( $skuModule['skuProperties'] ) ) {
					$productSKUPropertyList = $skuModule['skuProperties'];
				}
				$ignore_ship_from_default_id = '';
				if ( is_array( $productSKUPropertyList ) && ! empty( $productSKUPropertyList ) ) {
					foreach ( $productSKUPropertyList as $i => $skuProperty ) {
						$images            = array();
						$skuPropertyValues = $skuProperty['skuPropertyValues'];
						$attr_parent_id    = $skuProperty['skuPropertyId'];
						$skuPropertyName   = wc_sanitize_taxonomy_name( $skuProperty['skuPropertyName'] );
						if ( ( $attr_parent_id == 200007763 || strtolower( $skuPropertyName ) === 'ships-from' ) && $ignore_ship_from ) {
							foreach ( $skuPropertyValues as $value ) {
								if ( $value['skuPropertySendGoodsCountryCode'] === $ignore_ship_from_default ) {
									$ignore_ship_from_default_id = ! empty( $value['propertyValueId'] ) ? $value['propertyValueId'] : $value['propertyValueIdLong'];
								}
							}
							if ( $ignore_ship_from_default_id ) {
								continue;
							}
						} //point 1
						$attr = array(
							'values'   => array(),
							'slug'     => $skuPropertyName,
							'name'     => $skuProperty['skuPropertyName'],
							'position' => $i,
						);
						foreach ( $skuPropertyValues as $j => $j_item ) {
							$skuPropertyValue         = $skuPropertyValues[ $j ];
							$org_propertyValueId      = $skuPropertyValue['propertyValueId'] ?? $skuPropertyValue['propertyValueIdLong'];
							$propertyValueId          = "{$attr_parent_id}:{$org_propertyValueId}";
							$propertyValueName        = $skuPropertyValue['propertyValueName'];
							$propertyValueDisplayName = $skuPropertyValue['propertyValueDisplayName'];
							if ( in_array( $propertyValueDisplayName, $listAttributesDisplayNames ) ) {
								$propertyValueDisplayName = "{$propertyValueDisplayName}-{$org_propertyValueId}";
							}
							if ( in_array( $propertyValueName, $propertyValueNames ) ) {
								$propertyValueName = "{$propertyValueName}-{$org_propertyValueId}";
							}
							$listAttributesNames[ $propertyValueId ]        = $skuPropertyName;
							$listAttributesDisplayNames[ $propertyValueId ] = $propertyValueDisplayName;
							$propertyValueNames[ $propertyValueId ]         = $propertyValueName;
							$listAttributesIds[ $propertyValueId ]          = $attr_parent_id;
							$listAttributesSlug[ $propertyValueId ]         = $skuPropertyName;
							$attr['values'][ $propertyValueId ]             = $propertyValueDisplayName;
							$attr['values_sub'][ $propertyValueId ]         = $propertyValueName;
							$listAttributes[ $propertyValueId ]             = array(
								'name'      => $propertyValueDisplayName,
								'name_sub'  => $propertyValueName,
								'color'     => $skuPropertyValue['skuColorValue'] ?? '',
								'image'     => '',
								'ship_from' => $skuPropertyValue['skuPropertySendGoodsCountryCode'] ?? ''
							);
							if ( ! empty( $skuPropertyValue['skuPropertyImagePath'] ) ) {
								$images[ $propertyValueId ]                  = $skuPropertyValue['skuPropertyImagePath'];
								$variationImages[ $propertyValueId ]         = $skuPropertyValue['skuPropertyImagePath'];
								$listAttributes[ $propertyValueId ]['image'] = $skuPropertyValue['skuPropertyImagePath'];
							}
						}

						$result['list_attributes']               = $listAttributes;
						$result['list_attributes_names']         = $listAttributesNames;
						$result['list_attributes_ids']           = $listAttributesIds;
						$result['list_attributes_slugs']         = $listAttributesSlug;
						$result['variation_images']              = $variationImages;
						$result['attributes'][ $attr_parent_id ] = $attr;
						$result['images'][ $attr_parent_id ]     = $images;

						$result['parent'][ $attr_parent_id ] = $skuPropertyName;
					}
				}

				$skuPriceList = array();
				if ( isset( $skuModule['skuPaths'] ) ) {
					$skuPriceList = $skuModule['skuPaths'];
				}
				$skuPriceInfo = $data['PRICE']['skuIdStrPriceInfoMap'] ?? [];

				foreach ( $skuPriceList as $j => $j_item ) {
					if ( isset( $j_item['salable'] ) && ! $j_item['salable'] && (($j_item['skuStock'] ?? '') != 0) ) {
						continue;
					}
					$skuId = strval( $j_item['skuIdStr'] ?: $j_item['skuId'] );
					$temp  = array(
						'skuId'              => $skuId,
						'skuAttr'            => $j_item['skuAttr'] ?? '',
						'skuPropIds'         => isset( $j_item['skuPropIds'] ) ? $j_item['skuPropIds'] : '',
						'skuVal'             => [
							'availQuantity'  => $j_item['skuStock'] ?? 0,
							'actSkuCalPrice' => 0,
							'skuCalPrice'    => $skuPriceInfo[ $skuId ]['originalPrice']['value'] ?? '',
						],
						'image'              => '',
						'variation_ids'      => array(),
						'variation_ids_sub'  => array(),
						'variation_ids_slug' => array(),
						'ship_from'          => '',
					);
					if ( ! empty( $skuPriceInfo[ $skuId ]['salePriceLocal'] ) ) {
						$salePriceLocal = explode( '|', $skuPriceInfo[ $skuId ]['salePriceLocal'] );
						if ( isset( $salePriceLocal[1] ) ) {
							$sale_price = $salePriceLocal[1] . '.' . ( $salePriceLocal[2] ?? 0 );
							$temp['skuVal']['actSkuCalPrice'] = floatval( $sale_price );
						}
					}
					if ( ! $temp['skuPropIds'] && ! empty( $j_item['path'] ) && ! empty( $temp['skuAttr'] ) ) {
						$skuPath = explode( ';', $j_item['path'] );
						$skuAttr = explode( ';', $temp['skuAttr'] );
						if ( ! empty( $skuPath ) && ! empty( $skuAttr ) ) {
							$skuPropIds = $skuPath_map = [];
							foreach ( $skuPath as $skuPath_v ) {
								$skuPath_map[] = explode( ':', $skuPath_v );
							}
							foreach ( $skuAttr as $skuAttr_v ) {
								$skuAttr_map = explode( ':', $skuAttr_v );
								foreach ( $skuPath_map as $skuPath_v ) {
									if ( isset( $skuPath_v[1] ) && ! empty( $skuAttr_map[0] ) && ! empty( $skuPath_v[0] ) && $skuPath_v[0] == $skuAttr_map[0] ) {
										$skuPropIds[] = $skuPath_v[1];
									}
								}
							}
							if ( ! empty( $skuPropIds ) ) {
								$temp['skuPropIds'] = implode( ',', $skuPropIds );
							}
						}
					}
					if ( $temp['skuPropIds'] ) {
						$temAttr        = array();
						$temAttrSub     = array();
						$attrIds        = explode( ',', $temp['skuPropIds'] );
						$parent_attrIds = explode( ';', $temp['skuAttr'] );

						if ( $ignore_ship_from_default_id && ! in_array( $ignore_ship_from_default_id, $attrIds ) && $ignore_ship_from ) {
							continue;
						}
						foreach ( $attrIds as $k => $k_item ) {
							$propertyValueId = explode( ':', $parent_attrIds[ $k ] )[0] . ':' . $k_item;
							if ( isset( $listAttributesDisplayNames[ $propertyValueId ] ) ) {
								$temAttr[ $result['list_attributes_slugs'][ $propertyValueId ] ]    = $listAttributesDisplayNames[ $propertyValueId ];
								$temAttrSub[ $result['list_attributes_slugs'][ $propertyValueId ] ] = $propertyValueNames[ $propertyValueId ];
								if ( ! empty( $result['variation_images'][ $propertyValueId ] ) ) {
									$temp['image'] = $result['variation_images'][ $propertyValueId ];
								}
							}
							if ( ! empty( $listAttributes[ $propertyValueId ]['ship_from'] ) ) {
								$temp['ship_from'] = $listAttributes[ $propertyValueId ]['ship_from'];
							}
						}
						$temp['variation_ids']     = $temAttr;
						$temp['variation_ids_sub'] = $temAttrSub;
					}
					$variations [] = $temp;
				}
				$result['variations'] = $variations;
			}
		}

		public static function parse_data_from_AU( $data, $ignore_ship_from, $ignore_ship_from_default, &$result, &$error ) {
			if ( ! is_array( $result ) || empty( $result ) ) {
				$result = [ 'sku' => '' ];
			}

			$currency_component      = $data['currencyComponent'] ?? [];
			$result['currency_code'] = $currency_component['currencyCode'] ?? '';
			$error                   = VIALD_CLASS_Parse_Ali_Data::error_currency_imported( $result['currency_code'] );
			if ( $error ) {
				return;
			}
			if ( ! empty( $data['itemStatusComponent']['status'] ) ) {
				$error['error']   = 1;
				$error['message'] = esc_html__( 'Ali product is no longer available!', 'woocommerce-alidropship' );

				return;
			}

			$result['sku']             = $data['productInfoComponent']['id'] ?? '';
			$result['name']            = $data['productInfoComponent']['subject'] ?? '';
			$result['specsModule']     = $data['productPropComponent']['props'] ?? [];
			$result['description_url'] = $data['productDescComponent']['descriptionUrl'] ?? '';
			$result['gallery']         = $data['imageComponent']['imagePathList'] ?? [];

			if ( ! empty( $data['sellerComponent'] ) ) {
				$result['store_info'] = [
					'name' => $data['sellerComponent']['storeName'] ?? '',
					'url'  => $data['sellerComponent']['storeURL'] ?? '',
					'num'  => $data['sellerComponent']['storeNum'] ?? '',
				];
			}

			$sku_module                 = $data['skuComponent'] ?? [];
			$sku_module['skuPriceList'] = $data['priceComponent']['skuPriceList'] ?? [];
			VIALD_CLASS_Parse_Ali_Data::handle_sku_module( $sku_module, $ignore_ship_from, $ignore_ship_from_default, $result );
		}
	}
}