<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOOCOMMERCE_ALIDROPSHIP_Frontend_Shipping
 */
class VI_WOO_ALIDROPSHIP_Frontend_Shipping {
	private static $settings;

	public function __construct() {
		self::$settings = VI_WOO_ALIDROPSHIP_DATA::get_instance();
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'fulfill_billing_fields_in_latin_validation' ] );
	}

	public function fulfill_billing_fields_in_latin_validation( $data ) {
		if ( ! self::$settings->get_params( 'fulfill_billing_fields_in_latin' ) ) {
			return;
		}

		if ( empty( $data['ship_to_different_address'] ) ) {
			$f_name = $data['billing_first_name'];
			$l_name = $data['billing_last_name'];
			$city   = $data['billing_city'];

			if ( $f_name && preg_match( '/[^A-Za-z0-9]/im', $f_name ) ) {
				wc_add_notice( esc_html__( 'Require first name in English', 'woo-alidropship' ), 'error', [ 'ald_fname_field' => 'wrong_language' ] );
			}

			if ( $l_name && preg_match( '/[^A-Za-z0-9]/im', $l_name ) ) {
				wc_add_notice( esc_html__( 'Require last name in English', 'woo-alidropship' ), 'error', [ 'ald_lname_field' => 'wrong_language' ] );
			}

			if ( $city && preg_match( '/[^A-Za-z0-9]/im', $city ) ) {
				wc_add_notice( esc_html__( 'Require city in English', 'woo-alidropship' ), 'error', [ 'ald_city_field' => 'wrong_language' ] );
			}
		} else {
			$f_name = $data['shipping_first_name'];
			$l_name = $data['shipping_last_name'];
			$city   = $data['shipping_city'];

			if ( $f_name && preg_match( '/[^A-Za-z0-9]/im', $f_name ) ) {
				wc_add_notice( esc_html__( 'Require shipping first name in English', 'woo-alidropship' ), 'error', [ 'ald_fname_field' => 'wrong_language' ] );
			}
			if ( $l_name && preg_match( '/[^A-Za-z0-9]/im', $l_name ) ) {
				wc_add_notice( esc_html__( 'Require shipping last name in English', 'woo-alidropship' ), 'error', [ 'ald_lname_field' => 'wrong_language' ] );
			}
			if ( $city && preg_match( '/[^A-Za-z0-9]/im', $city ) ) {
				wc_add_notice( esc_html__( 'Require shipping city in English', 'woo-alidropship' ), 'error', [ 'ald_city_field' => 'wrong_language' ] );
			}
		}

	}
}