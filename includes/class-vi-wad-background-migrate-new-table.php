<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_ALIDROPSHIP_BACKGROUND_MIGRATE_NEW_TABLE extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'vi_wad_background_migrate_new_table';
	protected $page = 0;
	protected $step = '';
	protected $continue = false;

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
		if ( ! empty( $item ) ) {
			$step        = $item['step'];
			$numberposts = WP_DEBUG ? 1 : 30;
			$this->step  = $step;

			switch ( $step ) {
				case 'move':
					$posts = get_posts( [
						'post_type'   => 'vi_wad_draft_product',
						'numberposts' => $numberposts,
						'orderby'     => 'ID',
						'order'       => 'ASC',
						'post_status' => 'any',
						'meta_query'  => [// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							[
								'key'     => '_ald_migrated_to_new_table',
								'compare' => 'NOT EXISTS',
							]
						]
					] );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $post ) {
							$post_id = $post->ID;

							$clone       = (array) $post;
							$clone['ID'] = 0;
							$meta        = get_post_meta( $post_id );

							if ( ! empty( $meta ) ) {
								foreach ( $meta as $m_key => $m ) {
									if ( ! empty( $m[0] ) ) {
										$clone['meta_input'][ $m_key ] = maybe_unserialize( $m[0] );
									}
								}
							}

							$new_id = Ali_Product_Table::insert_post( $clone );

							if ( $new_id ) {
								update_post_meta( $post_id, '_ald_migrated_to_new_table', $new_id );
								$this->log( "Success: $post_id to $new_id" );
							} else {
								$this->log( 'Error: ' . $post_id );
							}
						}

						$this->continue = true;
					}

					break;

				case 'delete':
					$posts = get_posts( [
						'post_type'   => 'vi_wad_draft_product',
						'numberposts' => $numberposts,
						'post_status' => 'any',
					] );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $post ) {
							Ali_Product_Table::wp_delete_post( $post->ID, true );
						}
						$this->continue = true;
					}

					break;
			}
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
		// Show notice to user or perform some other arbitrary task...
		parent::complete();

		if ( $this->is_queue_empty() && ! $this->is_process_running() ) {
			if ( $this->continue ) {
				$this->push_to_queue( [ 'step' => $this->step ] );
				$this->save()->dispatch();
			} else {
				switch ( $this->step ) {
					case 'move':
						update_option( 'ald_migrated_to_new_table', true );

						$settings              = get_option( 'wooaliexpressdropship_params' );
						$settings['ald_table'] = 1;
						update_option( 'wooaliexpressdropship_params', $settings );
						break;

					case 'delete':
						update_option( 'ald_deleted_old_posts_data', true );
						break;
				}
			}
		}
	}

	/**
	 * Delete all batches.
	 *
	 * @return VI_WOOCOMMERCE_ALIDROPSHIP_BACKGROUND_MIGRATE_NEW_TABLE
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

	protected function log( $content ) {
		wc_get_logger()->info( $content, [ 'source' => 'ALD-migrate-product' ] );
	}
}