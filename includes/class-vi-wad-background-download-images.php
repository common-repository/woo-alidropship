<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vi_WAD_Background_Download_Images extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'vi_wad_background_download_images';

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
		$product_id  = isset( $item['woo_product_id'] ) ? $item['woo_product_id'] : '';
		$parent_id   = isset( $item['parent_id'] ) ? $item['parent_id'] : '';
		$set_gallery = isset( $item['set_gallery'] ) ? $item['set_gallery'] : '';
		$product_ids = isset( $item['product_ids'] ) ? $item['product_ids'] : array();
		$src         = isset( $item['src'] ) ? $item['src'] : '';
		try {
			vi_wad_set_time_limit();
			if ( $set_gallery == 2 ) {
				$instance = VI_WOO_ALIDROPSHIP_DATA::get_instance();
				if ( $instance->get_params( 'use_external_image' ) && class_exists( 'EXMAGE_WP_IMAGE_LINKS' ) ) {
					return false;
				}
			}
			$post = get_post( $product_id );
			if ( $post && $post->post_type === 'product' && $src ) {
				$thumb_id = VI_WOO_ALIDROPSHIP_DATA::download_image( $image_id, $src, $product_id );
				if ( $thumb_id && ! is_wp_error( $thumb_id ) ) {
					if ( $set_gallery == 2 ) {
						$downloaded_url = wp_get_attachment_url( $thumb_id );
						$description    = html_entity_decode( $post->post_content, ENT_QUOTES | ENT_XML1, 'UTF-8' );
						$description    = preg_replace( '/[^"]{0,}' . preg_quote( $image_id, '/' ) . '[^"]{0,}/U', $downloaded_url, $description );
						$description    = str_replace( $src, $downloaded_url, $description );
						wp_update_post( array( 'ID' => $product_id, 'post_content' => $description ) );
					} else {
						if ( count( $product_ids ) ) {
							foreach ( $product_ids as $v_id ) {
								$post_type = get_post_type( $v_id );
								if ( $post_type === 'product' ) {
									update_post_meta( $v_id, '_thumbnail_id', $thumb_id );
									if ( $parent_id ) {
										Ali_Product_Table::update_post_meta( $parent_id, '_vi_wad_product_image', $thumb_id );
									}
								} elseif ( $post_type === 'product_variation' ) {
									update_post_meta( $v_id, '_thumbnail_id', $thumb_id );
								}
							}
						}
						if ( 1 == $set_gallery ) {
							$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
							if ( $gallery ) {
								$gallery_array = explode( ',', $gallery );
							} else {
								$gallery_array = array();
							}
							$gallery_array[] = $thumb_id;
							update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_unique( $gallery_array ) ) );
						}
					}
				} else {
					VI_WOO_ALIDROPSHIP_Error_Images_Table::insert( $product_id, implode( ',', $product_ids ), $src, intval( $set_gallery ) );
					error_log( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce error: ' . $thumb_id->get_error_code() . ' - ' . $thumb_id->get_error_message() );
				}
			}

		} catch ( Exception $e ) {
			VI_WOO_ALIDROPSHIP_Error_Images_Table::insert( $product_id, implode( ',', $product_ids ), $src, intval( $set_gallery ) );
			error_log( 'ALD - Dropshipping and Fulfillment for AliExpress and WooCommerce error: ' . $e->getMessage() );

			return false;
		}

		return false;
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
			set_transient( 'vi_wad_background_download_images_complete', time() );
		}
		// Show notice to user or perform some other arbitrary task...
		parent::complete();
	}

	/**
	 * Delete all batches.
	 *
	 * @return Vi_WAD_Background_Download_Images
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