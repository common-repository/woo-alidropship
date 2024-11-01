<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Vi_WAD_Background_Download_Description' ) ) {
	class Vi_WAD_Background_Download_Description extends WP_Background_Process {

		/**
		 * @var string
		 */
		protected $action = 'vi_wad_download_description';

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
			try {
				vi_wad_set_time_limit();
				$description_url     = isset( $item['description_url'] ) ? $item['description_url'] : '';
				$product_id          = isset( $item['product_id'] ) ? $item['product_id'] : '';
				$description         = isset( $item['description'] ) ? $item['description'] : '';
				$product_description = isset( $item['product_description'] ) ? $item['product_description'] : '';
				VI_WOO_ALIDROPSHIP_DATA::download_description( $product_id, $description_url, $description, $product_description );

				return false;
			} catch ( Exception $e ) {
				return false;
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
			// Show notice to user or perform some other arbitrary task...
//			add_action('admin_init','');
			parent::complete();
		}

		/**
		 * Delete all batches.
		 *
		 * @return Vi_WAD_Background_Download_Description
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
