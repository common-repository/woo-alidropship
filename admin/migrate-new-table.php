<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_ALIDROPSHIP_Admin_Migrate_New_Table {
	public static $migrate_process;

	public function __construct() {
		add_action( 'init', [ $this, 'background_process' ] );
		add_action( 'wp_ajax_ald_migrate_to_new_table', array( $this, 'migrate_to_new_table' ) );
		add_action( 'wp_ajax_ald_migrate_remove_old_data', array( $this, 'remove_old_data' ) );
	}

	public function migrate_to_new_table() {
		check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
		Ali_Product_Table::create_table();
		$migrate_process = new VI_WOO_ALIDROPSHIP_BACKGROUND_MIGRATE_NEW_TABLE();

		if ( $migrate_process->is_queue_empty() && ! $migrate_process->is_process_running() ) {
			$migrate_process->push_to_queue( [ 'step' => 'move' ] );
			$migrate_process->save()->dispatch();
		}

		wp_send_json_success( esc_html__( 'Migration progress has started running in the background.', 'woo-alidropship' ) );
	}

	public function remove_old_data() {
		check_ajax_referer( 'woo_alidropship_admin_ajax', '_vi_wad_ajax_nonce' );
		$migrate_process = new VI_WOO_ALIDROPSHIP_BACKGROUND_MIGRATE_NEW_TABLE();

		if ( $migrate_process->is_queue_empty() && ! $migrate_process->is_process_running() ) {
			$migrate_process->push_to_queue( [ 'step' => 'delete' ] );
			$migrate_process->save()->dispatch();
		}

		wp_send_json_success( esc_html__( 'Deletion progress has started running in the background.', 'woo-alidropship' ) );
	}

	public function background_process() {
		self::$migrate_process = new VI_WOO_ALIDROPSHIP_BACKGROUND_MIGRATE_NEW_TABLE();
		if ( ! self::$migrate_process->is_queue_empty() || self::$migrate_process->is_process_running() ) {
//			error_log( print_r( $migrate_process, true ) );
		}
	}

	public static function migrate_process() {
		return self::$migrate_process;
	}
}

