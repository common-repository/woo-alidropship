<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOOCOMMERCE_ALIDROPSHIP_Admin_Find_Product
 */
class VI_WOO_ALIDROPSHIP_Admin_Find_Product {
	protected $api_url = 'https://api-sg.aliexpress.com/sync';
	protected $aff_app_key = 33737600;
	protected $per_page = 50;
	protected $mce_init = [];
	protected $qt_init = [];

	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ), 11 );
		add_action( 'wp_ajax_vi_wad_add_to_import_list', array( $this, 'ajax_add_to_import_list' ) );
		add_action( 'wp_ajax_ald_search_product', array( $this, 'ajax_search_product' ) );
	}

	public function screen_options_page() {
		add_screen_option( 'per_page', array(
			'label'   => esc_html__( 'Number of items per page', 'wp-admin' ),
			'default' => 5,
			'option'  => 'vi_wad_per_page'
		) );
	}

	public function admin_init() {
		if ( ! empty( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'ald_find_product' ) && ! empty( $_GET['_wp_http_referer'] ) ) {
			$referer = $_GET['_wp_http_referer'];
			unset( $_GET['_wpnonce'] );
			unset( $_GET['_wp_http_referer'] );

			$referer = add_query_arg( [ 'paged' => 1 ], $referer );

			$url = add_query_arg( $_GET, $referer );
			wp_safe_redirect( $url );
			exit;
		}
	}

	public function base_params( $args ) {

		$params = wp_parse_args( $args, array(
			'format'      => 'json',
			'sign_method' => 'sha256',
		) );

		return $params;
	}

	public function format_price_currency( $args ) {
		$args['currency'] = 'USD';

		return $args;
	}

	private static function get_categories() {
		$categories      = [];
		$categories_file = VI_WOO_ALIDROPSHIP_PACKAGES . 'categories.json';
		if ( is_file( $categories_file ) ) {
			$categories = vi_wad_json_decode( file_get_contents( $categories_file ) );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return $categories;
	}

	private function search_product( $keyword, $category, $country, $paged, $sort ) {
		if ( ! $keyword ) {
			return [];
		}

		$result = [];
		$args   = [
			'keywords'        => $keyword,
			'ship_to_country' => $country,
			'page_size'       => $this->per_page,
			'category_ids'    => $category,
			'page_no'         => $paged,
			'sort'            => $sort,
			'tracking_id'     => 'ald',
		];

		$sign_params = [
			'site_url' => VI_WOO_ALIDROPSHIP_DATA::get_domain_name(),
			'data'     => wp_json_encode( $args )
		];

		$sign_params['app_key'] = $this->aff_app_key;

		$sign_response = VI_WOO_ALIDROPSHIP_DATA::ali_ds_get_sign( $sign_params, 'search_product' );

		if ( $sign_response['status'] == 'error' ) {
			return $result;
		}

		$public_params              = $this->base_params( [ 'app_key' => $this->aff_app_key, 'method' => 'aliexpress.affiliate.product.query' ] );
		$public_params['timestamp'] = $sign_response['data']['timestamp'];
		$public_params['sign']      = $sign_response['data']['data'];

		$response = $this->ali_request( $public_params, $args );

		if ( ! empty( $response['aliexpress_affiliate_product_query_response']['resp_result']['result'] ) ) {
			$result = $response['aliexpress_affiliate_product_query_response']['resp_result']['result'];
		}

		return $result;
	}

	private function ali_request( $params, $body = [] ) {
		try {
			$url     = add_query_arg( array_map( 'urlencode', $params ), $this->api_url );
			$request = wp_remote_post( $url, array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'headers'    => array(
					'Content-Type' => 'text/plain;charset=UTF-8',
				),
				'body'       => $body,
				'timeout'    => 60,
			) );

			if ( ! is_wp_error( $request ) ) {
				$body = wp_remote_retrieve_body( $request );

				return json_decode( $body, true );
			} else {
				return false;
			}
		} catch ( \Exception $e ) {
			return false;
		}
	}

	private static function define_countries() {
		return [
			"AF"    => "Afghanistan",
			"ALA"   => "Aland Islands",
			"AL"    => "Albania",
			"GBA"   => "Alderney",
			"DZ"    => "Algeria",
			"AS"    => "American Samoa",
			"AD"    => "Andorra",
			"AO"    => "Angola",
			"AI"    => "Anguilla",
			"AG"    => "Antigua and Barbuda",
			"AR"    => "Argentina",
			"AM"    => "Armenia",
			"AW"    => "Aruba",
			"ASC"   => "Ascension Island",
			"AU"    => "Australia",
			"AT"    => "Austria",
			"AZ"    => "Azerbaijan",
			"BS"    => "Bahamas",
			"BH"    => "Bahrain",
			"BD"    => "Bangladesh",
			"BB"    => "Barbados",
			"BY"    => "Belarus",
			"BE"    => "Belgium",
			"BZ"    => "Belize",
			"BJ"    => "Benin",
			"BM"    => "Bermuda",
			"BT"    => "Bhutan",
			"BO"    => "Bolivia",
			"BA"    => "Bosnia and Herzegovina",
			"BW"    => "Botswana",
			"BR"    => "Brazil",
			"VG"    => "Virgin Islands (British)",
			"BN"    => "Brunei",
			"BG"    => "Bulgaria",
			"BF"    => "Burkina Faso",
			"BI"    => "Burundi",
			"KH"    => "Cambodia",
			"CM"    => "Cameroon",
			"CA"    => "Canada",
			"CV"    => "Cape Verde",
			"BQ"    => "Caribbean Netherlands",
			"KY"    => "Cayman Islands",
			"CF"    => "Central African Republic",
			"TD"    => "Chad",
			"CL"    => "Chile",
			"CX"    => "Christmas Island",
			"CC"    => "Cocos (Keeling) Islands",
			"CO"    => "Colombia",
			"KM"    => "Comoros",
			"ZR"    => "Congo, The Democratic Republic Of The",
			"CK"    => "Cook Islands",
			"CR"    => "Costa Rica",
			"CI"    => "Cote D'Ivoire",
			"HR"    => "Croatia (local name: Hrvatska)",
			"CW"    => "Curacao",
			"CY"    => "Cyprus",
			"CZ"    => "Czech Republic",
			"DK"    => "Denmark",
			"DJ"    => "Djibouti",
			"DM"    => "Dominica",
			"DO"    => "Dominican Republic",
			"TLS"   => "Timor-Leste",
			"EC"    => "Ecuador",
			"EG"    => "Egypt",
			"SV"    => "El Salvador",
			"GQ"    => "Equatorial Guinea",
			"ER"    => "Eritrea",
			"EE"    => "Estonia",
			"ET"    => "Ethiopia",
			"FK"    => "Falkland Islands (Malvinas)",
			"FO"    => "Faroe Islands",
			"FJ"    => "Fiji",
			"FI"    => "Finland",
			"FR"    => "France",
			"PF"    => "French Polynesia",
			"GA"    => "Gabon",
			"GM"    => "Gambia",
			"GE"    => "Georgia",
			"DE"    => "Germany",
			"GH"    => "Ghana",
			"GI"    => "Gibraltar",
			"GR"    => "Greece",
			"GL"    => "Greenland",
			"GD"    => "Grenada",
			"GP"    => "Guadeloupe",
			"GU"    => "Guam",
			"GT"    => "Guatemala",
			"GGY"   => "Guernsey",
			"GN"    => "Guinea",
			"GW"    => "Guinea-Bissau",
			"GY"    => "Guyana",
			"GF"    => "French Guiana",
			"HT"    => "Haiti",
			"HN"    => "Honduras",
			"HK"    => "Hong Kong,China",
			"HU"    => "Hungary",
			"IS"    => "Iceland",
			"IN"    => "India",
			"ID"    => "Indonesia",
			"IQ"    => "Iraq",
			"IE"    => "Ireland",
			"IL"    => "Israel",
			"IT"    => "Italy",
			"JM"    => "Jamaica",
			"JP"    => "Japan",
			"JEY"   => "Jersey",
			"JO"    => "Jordan",
			"KZ"    => "Kazakhstan",
			"KE"    => "Kenya",
			"KI"    => "Kiribati",
			"KR"    => "Korea",
			"KS"    => "Kosovo",
			"KW"    => "Kuwait",
			"KG"    => "Kyrgyzstan",
			"LA"    => "Lao People's Democratic Republic",
			"LV"    => "Latvia",
			"LB"    => "Lebanon",
			"LS"    => "Lesotho",
			"LR"    => "Liberia",
			"LY"    => "Libya",
			"LI"    => "Liechtenstein",
			"LT"    => "Lithuania",
			"LU"    => "Luxembourg",
			"MO"    => "Macau,China",
			"MG"    => "Madagascar",
			"MW"    => "Malawi",
			"MY"    => "Malaysia",
			"MV"    => "Maldives",
			"ML"    => "Mali",
			"MT"    => "Malta",
			"MH"    => "Marshall Islands",
			"MQ"    => "Martinique",
			"MR"    => "Mauritania",
			"MU"    => "Mauritius",
			"YT"    => "Mayotte",
			"MX"    => "Mexico",
			"FM"    => "Micronesia",
			"MC"    => "Monaco",
			"MN"    => "Mongolia",
			"MNE"   => "Montenegro",
			"MS"    => "Montserrat",
			"MA"    => "Morocco",
			"MZ"    => "Mozambique",
			"MM"    => "Myanmar",
			"NA"    => "Namibia",
			"NR"    => "Nauru",
			"NP"    => "Nepal",
			"NL"    => "Netherlands",
			"AN"    => "Netherlands Antilles",
			"NC"    => "New Caledonia",
			"NZ"    => "New Zealand",
			"NI"    => "Nicaragua",
			"NE"    => "Niger",
			"NG"    => "Nigeria",
			"NU"    => "Niue",
			"NF"    => "Norfolk Island",
			"MK"    => "Macedonia",
			"MP"    => "Northern Mariana Islands",
			"NO"    => "Norway",
			"OM"    => "Oman",
			"OTHER" => "Other Country",
			"PK"    => "Pakistan",
			"PW"    => "Palau",
			"PS"    => "Palestine",
			"PA"    => "Panama",
			"PG"    => "Papua New Guinea",
			"PY"    => "Paraguay",
			"PE"    => "Peru",
			"PH"    => "Philippines",
			"PL"    => "Poland",
			"PT"    => "Portugal",
			"PR"    => "Puerto Rico",
			"QA"    => "Qatar",
			"MD"    => "Moldova",
			"RE"    => "Reunion",
			"RO"    => "Romania",
			"RU"    => "Russian Federation",
			"RW"    => "Rwanda",
			"BLM"   => "Saint Barthelemy",
			"KN"    => "Saint Kitts and Nevis",
			"LC"    => "Saint Lucia",
			"MAF"   => "Saint Martin",
			"PM"    => "St. Pierre and Miquelon",
			"VC"    => "Saint Vincent and the Grenadines",
			"WS"    => "Samoa",
			"SM"    => "San Marino",
			"ST"    => "Sao Tome and Principe",
			"SA"    => "Saudi Arabia",
			"SN"    => "Senegal",
			"SRB"   => "Serbia",
			"SC"    => "Seychelles",
			"SL"    => "Sierra Leone",
			"SG"    => "Singapore",
			"SX"    => "Sint Maarten",
			"SK"    => "Slovakia (Slovak Republic)",
			"SI"    => "Slovenia",
			"SB"    => "Solomon Islands",
			"SO"    => "Somalia",
			"ZA"    => "South Africa",
			"SGS"   => "South Georgia and the South Sandwich Islands",
			"SS"    => "South Sudan",
			"ES"    => "Spain",
			"LK"    => "Sri Lanka",
			"SR"    => "Suriname",
			"SZ"    => "Swaziland",
			"SE"    => "Sweden",
			"CH"    => "Switzerland",
			"TW"    => "Taiwan,China",
			"TJ"    => "Tajikistan",
			"TZ"    => "Tanzania",
			"TH"    => "Thailand",
			"CG"    => "Congo, The Republic of Congo",
			"VA"    => "Vatican City State (Holy See)",
			"TG"    => "Togo",
			"TO"    => "Tonga",
			"TT"    => "Trinidad and Tobago",
			"TN"    => "Tunisia",
			"TR"    => "Turkey",
			"TM"    => "Turkmenistan",
			"TC"    => "Turks and Caicos Islands",
			"TV"    => "Tuvalu",
			"VI"    => "Virgin Islands (U.S.)",
			"UG"    => "Uganda",
			"UA"    => "Ukraine",
			"AE"    => "United Arab Emirates",
			"UK"    => "United Kingdom",
			"US"    => "United States",
			"UY"    => "Uruguay",
			"UZ"    => "Uzbekistan",
			"VU"    => "Vanuatu",
			"VE"    => "Venezuela",
			"VN"    => "Vietnam",
			"WF"    => "Wallis And Futuna Islands",
			"YE"    => "Yemen",
			"ZM"    => "Zambia",
			"EAZ"   => "Zanzibar",
			"ZW"    => "Zimbabwe",
		];
	}

	public function ajax_add_to_import_list() {
		$return=['status'=>'error'];
		if (!check_ajax_referer( 'woo_alidropship_admin_ajax', 'nonce' ,false)){
			$return['message'] ='Invalid nonce';
			wp_send_json( $return );
		}
		$product_id = isset($_POST['product_id']) ? wc_clean($_POST['product_id']):'';
		$country = isset($_POST['country']) ? wc_clean($_POST['country']):'';
        if (!$product_id || !$country){
	        $import_error = "Invalid data (product_id: {$product_id}, country: {$country}) to get product";
	        $return['message'] =$import_error;
	        wp_send_json( $return );
        }
        $get_data = VI_WOO_ALIDROPSHIP_DATA::get_data( '', [], 'viwad_init_data_before',false,[
			'product_id' => $product_id,
			'target_currency' => 'USD',
			'ship_to_country' => $country,
			'target_language' => 'en',
			'locale' => 'en_US',
			'domain'=>get_site_url(),
			'action' => 'import',
		] );
		if ( $get_data['status'] !== 'success' ) {
			if ( !empty($get_data['message']) ) {
				$result['message']      = $get_data['message'];
			} else {
				$result['message']      = esc_html__( 'Cannot retrieve data. Please contact us and we will help you detect and handle the problem', 'woo-alidropship' );
			}
			wp_send_json( $result );
		}
		$data = $get_data['data']??[];
		$time = time() ;
		$freight     = !empty($get_data['freight']) ? $get_data['freight'] : '';
		if ($freight){
			$freight = VI_WOO_ALIDROPSHIP_DATA::adjust_ali_freight( $freight,$country ==='RU'? 'api_ru':'api' );
		}
		if (empty($freight)){
			$freight = [];
			$time -= HOUR_IN_SECONDS;
		}
		$shipping_info = array(
			'time'          => $time,
			'country'       => $country,
			'company'       =>'',
			'company_name'  => '',
			'freight'       => $freight,
			'freight_ext'   => '',
			'shipping_cost' => null,
			'delivery_time' => '',
		);
		$import_error = $this->add_to_import_list( $data, $shipping_info );
        if ($import_error){
            $return['message'] =$import_error;
        }else {
	        add_filter( 'tiny_mce_before_init', [ $this, 'get_wp_editor_mceinit' ], 10, 2 );
	        add_filter( 'quicktags_settings', [ $this, 'get_wp_editor_qt' ], 10, 2 );
	        ob_start();
	        VI_WOO_ALIDROPSHIP_Admin_Import_List::import_list_html();
	        $return['import_list'] = ob_get_clean();
	        $return['mce_init']    = $this->mce_init;
	        $return['qt_init']     = $this->qt_init;
            $return['status'] = 'success';
	        $return['message'] = esc_html__( 'Product is added to import list', 'woo-alidropship' );
        }
		wp_send_json( $return );
	}
	public function add_to_import_list( $data, $shipping_info ) {
		$error ='';
		$sku     = $data['sku'] ?? '';
		$post_id = VI_WOO_ALIDROPSHIP_DATA::product_get_id_by_aliexpress_id( $sku );
		if ( ! $post_id ) {
			$post_id = VI_WOO_ALIDROPSHIP_DATA::get_instance()->create_product( $data, $shipping_info );
			if ( is_wp_error( $post_id ) ) {
				$error = $post_id->get_error_message();
			} elseif ( ! $post_id ) {
				$error = esc_html__( 'Cannot create post', 'woo-alidropship' );
			}
		} else {
			$error = esc_html__( 'Product exists', 'woo-alidropship' );
		}
        return $error;
	}
	public function get_wp_editor_mceinit( $mceInit, $editor_id ) {
		$this->mce_init[ $editor_id ] = $this->_parse_init( $mceInit );

		return $mceInit;
	}

	public function get_wp_editor_qt( $qtInit, $editor_id ) {
		$this->qt_init[ $editor_id ] = $this->_parse_init( $qtInit );

		return $qtInit;
	}

	private function _parse_init( $init ) {
		$options = '';

		foreach ( $init as $key => $value ) {
			if ( is_bool( $value ) ) {
				$val     = $value ? 'true' : 'false';
				$options .= $key . ':' . $val . ',';
				continue;
			} elseif ( ! empty( $value ) && is_string( $value ) && (
					( '{' === $value[0] && '}' === $value[ strlen( $value ) - 1 ] ) ||
					( '[' === $value[0] && ']' === $value[ strlen( $value ) - 1 ] ) ||
					preg_match( '/^\(?function ?\(/', $value ) ) ) {

				$options .= $key . ':' . $value . ',';
				continue;
			}
			$options .= $key . ':"' . $value . '",';
		}

		return '{' . trim( $options, ' ,' ) . '}';
	}

	public static function search_form() {
		$ald_categories = self::get_categories();
		$countries      = self::define_countries();
		$shipto         = get_option( 'ald_search_product_country' );

		if ( ! $shipto ) {
			$default_country = get_option( 'woocommerce_default_country' );
			$shipto          = current( explode( ':', $default_country ) );
		}

		$shipto = $shipto == 'GB' ? 'UK' : $shipto;

		?>
        <div id="ald-find-product-modal" class="vi-ui modal large">
            <i class="close icon"> </i>
            <div class="header">
                <div class="ald-header-title">
					<?php esc_html_e( 'Find product to import', 'woo-alidropship' ); ?>
                </div>
                <form class="vi-ui form small ald-search-product-form">

                    <div class="two fields">
                        <div class="field">
                            <div class="vi-ui labeled input right action">
                                <div class="vi-ui label basic">
									<?php esc_html_e( 'Ship to', 'woo-alidropship' ); ?>
                                </div>
                                <select class="vi-ui dropdown search selection fluid ald-ship-to-country" name="ald_country">
									<?php
									foreach ( $countries as $country_code => $country_name ) {
										printf( "<option value='%s' %s>%s</option>", esc_attr( $country_code ), selected( $shipto, $country_code, false ), esc_html( $country_name ) );
									}
									?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <div class="vi-ui labeled input right action">
                                <div class="vi-ui label basic">
									<?php esc_html_e( 'Sort', 'woo-alidropship' ); ?>
                                </div>
                                <select class="vi-ui dropdown fluid ald-search-product-sort" name="ald_sort">
									<?php
									$sort_options = [
										'SALE_PRICE_ASC'   => esc_html__( 'Price low to high', 'woo-alidropship' ),
										'SALE_PRICE_DESC'  => esc_html__( 'Price high to low', 'woo-alidropship' ),
										'LAST_VOLUME_ASC'  => esc_html__( 'Last volume low to high', 'woo-alidropship' ),
										'LAST_VOLUME_DESC' => esc_html__( 'Last volume high to low', 'woo-alidropship' ),
									];
									foreach ( $sort_options as $key => $name ) {
										printf( "<option value='%s'>%s</option>", esc_attr( $key ), esc_html( $name ) );
									}
									?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <div class="vi-ui action input">
                            <input type="text" placeholder="Search..." name="ald_keyword" class="ald-keyword">
                            <select class="vi-ui search selection dropdown" name="ald_category">
                                <option value=" "><?php esc_html_e( 'All categories' ); ?></option>
								<?php
								foreach ( $ald_categories as $ald_category ) {
									if ( isset( $ald_category['parent_category_id'] ) ) {
										continue;
									}
									printf( "<option value='%s' >%s</option>", esc_attr( $ald_category['category_id'] ), esc_html( $ald_category['category_name'] ) );
								}
								?>
                            </select>
                            <button type="submit" class="vi-ui button ald-search-button" name="ald_search" value="search">
								<?php esc_html_e( 'Search', 'woo-alidropship' ); ?>
                            </button>
                        </div>
                        <span class="ald-keyword-error">
							<?php esc_html_e( 'Input keyword to search', 'woo-alidropship' ); ?>
                        </span>
                    </div>
                </form>

            </div>
            <div class="content scrolling ald-search-result">
            </div>
            <div class="actions">
                <div class="ald-pagination-wrapper"></div>
            </div>
        </div>

		<?php
	}

	public function ajax_search_product() {
        if (!check_ajax_referer( 'woo_alidropship_admin_ajax', 'nonce' ,false)){
	        wp_send_json_error( esc_html__( 'Invalid nonce', 'woo-alidropship' ) );
        }


		$default_country = get_option( 'woocommerce_default_country' );
		$default_country = current( explode( ':', $default_country ) );

		$keyword = ! empty( $_POST['ald_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['ald_keyword'] ) ) : '';

		if ( ! $keyword ) {
			wp_send_json_error( esc_html__( 'Keyword is empty', 'woo-alidropship' ) );
		}

		$response = [ 'products' => '', 'pagination' => '' ];
		$category           = ! empty( $_POST['ald_category'] ) ? sanitize_text_field( wp_unslash( $_POST['ald_category'] ) ) : '';
		$country            = ! empty( $_POST['ald_country'] ) ? sanitize_text_field( wp_unslash( $_POST['ald_country'] ) ) : $default_country;
		$paged              = ! empty( $_POST['paged'] ) ? sanitize_text_field( wp_unslash( $_POST['paged'] ) ) : 1;
		$sort               = ! empty( $_POST['ald_sort'] ) ? sanitize_text_field( wp_unslash( $_POST['ald_sort'] ) ) : 'SALE_PRICE_ASC';
		$paged              = $paged > 150 ? 150 : $paged;
		$search_products    = $this->search_product( $keyword, $category, $country, $paged, $sort );
		$products           = $search_products['products']['product'] ?? [];
		$current_page       = $search_products['current_page_no'] ?? 1;
		$total_record_count = $search_products['total_record_count'] ?? 0;

		$total_page = ceil( $total_record_count / $this->per_page );
		$total_page -= $total_page > 2 ? 2 : 0;
		$total_page = $total_page > 150 ? 150 : $total_page;

		update_option( 'ald_search_product_country', $country );

		if ( ! empty( $products ) ) {
			ob_start();
			?>
            <div class="vi-ui four column grid">
				<?php
				foreach ( $products as $product ) {
					$product_id = $product['product_id'];
					$posts      = Ali_Product_Table::get_posts( [
						'post_type'   => 'vi_wad_draft_product',
						'post_status' => 'any',
						'meta_query'  => [// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => '_vi_wad_sku',
								'value'   => $product_id,
								'compare' => '=',
							),
						],
					] );
					$disabled   = ! empty( $posts ) ? ' disabled' : '';

					?>
                    <div class="column">
                        <div class="vi-ui fluid card">
                            <div class="image">
                                <img src="<?php echo esc_url( $product['product_main_image_url'] ) ?>">
                                <div class="ald-product-title">
									<?php
									printf( "<a href='%s' target='_blank' class=''>%s</a>",
										esc_url( $product['product_detail_url'] ), esc_html( $product['product_title'] ) );
									?>
                                </div>
                            </div>
                            <div class="content">
                                <div class="ald-prices-import-button">
                                    <div class="ald-product-prices">
										<?php
										add_filter( 'wc_price_args', [ $this, 'format_price_currency' ] );
										$original_price = $product['target_original_price'] ?? $product['original_price'] ?? '';
										$sale_price     = $product['target_sale_price'] ?? $product['sale_price'] ?? '';
										echo wc_format_sale_price( $original_price, $sale_price );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										remove_filter( 'wc_price_args', [ $this, 'format_price_currency' ] );
										?>
                                    </div>
                                    <a href="<?php echo esc_url( add_query_arg( [ 'aldChangeCountry' => $country ], $product['product_detail_url'] ) ) ?>"
                                       data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                       data-product_title="<?php echo esc_attr( $product['product_title'] ) ?>"
                                       class="vi-ui button icon tiny green ald-add-to-import-list<?php echo esc_attr( $disabled ) ?>"
                                       data-tooltip="<?php echo esc_attr__( 'Import this product', 'woo-alidropship' ) ?>">
                                        <i class="plus icon"> </i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
			$response['products'] = ob_get_clean();
		} else {
			$response['products'] = esc_html__( 'No product found', 'woo-alidropship' );
		}

		if ( $total_record_count && $total_page > 1 ) {
			ob_start();
			?>
            <div class="ald-pagination">
                <div class="vi-ui pagination menu">
					<?php
					for ( $i = 1; $i <= $total_page; $i ++ ) {
						if ( in_array( $i, [ 1, $current_page - 1, $current_page, $current_page + 1, $total_page - 1, $total_page ] ) ) {
							printf( '<a class="item %s"  data-paged="%d">%s</a>', esc_attr( $current_page == $i ? 'active' : '' ), esc_attr( $i ), esc_html( $i ) );
						} else if ( $i == $current_page - 2 && $current_page - 2 > 1 || $i == $current_page + 2 && $current_page + 2 < $total_page ) {
							echo '<a class="item disabled">...</a>';
						}
					}
					?>
                </div>
            </div>
			<?php
			$response['pagination'] = ob_get_clean();
		}

		wp_send_json_success( $response );
	}
}
