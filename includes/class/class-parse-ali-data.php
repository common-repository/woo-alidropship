<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'VIALD_CLASS_Parse_Ali_Data' ) ) {
	class VIALD_CLASS_Parse_Ali_Data{
		protected static function prepare_json_data($html,$ignore_ship_from,$ignore_ship_from_default, &$attributes, &$response){
			$listAttributes             =  $listAttributesDisplayNames = array();
			$listAttributesNames        =  $listAttributesSlug         =  $listAttributesIds          = array();
			$variationImages            =  $variations                 = array();
			$productVariationMaps       = array();
			$propertyValueNames         = array();
			$ali_product_data = vi_wad_json_decode( $html );
			if ( json_last_error() ) {
				/*Data crawled directly with PHP is string. Find needed data in JSON then convert to array*/
				preg_match( '/{"actionModule".+}}/im', $html, $match_html );
				if ( count( $match_html ) === 1 && $match_html[0] ) {
					$html             = $match_html[0];
					$ali_product_data = vi_wad_json_decode( $html );
				} else {
					preg_match( '/{"widgets".+}}/im', $html, $match_html );
					if ( count( $match_html ) === 1 && $match_html[0] ) {
						if ( class_exists( 'DOMDocument' ) ) {
							$document = new DOMDocument();
							$document->loadHTML( $html );
							$ae_data = $document->getElementById( '__AER_DATA__' );
							if ( $ae_data ) {
								$ali_product_data = $ae_data->textContent;
							}
						}
						if ( ! $ali_product_data ) {
							$html             = preg_replace( '/<\/script>.+}}/im', '', $match_html[0] );
							$ali_product_data = vi_wad_json_decode( $html );
						}
					} else {
						preg_match( '/_init_data_= { data: .+}/im', $html, $match_html );
						if ( count( $match_html ) === 1 && $match_html[0] ) {
							$html             = '{ "data"' . substr( $match_html[0], 19 );
							$html             = preg_replace( '/<\/script>.+}}/im', '', $html );
							$ali_product_data = vi_wad_json_decode( $html );
						} else {
							preg_match( '/{"tradeComponent".+}}/im', $html, $match_html );

							if ( ! empty( $match_html[0] ) ) {
								$html             = $match_html[0];
								$ali_product_data = vi_wad_json_decode( $html );
							}
						}
					}
				}

				if (!$ali_product_data){
					preg_match('/window.runParams = {\n.*}/m', $html, $match_html );
					if ( count( $match_html ) === 1 && $match_html[0] ){
						$html             = $match_html[0];
						$html             = trim(str_replace('window.runParams = {','',$html));
						$html             = trim(str_replace('data:','',$html));
						$ali_product_data = vi_wad_json_decode( $html );
					}
				}
			}
			if ( is_array( $ali_product_data ) && !empty( $ali_product_data ) ) {
				if ( isset( $ali_product_data['actionModule'] ) ) {
					$actionModule                      = isset( $ali_product_data['actionModule'] ) ? $ali_product_data['actionModule'] : array();
					$descriptionModule                 = isset( $ali_product_data['descriptionModule'] ) ? $ali_product_data['descriptionModule'] : array();
					$storeModule                       = isset( $ali_product_data['storeModule'] ) ? $ali_product_data['storeModule'] : array();
					$imageModule                       = isset( $ali_product_data['imageModule'] ) ? $ali_product_data['imageModule'] : array();
					$skuModule                         = isset( $ali_product_data['skuModule'] ) ? $ali_product_data['skuModule'] : array();
					$titleModule                       = isset( $ali_product_data['titleModule'] ) ? $ali_product_data['titleModule'] : array();
					$webEnv                            = isset( $ali_product_data['webEnv'] ) ? $ali_product_data['webEnv'] : array();
					$commonModule                      = isset( $ali_product_data['commonModule'] ) ? $ali_product_data['commonModule'] : array();
					$specsModule                       = isset( $ali_product_data['specsModule'] ) ? $ali_product_data['specsModule'] : array();
					$priceModule                       = isset( $ali_product_data['priceModule'] ) ? $ali_product_data['priceModule'] : array();
					$shippingModule                    = isset( $ali_product_data['shippingModule'] ) ? $ali_product_data['shippingModule'] : array();
					$attributes['currency_code']       = isset( $webEnv['currency'] ) ? $webEnv['currency'] : '';
					$attributes['trade_currency_code'] = isset( $commonModule['tradeCurrencyCode'] ) ? $commonModule['tradeCurrencyCode'] : '';
					$response = self::error_currency_imported( $attributes['currency_code'] );
					if ($response){
						if (!$attributes['trade_currency_code'] || ! self::is_currency_supported( $attributes['trade_currency_code'] )){
							return;
						}
					}

					if ( ! empty( $actionModule['productId'] ) ) {
						$attributes['sku'] = $actionModule['productId'];
					} elseif ( ! empty( $descriptionModule['productId'] ) ) {
						$attributes['sku'] = $descriptionModule['productId'];
					}

					if ( isset( $actionModule['itemStatus'] ) && intval( $actionModule['itemStatus'] ) > 0 ) {
						$response['error']  = 1;
						$response['message'] = esc_html__( 'This product is no longer available', 'woo-alidropship' );

						return;
					}

					$attributes['description_url'] = isset( $descriptionModule['descriptionUrl'] ) ? $descriptionModule['descriptionUrl'] : '';
					$attributes['specsModule']     = isset( $specsModule['props'] ) ? $specsModule['props'] : array();
					$attributes['store_info']      = array(
						'name' => $storeModule['storeName'],
						'url'  => $storeModule['storeURL'],
						'num'  => $storeModule['storeNum'],
					);
					$attributes['gallery']         = isset( $imageModule['imagePathList'] ) ? $imageModule['imagePathList'] : array();

					self::handle_sku_module( $skuModule, $ignore_ship_from, $ignore_ship_from_default, $attributes );
					$attributes['name'] = isset( $titleModule['subject'] ) ? $titleModule['subject'] : '';

				} elseif ( isset( $ali_product_data['widgets'] ) ) {
					$widgets = $ali_product_data['widgets'];

					if ( is_array( $widgets ) && !empty( $widgets ) ) {
						$props = array();
						$is_ru = false;

						foreach ( $widgets as $widget ) {
							if ( ! empty( $widget['props'] ) && ! empty( $widget['props']['id'] ) ) {
								if ( isset( $widget['props']['quantity']['activity'] ) ) {
									$attributes['currency_code'] = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_currency( $widgets );
									if ( isset( $widget['props']['itemStatus'] ) && $widget['props']['itemStatus'] == 2 ) {
										$response['error']  = 1;
										$response['message'] = esc_html__( 'This product is no longer available', 'woo-alidropship' );

										return;
									} else {
										$props                     = $widget['props'];
										$attributes['description'] = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_description( $widgets );
										$attributes['specsModule'] = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_specs_module( $widgets );
										$attributes['store_info']  = array( 'name' => '', 'url' => '', 'num' => '', );
										$store_info                = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_store_info( $widgets );

										if ( $store_info ) {
											$attributes['store_info']['name'] = isset( $store_info['name'] ) ? $store_info['name'] : '';
											$attributes['store_info']['url']  = isset( $store_info['url'] ) ? $store_info['url'] : '';
											$attributes['store_info']['num']  = isset( $store_info['storeNum'] ) ? $store_info['storeNum'] : '';
										}
									}
								} else {
									$attributes['currency_code'] = isset( $widget['children'][3]['props']['localization']['currencyProps']['selected']['currencyType'] ) ? $widget['children'][3]['props']['localization']['currencyProps']['selected']['currencyType'] : '';
									if ( isset( $widget['children'] ) && is_array( $widget['children'] ) ) {
										if ( count( $widget['children'] ) > 7 ) {
											if ( isset( $widget['children'][7]['children'] ) && is_array( $widget['children'][7]['children'] ) && !empty( $widget['children'][7]['children'] ) ) {
												$children = $widget['children'][7]['children'];
												if ( isset( $children[0]['props'] ) && is_array( $children[0]['props'] ) && !empty( $children[0]['props'] ) ) {
													$props = $children[0]['props'];
												}
												$attributes['description'] = isset( $widget['children'][10]['children'][1]['children'][1]['children'][0]['children'][0]['props']['html'] ) ? $widget['children'][10]['children'][1]['children'][1]['children'][0]['children'][0]['props']['html'] : '';
												$attributes['specsModule'] = isset( $widget['children'][10]['children'][1]['children'][1]['children'][2]['children'][0]['props']['char'] ) ? $widget['children'][10]['children'][1]['children'][1]['children'][2]['children'][0]['props']['char'] : array();
												$attributes['store_info']  = array(
													'name' => isset( $widget['children'][4]['props']['shop']['name'] ) ? $widget['children'][4]['props']['shop']['name'] : '',
													'url'  => isset( $widget['children'][4]['props']['shop']['url'] ) ? $widget['children'][4]['props']['shop']['url'] : '',
													'num'  => isset( $widget['children'][4]['props']['shop']['storeNum'] ) ? $widget['children'][4]['props']['shop']['storeNum'] : '',
												);
											}
										} else {
											$response['status']  = 'error';
											$response['message'] = esc_html__( 'This product is no longer available', 'woo-alidropship' );
										}
									}
								}
								break;
							}
						}

						if ( ! isset( $attributes['currency_code'] ) ) {
							$props = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_data( $widgets );
							if ( $props ) {
								$attributes['currency_code'] = 'RUB';
								$attributes['description']   = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_description( $widgets );
								$attributes['specsModule']   = array();
								$attributes['store_info']    = array(
									'name' => '',
									'url'  => isset( $props['storeUrl'] ) ? $props['storeUrl'] : '',
									'num'  => isset( $props['sellerId'] ) ? $props['sellerId'] : '',
								);
								if ( $attributes['store_info']['num'] ) {
									$attributes['store_info']['name'] = VIALD_CLASS_Parse_RU_Data::aliexpress_ru_get_store_name( $widgets, $attributes['store_info']['num'] );
								}
								$is_ru = true;
							}
						}
						$response = self::error_currency_imported( $attributes['currency_code'] );
						if ($response){
							return;
						}

						if ( !empty( $props ) ) {
							if ( ! empty( $props['id'] ) ) {
								$attributes['sku'] = $props['id'];
							}
							$attributes['gallery'] = array();
							if ( isset( $props['gallery'] ) && is_array( $props['gallery'] ) && !empty( $props['gallery'] ) ) {
								foreach ( $props['gallery'] as $gallery ) {
									if ( ! empty( $gallery['imageUrl'] ) ) {
										$attributes['gallery'][] = $gallery['imageUrl'];
									}
								}
							}
							$skuModule = isset( $props['skuInfo'] ) ? $props['skuInfo'] : array();
							if ( is_array($skuModule) && !empty( $skuModule ) ) {
								$productSKUPropertyList      = isset( $skuModule['propertyList'] ) ? $skuModule['propertyList'] : array();
								$ignore_ship_from_default_id = '';
								if ( is_array( $productSKUPropertyList ) && !empty( $productSKUPropertyList ) ) {
									foreach ($productSKUPropertyList as $i => $i_item){
										$images            = array();
										$skuPropertyValues = $productSKUPropertyList[ $i ]['values'];
										$attr_parent_id    = $productSKUPropertyList[ $i ]['id'];
										$skuPropertyName   = wc_sanitize_taxonomy_name( $productSKUPropertyList[ $i ]['name'] );
										if ( strtolower( $skuPropertyName ) === 'ships-from' && $ignore_ship_from ) {
											foreach ( $skuPropertyValues as $value ) {
												if ( isset( $value['skuPropertySendGoodsCountryCode'] ) && $value['skuPropertySendGoodsCountryCode'] === $ignore_ship_from_default ) {
													$ignore_ship_from_default_id = $value['id'];
												}
											}
											if ( $ignore_ship_from_default_id ) {
												continue;
											}
										} //point 1
										$attr = array(
											'values'   => array(),
											'slug'     => $skuPropertyName,
											'name'     => $productSKUPropertyList[ $i ]['name'],
											'position' => $i,
										);

										if ( $is_ru ) {
											foreach ($skuPropertyValues as $j => $j_item){
//											for ( $j = 0; $j < count( $skuPropertyValues ); $j ++ ) {
												$skuPropertyValue = $skuPropertyValues[ $j ];
												$propertyValueId  = $skuPropertyValue['id'];
//												$propertyValueId          = "{$attr_parent_id}:{$org_propertyValueId}";
												$propertyValueName        = $skuPropertyValue['name'];
												$propertyValueDisplayName = $skuPropertyValue['displayName'];
												if ( in_array( $propertyValueDisplayName, $listAttributesDisplayNames ) ) {
//													$propertyValueDisplayName = "{$propertyValueDisplayName}-{$org_propertyValueId}";
												}
												if ( in_array( $propertyValueName, $propertyValueNames ) ) {
//													$propertyValueName = "{$propertyValueName}-{$org_propertyValueId}";
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
													'color'     => isset( $skuPropertyValue['colorValue'] ) ? $skuPropertyValue['colorValue'] : '',
													'image'     => '',
													'ship_from' => isset( $skuPropertyValue['skuPropertySendGoodsCountryCode'] ) ? $skuPropertyValue['skuPropertySendGoodsCountryCode'] : ''
												);
												if ( isset( $skuPropertyValue['imageMainUrl'] ) && $skuPropertyValue['imageMainUrl'] ) {
													$images[ $propertyValueId ]                  = $skuPropertyValue['imageMainUrl'];
													$variationImages[ $propertyValueId ]         = $skuPropertyValue['imageMainUrl'];
													$listAttributes[ $propertyValueId ]['image'] = $skuPropertyValue['imageMainUrl'];
												}
											}
										} else {
											foreach ($skuPropertyValues as $j => $j_item){
//											for ( $j = 0; $j < count( $skuPropertyValues ); $j ++ ) {
												$skuPropertyValue         = $skuPropertyValues[ $j ];
												$org_propertyValueId      = $skuPropertyValue['id'];
												$propertyValueId          = "{$attr_parent_id}:{$org_propertyValueId}";
												$propertyValueName        = $skuPropertyValue['name'];
												$propertyValueDisplayName = $skuPropertyValue['displayName'];
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
													'color'     => isset( $skuPropertyValue['colorValue'] ) ? $skuPropertyValue['colorValue'] : '',
													'image'     => '',
													'ship_from' => isset( $skuPropertyValue['skuPropertySendGoodsCountryCode'] ) ? $skuPropertyValue['skuPropertySendGoodsCountryCode'] : ''
												);
												if ( isset( $skuPropertyValue['imageMainUrl'] ) && $skuPropertyValue['imageMainUrl'] ) {
													$images[ $propertyValueId ]                  = $skuPropertyValue['imageMainUrl'];
													$variationImages[ $propertyValueId ]         = $skuPropertyValue['imageMainUrl'];
													$listAttributes[ $propertyValueId ]['image'] = $skuPropertyValue['imageMainUrl'];
												}
											}
										}


										$attributes['list_attributes']               = $listAttributes;
										$attributes['list_attributes_names']         = $listAttributesNames;
										$attributes['list_attributes_ids']           = $listAttributesIds;
										$attributes['list_attributes_slugs']         = $listAttributesSlug;
										$attributes['variation_images']              = $variationImages;
										$attributes['attributes'][ $attr_parent_id ] = $attr;
										$attributes['images'][ $attr_parent_id ]     = $images;

										$attributes['parent'][ $attr_parent_id ] = $skuPropertyName;
									}
								}

								$skuPriceList = isset( $skuModule['priceList'] ) ? $skuModule['priceList'] : array();
								if (is_array($skuPriceList) && !empty($skuPriceList)) {
									foreach ($skuPriceList as $j => $j_item){
										if(isset($j_item['salable']) && !$j_item['salable']){
											continue;
										}
//	                                for ( $j = 0; $j < count( $skuPriceList ); $j ++ ) {
										$temp = array(
											'skuId'              => isset( $skuPriceList[ $j ]['skuIdStr'] ) ? strval( $skuPriceList[ $j ]['skuIdStr'] ) : strval( $skuPriceList[ $j ]['skuId'] ),
											'skuAttr'            => isset( $skuPriceList[ $j ]['skuAttr'] ) ? $skuPriceList[ $j ]['skuAttr'] : '',
											'skuPropIds'         => isset( $skuPriceList[ $j ]['skuPropIds'] ) ? $skuPriceList[ $j ]['skuPropIds'] : '',
											'skuVal'             => array(
												'availQuantity'  => isset( $skuPriceList[ $j ]['availQuantity'] ) ? $skuPriceList[ $j ]['availQuantity'] : 0,
												'actSkuCalPrice' => isset( $skuPriceList[ $j ]['activityAmount']['value'] ) ? $skuPriceList[ $j ]['activityAmount']['value'] : '',
												'skuCalPrice'    => isset( $skuPriceList[ $j ]['amount']['value'] ) ? $skuPriceList[ $j ]['amount']['value'] : '',
											),
											'image'              => '',
											'variation_ids'      => array(),
											'variation_ids_sub'  => array(),
											'variation_ids_slug' => array(),
											'ship_from'          => '',
										);
										if ( $temp['skuPropIds'] ) {
											$temAttr    = array();
											$temAttrSub = array();
											$attrIds    = explode( ',', $temp['skuPropIds'] );

											if ( $ignore_ship_from_default_id && ! in_array( $ignore_ship_from_default_id, $attrIds ) && $ignore_ship_from ) {
												continue;
											}

											if ( $is_ru ) {
												foreach ($attrIds as $k => $k_item){
//				                                for ( $k = 0; $k < count( $attrIds ); $k ++ ) {
													$propertyValueId = $attrIds[ $k ];

													if ( isset( $listAttributesDisplayNames[ $propertyValueId ] ) ) {
														$temAttr[ $attributes['list_attributes_slugs'][ $propertyValueId ] ]    = $listAttributesDisplayNames[ $propertyValueId ];
														$temAttrSub[ $attributes['list_attributes_slugs'][ $propertyValueId ] ] = $propertyValueNames[ $propertyValueId ];
														if ( ! empty( $attributes['variation_images'][ $propertyValueId ] ) ) {
															$temp['image'] = $attributes['variation_images'][ $propertyValueId ];
														}
													}
													if ( ! empty( $listAttributes[ $propertyValueId ]['ship_from'] ) ) {
														$temp['ship_from'] = $listAttributes[ $propertyValueId ]['ship_from'];
													}
												}

											} else {
												$parent_attrIds = explode( ';', $temp['skuAttr'] );
												foreach ($attrIds as $k => $k_item){
//				                                for ( $k = 0; $k < count( $attrIds ); $k ++ ) {
													$propertyValueId = explode( ':', $parent_attrIds[ $k ] )[0] . ':' . $attrIds[ $k ];

													if ( isset( $listAttributesDisplayNames[ $propertyValueId ] ) ) {
														$temAttr[ $attributes['list_attributes_slugs'][ $propertyValueId ] ]    = $listAttributesDisplayNames[ $propertyValueId ];
														$temAttrSub[ $attributes['list_attributes_slugs'][ $propertyValueId ] ] = $propertyValueNames[ $propertyValueId ];
														if ( ! empty( $attributes['variation_images'][ $propertyValueId ] ) ) {
															$temp['image'] = $attributes['variation_images'][ $propertyValueId ];
														}
													}
													if ( ! empty( $listAttributes[ $propertyValueId ]['ship_from'] ) ) {
														$temp['ship_from'] = $listAttributes[ $propertyValueId ]['ship_from'];
													}
												}

											}

											$temp['variation_ids']     = $temAttr;
											$temp['variation_ids_sub'] = $temAttrSub;
										}

										$variations [] = $temp;
									}
								}
								$attributes['variations'] = $variations;
							}
							$attributes['name'] = isset( $props['name'] ) ? $props['name'] : '';
						}
						$attributes['description_url'] = '';

					}
				} elseif ( isset( $ali_product_data['data']['data'] ) ) {
					$attributes['currency_code'] = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_trade_currency( $ali_product_data['data']['data'] );
					$response = self::error_currency_imported( $attributes['currency_code'] );
					if ($response){
						return;
					}

					$actionModule = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_action_module( $ali_product_data['data']['data'] );

					if ( $actionModule ) {
						$attributes['sku'] = isset( $actionModule['productId'] ) ? $actionModule['productId'] : '';
						if ( isset( $actionModule['itemStatus'] ) && intval( $actionModule['itemStatus'] ) > 0 ) {
							$response['error']  = 1;
							$response['message'] = esc_html__( 'This product is no longer available', 'woo-alidropship' );

							return;
						}
					}

					$attributes['description_url'] = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_description( $ali_product_data['data']['data'] );
					$attributes['specsModule']     = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_specs_module( $ali_product_data['data']['data'] );
					$attributes['store_info']      = array(
						'name' => '',
						'url'  => '',
						'num'  => '',
					);
					$store_info                    = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_store_info( $ali_product_data['data']['data'] );
					if ( $store_info ) {
						$attributes['store_info']['name'] = isset( $store_info['storeName'] ) ? $store_info['storeName'] : '';
						$attributes['store_info']['url']  = isset( $store_info['storeURL'] ) ? $store_info['storeURL'] : '';
						$attributes['store_info']['num']  = isset( $store_info['storeNum'] ) ? $store_info['storeNum'] : '';
					}
					$image_view = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_image_view( $ali_product_data['data']['data'] );
					if ( $image_view ) {
						$attributes['gallery'] = isset( $image_view['imagePathList'] ) ? $image_view['imagePathList'] : array();
					}
					$skuModule = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_sku_module( $ali_product_data['data']['data'] );
					if ( $skuModule ) {
						self::handle_sku_module( $skuModule, $ignore_ship_from, $ignore_ship_from_default, $attributes );
					}
					$titleModule = VIALD_CLASS_Parse_PT_Data::aliexpress_pt_get_title_module( $ali_product_data['data']['data'] );
					if ( $titleModule ) {
						$attributes['name'] = isset( $titleModule['subject'] ) ? $titleModule['subject'] : '';
					}
				} elseif ( isset( $ali_product_data['tradeComponent'] ) ) {
					VIALD_CLASS_Parse_Other_Data::parse_data_from_AU( $ali_product_data, $ignore_ship_from, $ignore_ship_from_default, $attributes, $response );
				}
				if ( ! empty( $ali_product_data['productInfoComponent']['categoryPaths'] ) ) {
					$categories = explode( '/', $ali_product_data['productInfoComponent']['categoryPaths'] );

					$attributes['categories'] = $categories;
				}
				if (isset($ali_product_data['PRODUCT_TITLE'],$ali_product_data['PRICE'],$ali_product_data['SHIPPING'])){
					VIALD_CLASS_Parse_Other_Data::parse_data_business( $ali_product_data, $ignore_ship_from, $ignore_ship_from_default ,$attributes, $response);
				}
			} else {
				$descriptionModuleReg = '/"descriptionModule":(.*?),"features":{},"feedbackModule"/';
				preg_match( $descriptionModuleReg, $html, $descriptionModule );
				if ( $descriptionModule ) {
					$descriptionModule             = vi_wad_json_decode( $descriptionModule[1] );
					$attributes['sku']             = $descriptionModule['productId'];
					$attributes['description_url'] = $descriptionModule['descriptionUrl'];
				}

				$specsModuleReg = '/"specsModule":(.*?),"storeModule"/';
				preg_match( $specsModuleReg, $html, $specsModule );
				if ( $specsModule ) {
					$specsModule = vi_wad_json_decode( $specsModule[1] );
					if ( isset( $specsModule['props'] ) ) {
						$attributes['specsModule'] = $specsModule['props'];
					}
				}
				$storeModuleReg = '/"storeModule":(.*?),"titleModule"/';
				preg_match( $storeModuleReg, $html, $storeModule );
				if ( $storeModule ) {
					$storeModule              = vi_wad_json_decode( $storeModule[1] );
					$attributes['store_info'] = array(
						'name' => $storeModule['storeName'],
						'url'  => $storeModule['storeURL'],
						'num'  => $storeModule['storeNum'],
					);
				}
				$imagePathListReg = '/"imagePathList":(.*?),"name":"ImageModule"/';
				preg_match( $imagePathListReg, $html, $imagePathList );
				if ( $imagePathList ) {
					$imagePathList         = vi_wad_json_decode( $imagePathList[1] );
					$attributes['gallery'] = $imagePathList;
				}
				$skuModuleReg = '/"skuModule":(.*?),"specsModule"/';
				preg_match( $skuModuleReg, $html, $skuModule );
				if ( count( $skuModule ) == 2 ) {
					$skuModule                   = vi_wad_json_decode( $skuModule[1] );
					$productSKUPropertyList      = isset( $skuModule['productSKUPropertyList'] ) ? $skuModule['productSKUPropertyList'] : array();
					$ignore_ship_from_default_id = '';
					if ( is_array( $productSKUPropertyList ) && !empty( $productSKUPropertyList ) ) {
						foreach ($productSKUPropertyList as $i => $i_item){
//						for ( $i = 0; $i < count( $productSKUPropertyList ); $i ++ ) {
							$images            = array();
							$skuPropertyValues = $productSKUPropertyList[ $i ]['skuPropertyValues'];
							$attr_parent_id    = $productSKUPropertyList[ $i ]['skuPropertyId'];
							$skuPropertyName   = wc_sanitize_taxonomy_name( $productSKUPropertyList[ $i ]['skuPropertyName'] );
							if ( strtolower( $skuPropertyName ) === 'ships-from' && $ignore_ship_from ) {
								foreach ( $skuPropertyValues as $value ) {
									if ( $value['skuPropertySendGoodsCountryCode'] === $ignore_ship_from_default ) {
										$ignore_ship_from_default_id = $value['propertyValueId'] ? $value['propertyValueId'] : $value['propertyValueIdLong'];
									}
								}
								if ( $ignore_ship_from_default_id ) {
									continue;
								}
							} //point 1
							$attr = array(
								'values'   => array(),
								'slug'     => $skuPropertyName,
								'name'     => $productSKUPropertyList[ $i ]['skuPropertyName'],
								'position' => $i,
							);
							foreach ($skuPropertyValues as $j => $j_item){
//							for ( $j = 0; $j < count( $skuPropertyValues ); $j ++ ) {
								$skuPropertyValue                               = $skuPropertyValues[ $j ];
								$org_propertyValueId                            = $skuPropertyValue['propertyValueId'] ? $skuPropertyValue['propertyValueId'] : $skuPropertyValue['propertyValueIdLong'];
								$propertyValueId                                = "{$attr_parent_id}:{$org_propertyValueId}";
								$propertyValueName                              = $skuPropertyValue['propertyValueName'];
								$propertyValueDisplayName                       = $skuPropertyValue['propertyValueDisplayName'];
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
									'color'     => isset( $skuPropertyValue['skuColorValue'] ) ? $skuPropertyValue['skuColorValue'] : '',
									'image'     => '',
									'ship_from' => isset( $skuPropertyValue['skuPropertySendGoodsCountryCode'] ) ? $skuPropertyValue['skuPropertySendGoodsCountryCode'] : ''
								);
								if ( isset( $skuPropertyValue['skuPropertyImagePath'] ) && $skuPropertyValue['skuPropertyImagePath'] ) {
									$images[ $propertyValueId ]                  = $skuPropertyValue['skuPropertyImagePath'];
									$variationImages[ $propertyValueId ]         = $skuPropertyValue['skuPropertyImagePath'];
									$listAttributes[ $propertyValueId ]['image'] = $skuPropertyValue['skuPropertyImagePath'];
								}
							}

							$attributes['list_attributes']               = $listAttributes;
							$attributes['list_attributes_names']         = $listAttributesNames;
							$attributes['list_attributes_ids']           = $listAttributesIds;
							$attributes['list_attributes_slugs']         = $listAttributesSlug;
							$attributes['variation_images']              = $variationImages;
							$attributes['attributes'][ $attr_parent_id ] = $attr;
							$attributes['images'][ $attr_parent_id ]     = $images;

							$attributes['parent'][ $attr_parent_id ] = $skuPropertyName;
						}
					}

					$skuPriceList = $skuModule['skuPriceList'];
					foreach ($skuPriceList as $j => $j_item){
						if(isset($j_item['salable']) && !$j_item['salable']){
							continue;
						}
//					for ( $j = 0; $j < count( $skuPriceList ); $j ++ ) {
						$temp = array(
							'skuId'              => isset( $skuPriceList[ $j ]['skuIdStr'] ) ? strval( $skuPriceList[ $j ]['skuIdStr'] ) : strval( $skuPriceList[ $j ]['skuId'] ),
							'skuAttr'            => isset( $skuPriceList[ $j ]['skuAttr'] ) ? $skuPriceList[ $j ]['skuAttr'] : '',
							'skuPropIds'         => isset( $skuPriceList[ $j ]['skuPropIds'] ) ? $skuPriceList[ $j ]['skuPropIds'] : '',
							'skuVal'             => $skuPriceList[ $j ]['skuVal'],
							'image'              => '',
							'variation_ids'      => array(),
							'variation_ids_sub'  => array(),
							'variation_ids_slug' => array(),
							'ship_from'          => '',
						);
						if ( $temp['skuPropIds'] ) {
							$temAttr        = array();
							$temAttrSub     = array();
							$attrIds        = explode( ',', $temp['skuPropIds'] );
							$parent_attrIds = explode( ';', $temp['skuAttr'] );

							if ( $ignore_ship_from_default_id && ! in_array( $ignore_ship_from_default_id, $attrIds ) && $ignore_ship_from ) {
								continue;
							}

							foreach ($attrIds as $k => $k_item){
//                            for ( $k = 0; $k < count( $attrIds ); $k ++ ) {
								$propertyValueId = explode( ':', $parent_attrIds[ $k ] )[0] . ':' . $attrIds[ $k ];
								if ( isset( $listAttributesDisplayNames[ $propertyValueId ] ) ) {
									$temAttr[ $attributes['list_attributes_slugs'][ $propertyValueId ] ]    = $listAttributesDisplayNames[ $propertyValueId ];
									$temAttrSub[ $attributes['list_attributes_slugs'][ $propertyValueId ] ] = $propertyValueNames[ $propertyValueId ];
									if ( ! empty( $attributes['variation_images'][ $propertyValueId ] ) ) {
										$temp['image'] = $attributes['variation_images'][ $propertyValueId ];
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
					$attributes['variations'] = $variations;
				}
				$titleModuleReg = '/"titleModule":(.*?),"webEnv"/';
				preg_match( $titleModuleReg, $html, $titleModule );
				if ( count( $titleModule ) == 2 ) {
					$titleModule        = vi_wad_json_decode( $titleModule[1] );
					$attributes['name'] = $titleModule['subject'];
				}

				$webEnvReg = '/"webEnv":(.*?)}}/';
				preg_match( $webEnvReg, $html, $webEnv );
				if ( count( $webEnv ) == 2 ) {
					$webEnv                      = vi_wad_json_decode( $webEnv[1] . '}' );
					$attributes['currency_code'] = $webEnv['currency'];
				}
			}
			if ( ! $attributes['sku'] ) {
				$search  = array( "\n", "\r", "\t" );
				$replace = array( "", "", "" );
				$html    = str_replace( $search, $replace, $html );
				$regSku  = '/window\.runParams\.productId="([\s\S]*?)";/im';
				preg_match( $regSku, $html, $match_product_sku );
				if ( count( $match_product_sku ) === 2 && $match_product_sku[1] ) {
					$attributes['sku'] = $match_product_sku[1];
					$reg               = '/var skuProducts=(\[[\s\S]*?]);/im';
					$regId             = '/<a[\s\S]*?data-sku-id="(\d*?)"[\s\S]*?>(.*?)<\/a>/im';
					$regTitle          = '/<dt class="p-item-title">(.*?)<\/dt>[\s\S]*?data-sku-prop-id="(.*?)"/im';
					$regGallery        = '/imageBigViewURL=(\[[\s\S]*?]);/im';
					$regCurrencyCode   = '/window\.runParams\.currencyCode="([\s\S]*?)";/im';
					$regDetailDesc     = '/window\.runParams\.detailDesc="([\s\S]*?)";/im';
					$regOffline        = '/window\.runParams\.offline=([\s\S]*?);/im';
					$regName           = '/class="product-name" itemprop="name">([\s\S]*?)<\/h1>/im';
					$regDescription    = '/<ul class="product-property-list util-clearfix">([\s\S]*?)<\/ul>/im';
					preg_match( $regOffline, $html, $offlineMatches );
					if ( count( $offlineMatches ) == 2 ) {
						$offline = $offlineMatches[1];
					}

					preg_match( $reg, $html, $matches );
					if ( $matches ) {
						$productVariationMaps = vi_wad_json_decode( $matches[1] );
					}

					preg_match( $regDetailDesc, $html, $detailDescMatches );
					if ( $detailDescMatches ) {
						$attributes['description_url'] = $detailDescMatches[1];
					}

					preg_match( $regDescription, $html, $regDescriptionMatches );
					if ( $regDescriptionMatches ) {
						$attributes['short_description'] = $regDescriptionMatches[0];
					}

					$reg = '/<dl class="p-property-item">([\s\S]*?)<\/dl>/im';
					preg_match_all( $reg, $html, $matches );

					if ( !empty( $matches[0] ) ) {
						$match_variations = $matches[0];
						$title            = '';
						$titleSlug        = '';
						$reTitle1         = '/title="(.*?)"/mi';
						$reImage          = '/bigpic="(.*?)"/mi';
						$attr_parent_id   = '';
						foreach ($match_variations as $i => $i_item){
							preg_match( $regTitle, $match_variations[ $i ], $matchTitle );

							if ( count( $matchTitle ) == 3 ) {
								$title          = $matchTitle[1];
								$title          = substr( $title, 0, strlen( $title ) - 1 );
								$titleSlug      = strtolower( trim( preg_replace( '/[^\w]+/i', '-', $title ) ) );
								$attr_parent_id = $matchTitle[2];
							}

							$attr   = array();
							$images = array();
							preg_match_all( $regId, $match_variations[ $i ], $matchId );

							if ( count( $matchId ) == 3 ) {
								foreach ( $matchId[1] as $matchID_k => $matchID_v ) {
									$listAttributesNames[ $matchID_v ] = $title;
									$listAttributesIds[ $matchID_v ]   = $attr_parent_id;
									$listAttributesSlug[ $matchID_v ]  = $titleSlug;
									preg_match( $reTitle1, $matchId[2][ $matchID_k ], $title1 );

									if ( count( $title1 ) == 2 ) {
										$attr[ $matchID_v ]           = $title1[1];
										$listAttributes[ $matchID_v ] = $title1[1];
									} else {
										$end                          = strlen( $matchId[2][ $matchID_k ] ) - 13;
										$attr[ $matchID_v ]           = substr( $matchId[2][ $matchID_k ], 6, $end );
										$listAttributes[ $matchID_v ] = $attr[ $matchID_v ];
									}

									preg_match( $reImage, $matchId[2][ $matchID_k ], $image );

									if ( count( $image ) == 2 ) {
										$images[ $matchID_v ]          = $image[1];
										$variationImages[ $matchID_v ] = $image[1];
									}
								}

							}
							$attributes['list_attributes']               = $listAttributes;
							$attributes['list_attributes_names']         = $listAttributesNames;
							$attributes['list_attributes_ids']           = $listAttributesIds;
							$attributes['list_attributes_slugs']         = $listAttributesSlug;
							$attributes['variation_images']              = $variationImages;
							$attributes['attributes'][ $attr_parent_id ] = $attr;
							if ( !empty( $images )  ) {
								$attributes['images'][ $attr_parent_id ] = $images;
							}
							$attributes['parent'][ $attr_parent_id ]             = $title;
							$attributes['attribute_position'][ $attr_parent_id ] = $i;
							$attributes['parent_slug'][ $attr_parent_id ]        = $titleSlug;
						}
					}

					preg_match( $regGallery, $html, $matchGallery );
					if ( count( $matchGallery ) == 2 ) {
						$attributes['gallery'] = vi_wad_json_decode( $matchGallery[1] );
					}

					foreach ($productVariationMaps as $j => $j_item){
						$temp = array(
							'skuId'         => isset( $productVariationMaps[ $j ]['skuIdStr'] ) ? strval( $productVariationMaps[ $j ]['skuIdStr'] ) : strval( $productVariationMaps[ $j ]['skuId'] ),
							'skuPropIds'    => isset( $productVariationMaps[ $j ]['skuPropIds'] ) ? $productVariationMaps[ $j ]['skuPropIds'] : '',
							'skuAttr'       => isset( $productVariationMaps[ $j ]['skuAttr'] ) ? $productVariationMaps[ $j ]['skuAttr'] : '',
							'skuVal'        => $productVariationMaps[ $j ]['skuVal'],
							'image'         => '',
							'variation_ids' => array(),
						);

						if ( $temp['skuPropIds'] ) {
							$temAttr = array();
							$attrIds = explode( ',', $temp['skuPropIds'] );
							foreach ($attrIds as $k => $k_item){
								$temAttr[ $attributes['list_attributes_slugs'][ $attrIds[ $k ] ] ] = $attributes['list_attributes'][ $attrIds[ $k ] ];
							}
							$temp['variation_ids'] = $temAttr;
							$temp['image']         = $attributes['variation_images'][ $attrIds[0] ];
						}
						array_push( $variations, $temp );
					}
					$attributes['variations'] = $variations;
					preg_match( $regName, $html, $matchName );
					if ( count( $matchName ) == 2 ) {
						$attributes['name'] = $matchName[1];
					}
					preg_match( $regCurrencyCode, $html, $matchCurrency );
					if ( count( $matchCurrency ) == 2 ) {
						$attributes['currency_code'] = $matchCurrency[1];
					}
				}
			}
		}
		protected static function prepare_arr_data($html,$ignore_ship_from,$ignore_ship_from_default, &$attributes, &$response){
			$listAttributes             =  $listAttributesDisplayNames = array();
			$listAttributesNames        =  $listAttributesSlug         =  $listAttributesIds          = array();
			$variationImages            =  $variations                 = array();
			if ( ! empty( $html['ae_item_base_info_dto'] ) ) {
				/*Rebuild data from the new product API aliexpress.ds.product.get - since 1.0.10*/
				if ( ! empty( $html['ae_item_base_info_dto']['product_status_type'] ) && $html['ae_item_base_info_dto']['product_status_type'] === 'offline' ) {
					$response['error']  = 1;
					$response['message'] = esc_html__( 'This product is no longer available', 'woocommerce-alidropship' );
					return;
				}
				if ( ! empty( $html['ae_item_base_info_dto']['product_id'] ) ) {
					$attributes['sku'] = $html['ae_item_base_info_dto']['product_id'];
				}
				$attributes['gallery'] = !empty($html['ae_multimedia_info_dto']['image_urls']) ? explode( ';', $html['ae_multimedia_info_dto']['image_urls'] ) : array();
				$skuModule = isset( $html['ae_item_sku_info_dtos'] ['ae_item_sku_info_d_t_o'] ) ? $html['ae_item_sku_info_dtos'] ['ae_item_sku_info_d_t_o'] : ( $html['ae_item_sku_info_dtos'] ??[]);
				if ( is_array( $skuModule ) && !empty($skuModule) ) {
					$productSKUPropertyList = array();
					$propertyValueNames     = array();
					if ( ! empty( $skuModule[0]['ae_sku_property_dtos']['ae_sku_property_d_t_o'] ) ) {
						foreach ($skuModule[0]['ae_sku_property_dtos']['ae_sku_property_d_t_o'] as $i => $i_item){
							$productSKUPropertyList[] = array(
								'id'     => $skuModule[0]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $i ]['sku_property_id'] ?? '',
								'values' => array(),
								'name'   => $skuModule[0]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $i ]['sku_property_name'] ?? '',
							);
						}
						foreach ($skuModule as $i => $i_item){
							foreach ($productSKUPropertyList as $j => $j_item){
								if ( ! in_array( $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['property_value_id'], array_column( $productSKUPropertyList[ $j ]['values'], 'id' ) ) ) {
									$property_value = array(
										'id'        => isset( $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['property_value_id'] ) ? $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['property_value_id'] : '',
										'image'     => isset( $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['sku_image'] ) ? str_replace( array(
											'ae02.alicdn.com',
											'ae03.alicdn.com',
											'ae04.alicdn.com',
											'ae05.alicdn.com',
										), 'ae01.alicdn.com', $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['sku_image'] ) : '',
										'name'      => isset( $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['sku_property_value'] ) ? $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['sku_property_value'] : '',
										'ship_from' => '',
									);
									if ( ! empty( $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['property_value_definition_name'] ) ) {
										$property_value['sub_name'] = $property_value['name'];
										$property_value['name'] = $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['property_value_definition_name'];
									}
									$ship_from = self::property_value_id_to_ship_from( $skuModule[ $i ]['ae_sku_property_dtos']['ae_sku_property_d_t_o'][ $j ]['sku_property_id'], $property_value['id'] );
									if ( $ship_from ) {
										$property_value['ship_from'] = $ship_from;
									}
									$productSKUPropertyList[ $j ]['values'][] = $property_value;
								}
							}
						}
					}
					if ( ! empty( $skuModule[0]['aeop_s_k_u_propertys'] ) ) {
						foreach ($skuModule[0]['aeop_s_k_u_propertys'] as $i_item){
							$productSKUPropertyList[] = array(
								'id'     => $i_item['sku_property_id'] ?? '',
								'values' => array(),
								'name'   => $i_item['sku_property_name'] ?? '',
							);
						}
						foreach ($skuModule as  $i_item){
							foreach ($productSKUPropertyList as $j => $j_item){
								if ( ! in_array( $i_item['aeop_s_k_u_propertys'][ $j ]['property_value_id'], array_column( $productSKUPropertyList[ $j ]['values'], 'id' ) ) ) {
									$property_value = array(
										'id'        => isset( $i_item['aeop_s_k_u_propertys'][ $j ]['property_value_id'] ) ? $i_item['aeop_s_k_u_propertys'][ $j ]['property_value_id'] : '',
										'image'     => isset( $i_item['aeop_s_k_u_propertys'][ $j ]['sku_image'] ) ? str_replace( array(
											'ae02.alicdn.com',
											'ae03.alicdn.com',
											'ae04.alicdn.com',
											'ae05.alicdn.com',
										), 'ae01.alicdn.com', $i_item['aeop_s_k_u_propertys'][ $j ]['sku_image'] ) : '',
										'name'      => isset( $i_item['aeop_s_k_u_propertys'][ $j ]['sku_property_value'] ) ? $i_item['aeop_s_k_u_propertys'][ $j ]['sku_property_value'] : '',
										'ship_from' => '',
									);
									if ( ! empty($i_item['aeop_s_k_u_propertys'][ $j ]['property_value_definition_name'] ) ) {
										$property_value['sub_name'] = $property_value['name'];
										$property_value['name'] =$i_item['aeop_s_k_u_propertys'][ $j ]['property_value_definition_name'];
									}
									$ship_from = self::property_value_id_to_ship_from($i_item['aeop_s_k_u_propertys'][ $j ]['sku_property_id'], $property_value['id'] );
									if ( $ship_from ) {
										$property_value['ship_from'] = $ship_from;
									}
									$productSKUPropertyList[ $j ]['values'][] = $property_value;
								}
							}
						}
					}
					$ignore_ship_from_default_id = '';
					if ( !empty( $productSKUPropertyList ) ) {
						foreach ($productSKUPropertyList as $i => $i_item){
							$images            = array();
							$skuPropertyValues = $i_item['values'];
							$attr_parent_id    = $i_item['id'];
							$skuPropertyName   = wc_sanitize_taxonomy_name( $i_item['name'] );
							if ( ( $attr_parent_id == 200007763 ||strtolower( $skuPropertyName ) === 'ships-from') && $ignore_ship_from ) {
								foreach ( $skuPropertyValues as $value ) {
									if ( isset( $value['ship_from'] ) && $value['ship_from'] === $ignore_ship_from_default ) {
										$ignore_ship_from_default_id = $value['id'];
									}
								}
								if ( $ignore_ship_from_default_id ) {
									continue;
								}
							} //point 1
							$attr = array(
								'values'   => array(),
								'slug'     => $skuPropertyName,
								'name'     => $i_item['name'],
								'position' => $i,
							);
							foreach ($skuPropertyValues as $j_item){
								$skuPropertyValue         = $j_item;
								$org_propertyValueId      = $skuPropertyValue['id'];
								$propertyValueId          = "{$attr_parent_id}:{$org_propertyValueId}";
								$propertyValueDisplayName = $skuPropertyValue['name'];
								$propertyValueName        = $skuPropertyValue['sub_name']??$propertyValueDisplayName ;
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
								$listAttributes[ $propertyValueId ]             = array(
									'name'      => $propertyValueDisplayName,
									'name_sub'  => $propertyValueName,
									'color'     => '',
									'image'     => '',
									'ship_from' => isset( $skuPropertyValue['ship_from'] ) ? $skuPropertyValue['ship_from'] : ''
								);
								if ( isset( $skuPropertyValue['image'] ) && $skuPropertyValue['image'] ) {
									$images[ $propertyValueId ]                  = $skuPropertyValue['image'];
									$variationImages[ $propertyValueId ]         = $skuPropertyValue['image'];
									$listAttributes[ $propertyValueId ]['image'] = $skuPropertyValue['image'];
								}
							}

							$attributes['list_attributes']               = $listAttributes;
							$attributes['list_attributes_names']         = $listAttributesNames;
							$attributes['list_attributes_ids']           = $listAttributesIds;
							$attributes['list_attributes_slugs']         = $listAttributesSlug;
							$attributes['variation_images']              = $variationImages;
							$attributes['attributes'][ $attr_parent_id ] = $attr;
							$attributes['images'][ $attr_parent_id ]     = $images;

							$attributes['parent'][ $attr_parent_id ] = $skuPropertyName;
						}
					}
					foreach ($skuModule as $j => $j_item){
						if(isset($j_item['salable']) && !$j_item['salable']){
							continue;
						}
						$ae_sku_property = isset( $j_item['ae_sku_property_dtos']['ae_sku_property_d_t_o'] ) ? $j_item['ae_sku_property_dtos']['ae_sku_property_d_t_o'] : '';
						if (!$ae_sku_property){
							$ae_sku_property = $j_item['aeop_s_k_u_propertys'] ??'';
						}
						$temp                  = array(
							'skuId'              => $j_item['sku_id']??'',
							'skuAttr'            => ( isset( $skuModule[ $j ]['id'] ) && $skuModule[ $j ]['id'] !== '<none>' ) ? $skuModule[ $j ]['id'] : '',
							'skuPropIds'         => !empty( $ae_sku_property ) ? array_column( $ae_sku_property, 'property_value_id' ) : array(),
							'skuVal'             => array(
								'availQuantity'  => isset( $skuModule[ $j ]['sku_available_stock'] ) ? $skuModule[ $j ]['sku_available_stock'] : ( isset( $skuModule[ $j ]['ipm_sku_stock'] ) ? $skuModule[ $j ]['ipm_sku_stock'] : 0 ),
								'skuCalPrice'    => isset( $skuModule[ $j ]['sku_price'] ) ? $skuModule[ $j ]['sku_price'] : '',
								'actSkuCalPrice' => 0,
							),
							'image'              => '',
							'variation_ids'      => array(),
							'variation_ids_sub'  => array(),
							'variation_ids_slug' => array(),
							'ship_from'          => '',
							'currency_code'      => isset( $skuModule[ $j ]['currency_code'] ) ? $skuModule[ $j ]['currency_code'] : '',
						);
						$s_price               = isset( $skuModule[ $j ]['offer_sale_price'] ) ? self::string_to_float( $skuModule[ $j ]['offer_sale_price'] ) : 0;
						$offer_bulk_sale_price = isset( $skuModule[ $j ]['offer_bulk_sale_price'] ) ? self::string_to_float( $skuModule[ $j ]['offer_bulk_sale_price'] ) : 0;

						if ( $s_price > 0 && $offer_bulk_sale_price > $s_price ) {
							$s_price = $offer_bulk_sale_price;
						}

						$temp['skuVal']['actSkuCalPrice'] = $s_price;

						if ( $temp['skuPropIds'] ) {
							$temAttr        = array();
							$temAttrSub     = array();
							$attrIds        = $temp['skuPropIds'];
							$parent_attrIds = explode( ';', $temp['skuAttr'] );

							if ( $ignore_ship_from_default_id && ! in_array( $ignore_ship_from_default_id, $attrIds ) && $ignore_ship_from ) {
								continue;
							}

							foreach ($attrIds as $k => $k_item){
								$propertyValueId = explode( ':', $parent_attrIds[ $k ] )[0] . ':' . $k_item;
								if ( isset( $listAttributesDisplayNames[ $propertyValueId ] ) ) {
									$temAttr[ $attributes['list_attributes_slugs'][ $propertyValueId ] ] = $listAttributesDisplayNames[ $propertyValueId ];
									$temAttrSub[ $attributes['list_attributes_slugs'][ $propertyValueId ] ] = $propertyValueNames[ $propertyValueId ];
									if ( ! empty( $attributes['variation_images'][ $propertyValueId ] ) ) {
										$temp['image'] = $attributes['variation_images'][ $propertyValueId ];
									}
								}
								if ( ! empty( $listAttributes[ $propertyValueId ]['ship_from'] ) ) {
									$temp['ship_from'] = $listAttributes[ $propertyValueId ]['ship_from'];
								}
							}
							$temp['variation_ids'] = $temAttr;
							$temp['variation_ids_sub'] = $temAttrSub;
						}
						$variations [] = $temp;
					}
					$attributes['variations'] = $variations;
				}
				$attributes['description_url'] = '';
				$attributes['description']     = $html['ae_item_base_info_dto']['detail'] ?? '';
				$attributes['specsModule']     = array();
				$attributes['store_info']    = array(
					'name' => isset( $html['ae_store_info']['store_name'] ) ? $html['ae_store_info']['store_name'] : '',
					'url'  => '',
					'num'  => isset( $html['ae_store_info']['store_id'] ) ? $html['ae_store_info']['store_id'] : '',
				);
				$attributes['name']          = $html['ae_item_base_info_dto']['subject'];
				$attributes['currency_code'] = $html['ae_item_base_info_dto']['currency_code'];
			}
		}
		public static function parse_data(&$attributes, $data, $skip_ship_from_check = false ){
			$result = [ 'error'=> 0, 'message' => '' ];
			if (!is_array($attributes) || empty($attributes)){
				$attributes = [ 'sku' => '' ];
			}
			$settings                   = VI_WOO_ALIDROPSHIP_DATA::get_instance();
			$ignore_ship_from           = $skip_ship_from_check ? false : $settings->get_params( 'ignore_ship_from' );
			$ignore_ship_from_default   = 'CN';
			if (!is_array($data)){
				/*Data passed from chrome extension in JSON format*/
				self::prepare_json_data($data,$ignore_ship_from,$ignore_ship_from_default,$attributes,$result);
			}else{
				self::prepare_arr_data($data,$ignore_ship_from,$ignore_ship_from_default,$attributes,$result);
			}
			return $result;
		}
		public static function error_currency_imported($currency){
			if ( ! VIALD_CLASS_Parse_Ali_Data::is_currency_supported($currency ) ) {
				$error['error']  = 1;
				$error['code']   = 'currency_not_supported';
				if ( in_array( $currency, VI_WOO_ALIDROPSHIP_DATA::get_accept_currencies() ) ) {
					$error['message'] = sprintf( esc_html__( 'Please configure %s/USD rate in the plugin settings/Product price', 'woo-alidropship' ), $currency );//phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				} else {
					$error['message'] = esc_html__( 'Please switch AliExpress currency to USD', 'woo-alidropship' );
				}

				return $error;
			}
			return false;
		}

		/**
		 * Build sku module
		 *
		 * @param $skuModule
		 * @param $ignore_ship_from
		 * @param $ignore_ship_from_default
		 * @param $attributes
		 */
		public static function handle_sku_module( $skuModule, $ignore_ship_from, $ignore_ship_from_default, &$attributes ) {
			if ( is_array( $skuModule ) && !empty( $skuModule ) ) {
				$listAttributes             = array();
				$listAttributesDisplayNames = array();
				$propertyValueNames         = array();
				$listAttributesNames        = array();
				$listAttributesSlug         = array();
				$listAttributesIds          = array();
				$variationImages            = array();
				$variations                 = array();
				$productSKUPropertyList     = array();
				if ( isset( $skuModule['productSKUPropertyList'] ) ) {
					$productSKUPropertyList = $skuModule['productSKUPropertyList'];
				} elseif ( isset( $skuModule['propertyList'] ) ) {
					$productSKUPropertyList = $skuModule['propertyList'];
				}
				$ignore_ship_from_default_id = '';
				if ( is_array( $productSKUPropertyList ) && !empty( $productSKUPropertyList ) ) {
					foreach ($productSKUPropertyList as $i => $i_item){
						$images            = array();
						$skuPropertyValues = $productSKUPropertyList[ $i ]['skuPropertyValues'];
						$attr_parent_id    = $productSKUPropertyList[ $i ]['skuPropertyId'];
						$skuPropertyName   = wc_sanitize_taxonomy_name( $productSKUPropertyList[ $i ]['skuPropertyName'] );
						if ( ($attr_parent_id == 200007763 || strtolower( $skuPropertyName ) === 'ships-from') && $ignore_ship_from ) {
							foreach ( $skuPropertyValues as $value ) {
								if ( $value['skuPropertySendGoodsCountryCode'] === $ignore_ship_from_default ) {
									$ignore_ship_from_default_id = $value['propertyValueId'] ? $value['propertyValueId'] : $value['propertyValueIdLong'];
								}
							}
							if ( $ignore_ship_from_default_id ) {
								continue;
							}
						} //point 1
						$attr = array(
							'values'   => array(),
							'slug'     => $skuPropertyName,
							'name'     => $productSKUPropertyList[ $i ]['skuPropertyName'],
							'position' => $i,
						);
						foreach ($skuPropertyValues as $j => $j_item){
							$skuPropertyValue         = $skuPropertyValues[ $j ];
							$org_propertyValueId      = $skuPropertyValue['propertyValueId'] ? $skuPropertyValue['propertyValueId'] : $skuPropertyValue['propertyValueIdLong'];
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
								'color'     => isset( $skuPropertyValue['skuColorValue'] ) ? $skuPropertyValue['skuColorValue'] : '',
								'image'     => '',
								'ship_from' => isset( $skuPropertyValue['skuPropertySendGoodsCountryCode'] ) ? $skuPropertyValue['skuPropertySendGoodsCountryCode'] : ''
							);
							if ( isset( $skuPropertyValue['skuPropertyImagePath'] ) && $skuPropertyValue['skuPropertyImagePath'] ) {
								$images[ $propertyValueId ]                  = $skuPropertyValue['skuPropertyImagePath'];
								$variationImages[ $propertyValueId ]         = $skuPropertyValue['skuPropertyImagePath'];
								$listAttributes[ $propertyValueId ]['image'] = $skuPropertyValue['skuPropertyImagePath'];
							}
						}

						$attributes['list_attributes']               = $listAttributes;
						$attributes['list_attributes_names']         = $listAttributesNames;
						$attributes['list_attributes_ids']           = $listAttributesIds;
						$attributes['list_attributes_slugs']         = $listAttributesSlug;
						$attributes['variation_images']              = $variationImages;
						$attributes['attributes'][ $attr_parent_id ] = $attr;
						$attributes['images'][ $attr_parent_id ]     = $images;

						$attributes['parent'][ $attr_parent_id ] = $skuPropertyName;
					}
				}

				$skuPriceList = array();
				if ( isset( $skuModule['skuPriceList'] ) ) {
					$skuPriceList = $skuModule['skuPriceList'];
				} elseif ( isset( $skuModule['skuList'] ) ) {
					$skuPriceList = $skuModule['skuList'];
				}

				foreach ($skuPriceList as $j => $j_item){
					if(isset($j_item['salable']) && !$j_item['salable']){
						continue;
					}
					$temp = array(
						'skuId'              => isset( $skuPriceList[ $j ]['skuIdStr'] ) ? strval( $skuPriceList[ $j ]['skuIdStr'] ) : strval( $skuPriceList[ $j ]['skuId'] ),
						'skuAttr'            => isset( $skuPriceList[ $j ]['skuAttr'] ) ? $skuPriceList[ $j ]['skuAttr'] : '',
						'skuPropIds'         => isset( $skuPriceList[ $j ]['skuPropIds'] ) ? $skuPriceList[ $j ]['skuPropIds'] : '',
						'skuVal'             => $skuPriceList[ $j ]['skuVal'],
						'image'              => '',
						'variation_ids'      => array(),
						'variation_ids_sub'  => array(),
						'variation_ids_slug' => array(),
						'ship_from'          => '',
					);
					if ( $temp['skuPropIds'] ) {
						$temAttr        = array();
						$temAttrSub     = array();
						$attrIds        = explode( ',', $temp['skuPropIds'] );
						$parent_attrIds = explode( ';', $temp['skuAttr'] );

						if ( $ignore_ship_from_default_id && ! in_array( $ignore_ship_from_default_id, $attrIds ) && $ignore_ship_from ) {
							continue;
						}

						foreach ($attrIds as $k => $k_item){
							$propertyValueId = explode( ':', $parent_attrIds[ $k ] )[0] . ':' . $k_item;
							if ( isset( $listAttributesDisplayNames[ $propertyValueId ] ) ) {
								$temAttr[ $attributes['list_attributes_slugs'][ $propertyValueId ] ]    = $listAttributesDisplayNames[ $propertyValueId ];
								$temAttrSub[ $attributes['list_attributes_slugs'][ $propertyValueId ] ] = $propertyValueNames[ $propertyValueId ];
								if ( ! empty( $attributes['variation_images'][ $propertyValueId ] ) ) {
									$temp['image'] = $attributes['variation_images'][ $propertyValueId ];
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
				$attributes['variations'] = $variations;
			}
		}
		/**
		 * By default, only support USD
		 *
		 * Since July of 2022, need support RUB as AliExpress does not allow to change currency to USD if language is Russian
		 *
		 * @param $currency
		 *
		 * @return bool
		 */
		public static function is_currency_supported( $currency ) {
			$instance = VI_WOO_ALIDROPSHIP_DATA::get_instance();
			$support  = false;

			if ( $currency === 'USD' ) {
				$support = true;
			} else if ( $currency === get_option( 'woocommerce_currency' ) ) {
				if ( in_array( $currency, $instance::get_accept_currencies() ) && $instance->get_params( 'import_currency_rate' ) ) {
					$support = true;
				}
			} else if ( in_array( $currency, $instance::get_accept_currencies(), true ) && $instance->get_params( "import_currency_rate_{$currency}" ) ) {
				$support = true;
			}

			return $support;
		}
		/**
		 * @param $property_id
		 * @param $property_value_id
		 *
		 * @return string
		 */
		private static function property_value_id_to_ship_from( $property_id, $property_value_id ) {
			$ship_from = '';
			if ( $property_id == 200007763 ) {
				switch ( $property_value_id ) {
					case 203372089:
						$ship_from = 'PL';
						break;
					case 201336100:
					case 201441035:
						$ship_from = 'CN';
						break;
					case 201336103:
						$ship_from = 'RU';
						break;
					case 100015076:
						$ship_from = 'BE';
						break;
					case 201336104:
						$ship_from = 'ES';
						break;
					case 201336342:
						$ship_from = 'FR';
						break;
					case 201336106:
						$ship_from = 'US';
						break;
					case 201336101:
						$ship_from = 'DE';
						break;
					case 203124901:
						$ship_from = 'UA';
						break;
					case 201336105:
						$ship_from = 'UK';
						break;
					case 201336099:
						$ship_from = 'AU';
						break;
					case 203287806:
						$ship_from = 'CZ';
						break;
					case 201336343:
						$ship_from = 'IT';
						break;
					case 203054831:
						$ship_from = 'TR';
						break;
					case 203124902:
						$ship_from = 'AE';
						break;
					case 100015009:
						$ship_from = 'ZA';
						break;
					case 201336102:
						$ship_from = 'ID';
						break;
					case 202724806:
						$ship_from = 'CL';
						break;
					case 203054829:
						$ship_from = 'BR';
						break;
					case 203124900:
						$ship_from = 'VN';
						break;
					case 203124903:
						$ship_from = 'IL';
						break;
					case 100015000:
						$ship_from = 'SA';
						break;
					case 5581:
						$ship_from = 'KR';
						break;
					default:
				}
			}

			return $ship_from;
		}
		private static function string_to_float( $string_number ) {
			return VI_WOO_ALIDROPSHIP_DATA::string_to_float($string_number);
		}
	}
}