<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Vi_WAD_Background_Import_Product' ) ) {
	class Vi_WAD_Background_Import_Product extends WP_Background_Process {
		/**
		 * @var string
		 */
		protected $action = 'vi_wad_import_product';

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param mixed $item Queue item to iterate over
		 *
		 * @return mixed
		 */
		protected function task( $item ) {
			$ali_product_id = isset( $item['ali_product_id'] ) ? $item['ali_product_id'] : '';
			$parent_id      = isset( $item['parent_id'] ) ? $item['parent_id'] : '';
			try {
				vi_wad_set_time_limit();
				$settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();
				if ( ! $parent_id ) {
					return false;
				}
				$image              = isset( $item['image'] ) ? $item['image'] : '';
				$categories         = isset( $item['categories'] ) ? $item['categories'] : array();
				$title              = isset( $item['title'] ) ? $item['title'] : '';
				$sku                = isset( $item['sku'] ) ? $item['sku'] : '';
				$status             = isset( $item['status'] ) ? $item['status'] : 'publish';
				$tags               = isset( $item['tags'] ) ? $item['tags'] : array();
				$description        = isset( $item['description'] ) ? $item['description'] : '';
				$variations         = isset( $item['variations'] ) ? $item['variations'] : array();
				$gallery            = isset( $item['gallery'] ) ? $item['gallery'] : array();
				$attributes         = isset( $item['attributes'] ) ? $item['attributes'] : array();
				$catalog_visibility = isset( $item['catalog_visibility'] ) ? $item['catalog_visibility'] : 'visible';
				$default_attr       = isset( $item['variation_default'] ) ? $item['variation_default'] : '';
				if ( is_array( $attributes ) && count( $attributes ) ) {
					$attr_data         = array();
					$position          = 0;
					$variation_visible = $settings->get_params( 'variation_visible' );
					foreach ( $attributes as $key => $attr ) {
						$attr_data[ strtolower( $attr['slug'] ) ] = array(
							'name'         => VI_WOO_ALIDROPSHIP_DATA::get_attribute_name_by_slug( $attr['slug'] ),
							'value'        => implode( ' | ', $attr['values'] ),
							'position'     => isset( $attr['position'] ) ? $attr['position'] : $position,
							'is_visible'   => $variation_visible ? 1 : '',
							'is_variation' => 1,
							'is_taxonomy'  => '',
						);
						$position ++;
					}

					/*Create data for product*/
					$product_data = array( // Set up the basic post data to insert for our product
						'post_excerpt' => '',
						'post_content' => $description,
						'post_title'   => $title,
						'post_status'  => $status,
						'post_type'    => 'product',
						'meta_input'   => array(
							'_sku'                => VI_WOO_ALIDROPSHIP_DATA::sku_exists( $sku ) ? '' : $sku,
							'_product_attributes' => $attr_data,
							'_visibility'         => 'visible',
							'_default_attributes' => $default_attr,
						)
					);

					$product_id = wp_insert_post( $product_data ); // Insert the post returning the new post id

					if ( ! is_wp_error( $product_id ) ) {
						if ( $parent_id ) {
							Ali_Product_Table::wp_update_post( array(
								'ID'          => $parent_id,
								'post_status' => 'publish'
							) );
							Ali_Product_Table::update_post_meta( $parent_id, '_vi_wad_woo_id', $product_id );
						}

						//			// Set up its categories
						//			wp_set_object_terms( $product_id, $product_data['categories'], 'product_cat' );
						wp_set_object_terms( $product_id, 'variable', 'product_type' ); // Set it to a variable product type
						/*download image gallery*/
						$dispatch = false;
						if ( $image ) {
							$dispatch   = true;
							$image_data = array(
								'woo_product_id' => $product_id,
								'parent_id'      => $parent_id,
								'src'            => $image,
								'product_ids'    => array( $product_id ),
								'set_gallery'    => 0,
							);
							VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->push_to_queue( $image_data );
						}
						if ( is_array( $gallery ) && count( $gallery ) ) {
							$dispatch = true;
							foreach ( $gallery as $image_url ) {
								$image_data = array(
									'woo_product_id' => $product_id,
									'parent_id'      => $parent_id,
									'src'            => $image_url,
									'product_ids'    => array(),
									'set_gallery'    => 1,
								);
								VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->push_to_queue( $image_data );
							}
						}
						/*Set product tag*/
						if ( is_array( $tags ) && count( $tags ) ) {
							wp_set_object_terms( $product_id, $tags, 'product_tag' );
						}
						/*Set product categories*/
						if ( is_array( $categories ) && count( $categories ) ) {
							wp_set_post_terms( $product_id, $categories, 'product_cat', true );
						}
						update_post_meta( $product_id, '_vi_wad_aliexpress_product_id', $ali_product_id );
						/*Create product variation*/
						$this->import_product_variation( $product_id, $item, $dispatch );
						vi_wad_set_catalog_visibility( $product_id, $catalog_visibility );
					}
				} else {
					/*Create data for product*/
					$manage_stock = $settings->get_params( 'manage_stock' );
					$manage_stock = $manage_stock ? 'yes' : 'no';

					$sale_price    = isset( $variations[0]['sale_price'] ) ? floatval( $variations[0]['sale_price'] ) : '';
					$regular_price = isset( $variations[0]['regular_price'] ) ? floatval( $variations[0]['regular_price'] ) : '';
					$product_data  = array( // Set up the basic post data to insert for our product
						'post_excerpt' => '',
						'post_content' => $description,
						'post_title'   => $title,
						'post_status'  => $status,
						'post_type'    => 'product',
						'meta_input'   => array(
							'_sku'            => wc_product_generate_unique_sku( 0, $sku ),
							'_visibility'     => 'visible',
							'_regular_price'  => $regular_price,
							'_price'          => $regular_price,
							'_manage_stock'   => $manage_stock,
							'_stock_status'   => 'instock',
							'_stock'          => isset( $variations[0]['stock'] ) ? absint( $variations[0]['stock'] ) : 0,
						)
					);
					if ( $sale_price ) {
						$product_data['meta_input']['_sale_price'] = $sale_price;
						$product_data['meta_input']['_price']      = $sale_price;
					}
					$product_id = wp_insert_post( $product_data ); // Insert the post returning the new post id

					if ( ! is_wp_error( $product_id ) ) {
						if ( $parent_id ) {
							Ali_Product_Table::wp_update_post( array(
								'ID'          => $parent_id,
								'post_status' => 'publish'
							) );
							Ali_Product_Table::update_post_meta( $parent_id, '_vi_wad_woo_id', $product_id );
						}
						//			// Set up its categories
						//			wp_set_object_terms( $product_id, $product_data['categories'], 'product_cat' );
						wp_set_object_terms( $product_id, 'simple', 'product_type' ); // Set it to a variable product type
						/*download image gallery*/
						$dispatch = false;
						if ( $image ) {
							$dispatch   = true;
							$image_data = array(
								'woo_product_id' => $product_id,
								'parent_id'      => $parent_id,
								'src'            => $image,
								'product_ids'    => array( $product_id ),
								'set_gallery'    => 0,
							);
							VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->push_to_queue( $image_data );
						}
						if ( is_array( $gallery ) && count( $gallery ) ) {
							foreach ( $gallery as $image_url ) {
								$dispatch   = true;
								$image_data = array(
									'woo_product_id' => $product_id,
									'parent_id'      => $parent_id,
									'src'            => $image_url,
									'product_ids'    => array(),
									'set_gallery'    => 1,
								);
								VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->push_to_queue( $image_data );
							}
						}
						if ( $dispatch ) {
							VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->save()->dispatch();
						}
						/*Set product tag*/
						if ( is_array( $tags ) && count( $tags ) ) {
							wp_set_post_terms( $product_id, $tags, 'product_tag', true );
						}
						/*Set product categories*/
						if ( is_array( $categories ) && count( $categories ) ) {
							wp_set_post_terms( $product_id, $categories, 'product_cat', true );
						}
						update_post_meta( $product_id, '_vi_wad_aliexpress_product_id', $ali_product_id );
						vi_wad_set_catalog_visibility( $product_id, $catalog_visibility );
						$product = wc_get_product( $product_id );
						if ( $product ) {
							$product->save();
						}
					}
				}

				return false;
			} catch ( Exception $e ) {
				Ali_Product_Table::wp_update_post( array(
					'ID'          => $parent_id,
					'post_status' => 'draft'
				) );

				return false;
			}
		}

		public static function import_product_variation( $product_variable_id, $item, $dispatch ) {
			$product = wc_get_product( $product_variable_id );
			if ( $product ) {
				if ( is_array( $item['variations'] ) && count( $item['variations'] ) ) {
					$variation_ids = array();
					if ( count( $item['variation_images'] ) ) {
						foreach ( $item['variation_images'] as $key => $val ) {
							$variation_ids[ $key ] = array();
						}
					}

					$settings       = VI_WOO_ALIDROPSHIP_DATA::get_instance();
					$manage_stock = $settings->get_params( 'manage_stock' );
					$manage_stock = $manage_stock ? 'yes' : 'no';

					foreach ( $item['variations'] as $product_variation ) {
						$stock_quantity = isset( $product_variation['stock'] ) ? absint( $product_variation['stock'] ) : 0;
						$variation      = new WC_Product_Variation();
						$variation->set_parent_id( $product_variable_id );
						$variation->set_attributes( $product_variation['attributes'] );
						/*Set metabox for variation . Check field name at woocommerce/includes/class-wc-ajax.php*/
						$fields = array(
							'sku'            => wc_product_generate_unique_sku( 0, $product_variation['sku'] ),
							'regular_price'  => $product_variation['regular_price'],
							'price'          => $product_variation['regular_price'],
							'manage_stock'   => $manage_stock,
							'stock_status'   => 'instock',
							'stock_quantity' => $stock_quantity,
						);
						if ( isset( $product_variation['sale_price'] ) && $product_variation['sale_price'] && $product_variation['sale_price'] < $product_variation['regular_price'] ) {
							$fields['sale_price'] = $product_variation['sale_price'];
							$fields['price']      = $product_variation['sale_price'];
						}
						foreach ( $fields as $field => $value ) {
							$variation->{"set_$field"}( wc_clean( $value ) );
						}
						do_action( 'product_variation_linked', $variation->save() );
						update_post_meta( $variation->get_id(), '_vi_wad_aliexpress_variation_id', $product_variation['skuId'] );
						update_post_meta( $variation->get_id(), '_vi_wad_aliexpress_variation_attr', $product_variation['skuAttr'] );
						if ( $product_variation['image'] ) {
							$pos = array_search( $product_variation['image'], $item['variation_images'] );
							if ( $pos !== false ) {
								$variation_ids[ $pos ][] = $variation->get_id();
							}
						}
					}
					if ( count( $variation_ids ) ) {
						foreach ( $variation_ids as $key => $values ) {
							if ( count( $values ) && ! empty( $item['variation_images'][ $key ] ) ) {
								$dispatch   = true;
								$image_data = array(
									'woo_product_id' => $product_variable_id,
									'parent_id'      => '',
									'src'            => $item['variation_images'][ $key ],
									'product_ids'    => $values,
									'set_gallery'    => 0,
								);
								VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->push_to_queue( $image_data );
							}
						}
					}
				}

				$data_store = $product->get_data_store();
				$data_store->sort_all_product_variations( $product->get_id() );
			}
			if ( $dispatch ) {
				VI_WOO_ALIDROPSHIP_Admin_Import_List::$process_image->save()->dispatch();
			}
		}

		/**
		 * Is the updater running?
		 *
		 * @return boolean
		 */
		public function is_process_running() {
			return parent::is_process_running();
		}

		/**
		 * Is the queue empty
		 *
		 * @return boolean
		 */
		public function is_queue_empty() {
			return parent::is_queue_empty();
		}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {
			if ( $this->is_queue_empty() && ! $this->is_process_running() ) {
				set_transient( 'vi_wad_background_import_product', time() );
			}
			// Show notice to user or perform some other arbitrary task...
			parent::complete();
		}

		/**
		 * Delete all batches.
		 *
		 * @return Vi_WAD_Background_Import_Product
		 */
		public function delete_all_batches() {
			global $wpdb;

			$table  = $wpdb->options;
			$column = 'option_name';

			if ( is_multisite() ) {
				$table  = $wpdb->sitemeta;
				$column = 'meta_key';
			}

			$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

			return $this;
		}

		/**
		 * Kill process.
		 *
		 * Stop processing queue items, clear cronjob and delete all batches.
		 */
		public function kill_process() {
			if ( ! $this->is_queue_empty() ) {
				$this->delete_all_batches();
				wp_clear_scheduled_hook( $this->cron_hook_identifier );
			}
		}
	}
}
