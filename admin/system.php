<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VI_WOO_ALIDROPSHIP_Admin_System
 */
class VI_WOO_ALIDROPSHIP_Admin_System {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu_page' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function admin_enqueue_scripts() {
		global $pagenow;
		$page = isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $pagenow === 'admin.php' && $page === 'woo-alidropship-status' ) {
			wp_enqueue_style( 'woo-alidropship-button', VI_WOO_ALIDROPSHIP_CSS . 'button.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
			wp_enqueue_style( 'woo-alidropship-icon', VI_WOO_ALIDROPSHIP_CSS . 'icon.min.css',[], VI_WOO_ALIDROPSHIP_VERSION  );
		}
	}

	public function page_callback() {
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'System Status', 'woo-alidropship' ) ?></h2>
            <table cellspacing="0" id="status" class="widefat">
                <tbody>
                <tr>
                    <td data-export-label="<?php esc_html_e( 'PHP Time Limit', 'woo-alidropship' ) ?>"><?php esc_html_e( 'PHP Max Execution Time', 'woo-alidropship' ) ?></td>
                    <td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?></td>
                    <td><?php esc_html_e( 'Should be greater than 100', 'woo-alidropship' ) ?></td>
                </tr>
                <tr>
                    <td data-export-label="<?php esc_html_e( 'PHP Max Input Vars', 'woo-alidropship' ) ?>"><?php esc_html_e( 'PHP Max Input Vars', 'woo-alidropship' ) ?></td>

                    <td><?php echo esc_html( ini_get( 'max_input_vars' ) ); ?></td>
                    <td><?php esc_html_e( 'Should be greater than 10000', 'woo-alidropship' ) ?></td>
                </tr>
                <tr>
                    <td data-export-label="<?php esc_html_e( 'Memory Limit', 'woo-alidropship' ) ?>"><?php esc_html_e( 'Memory Limit', 'woo-alidropship' ) ?></td>

                    <td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
                    <td><?php esc_html_e( 'Should be greater than 300MB', 'woo-alidropship' ) ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'WooCommerce AliExpress Dropshipping Extension installed and active', 'woo-alidropship' ) ?></td>
                    <td>
                        <i class="red cancel icon <?php echo esc_attr( VI_WOO_ALIDROPSHIP_DATA::set( 'chrome-extension-active' ) ) ?>"></i>
                        <a target="_blank" href="https://downloads.villatheme.com/?download=alidropship-extension"
                           title="<?php esc_attr_e( 'You have to install the chrome extension to import products from AliExpress', 'woo-alidropship' ) ?>"
                           class="vi-ui positive button labeled icon mini <?php echo esc_attr( VI_WOO_ALIDROPSHIP_DATA::set( 'download-chrome-extension' ) ) ?>"><i
                                    class="external icon"></i><?php esc_html_e( 'Install Extension', 'woo-alidropship' ) ?>
                        </a>
                    </td>
                    <td><?php esc_html_e( '*Required to be able to import AliExpress products', 'woo-alidropship' ) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
		<?php
	}

	/**
	 * Register a custom menu page.
	 */
	public function menu_page() {
		add_submenu_page(
			'woo-alidropship-import-list',
			esc_html__( 'System Status', 'woo-alidropship' ),
			esc_html__( 'System Status', 'woo-alidropship' ),
			'manage_options',
			'woo-alidropship-status',
			array( $this, 'page_callback' )
		);
	}
}
