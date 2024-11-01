<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'VI_WOO_ALIDROPSHIP_Ali_Orders_Info_Table' ) ) {
	class VI_WOO_ALIDROPSHIP_Ali_Orders_Info_Table {
		/**
		 * Create table
		 */
		public static function create_table() {
			global $wpdb;
			$table = $wpdb->prefix . 'vi_wad_ali_orders_info';

			$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `order_id` varchar(50) NOT NULL unique,
                             `currency` varchar(20),
                             `order_total` float,
                             PRIMARY KEY  (`id`)
                             )";

			$wpdb->query( $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**Insert data to table
		 *
		 * @param $order_id
		 * @param $currency
		 * @param $order_total
		 *
		 * @return int
		 */

		public static function insert( $order_id, $currency, $order_total ) {
			global $wpdb;
			$table       = $wpdb->prefix . 'vi_wad_ali_orders_info';
			$order_id    = strval( $order_id );
			$currency    = trim( $currency );
			$order_total = floatval( $order_total );
			$result      = self::get_row_by_order_id( $order_id );
			if ( $order_id && $currency && $order_total ) {
				$sql    = "INSERT INTO {$table} (`order_id`,`currency`,`order_total`) VALUES(%s,%s,%f) ON DUPLICATE KEY UPDATE currency=%s, order_total=%f";
				$return = $wpdb->query( $wpdb->prepare( $sql ,array(// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
					$order_id,
					$currency,
					$order_total,
					$currency,
					$order_total
				)) );
				$return = strval( $return );
			} else {
				if ( ! empty( $result['id'] ) ) {
					if ( $order_total ) {
						$update_fields = array( 'order_total' => $order_total );
						if ( $currency ) {
							$update_fields['currency'] = $currency;
						}
						$wpdb->update( $table,// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
							$update_fields,
							array(
								'id' => $result['id'],
							)
						);
					}

					$return = $result['id'];
				} else {
					$wpdb->insert( $table,// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						array(
							'order_id'    => $order_id,
							'currency'    => $currency,
							'order_total' => $order_total,
						),
						array(
							'%s',
							'%s',
							'%f',
						)
					);
					$return = $wpdb->insert_id;
				}
			}


			return $return;
		}

		/**Delete row
		 *
		 * @param $id
		 *
		 * @return false|int
		 */
		public static function delete( $id ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'vi_wad_ali_orders_info';
			$delete = $wpdb->delete( $table,// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				array(
					'id' => $id,
				),
				array(
					'%d',
				)
			);

			return $delete;
		}

		/**Get row
		 *
		 * @param $id
		 *
		 * @return array|null|object
		 */
		public static function get_row( $id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'vi_wad_ali_orders_info';
			$query = "SELECT * FROM {$table} WHERE id=%d";

			return $wpdb->get_results( $wpdb->prepare( $query, $id ), ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**Get row by id
		 *
		 * @param $order_id
		 *
		 * @return array|null|object
		 */
		public static function get_row_by_order_id( $order_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'vi_wad_ali_orders_info';
			$query = "SELECT * FROM {$table} WHERE order_id=%f";

			return $wpdb->get_results( $wpdb->prepare( $query, $order_id ), ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**Get rows
		 *
		 * @param int $limit
		 * @param int $offset
		 * @param bool $count
		 *
		 * @return array|null|object|string
		 */
		public static function get_rows( $limit = 0, $offset = 0, $count = false ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'vi_wad_ali_orders_info';
			$select = '*';
			if ( $count ) {
				$select = 'count(*)';
				$query  = "SELECT {$select} FROM {$table}";

				return $wpdb->get_var( $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$select} FROM {$table}";
				if ( $limit ) {
					$query .= " LIMIT {$offset},{$limit}";
				}

				return $wpdb->get_results( $query, ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
	}
}
