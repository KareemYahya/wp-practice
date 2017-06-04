<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/admin
 * @author     Pixelgrade <email@example.com>
 */
class PixelgradeCare_Support {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 *
	 * @param      string $plugin_name The name of this plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	function support_setup() {
//		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! $this->_has_active_license() ) {
//			return;
//		}
		wp_enqueue_style( 'galanogrotesquealt', '//pxgcdn.com/fonts/galanogrotesquealt/stylesheet.css' );

		wp_enqueue_style( 'galanoclassic', '//pxgcdn.com/fonts/galanoclassic/stylesheet.css' );

		wp_enqueue_style( 'pixelgrade_care_style', plugin_dir_url( __FILE__ ) . 'css/pixelgrade_care-admin.css', array(), $this->version, 'all' );

		wp_enqueue_script( 'pixelgrade_care_support', plugin_dir_url( __FILE__ ) . 'js/support.js', array(
			'jquery',
			'wp-util',
			'updates'
		), $this->version, true );

		if ( ! wp_script_is('pixelgrade_care-dashboard') ) {
			$this->plugin_name->localize_js_data( 'pixelgrade_care_support' );
		}

		$this->support_content();
	}

	/**
	 * Output the content for the current step.
	 */
	public function support_content() { ?>
		<div id="pixelgrade_care_support_section"></div>
	<?php }

	// Determine if the current user has an active theme license and is allowed to use the support section
	private function _has_active_license() {
		$pixcare_option = get_option( 'pixcare_options' );

		if ( ! isset( $pixcare_option['state'] ) && ! isset( $pixcare_option['state']['licenses'] ) ) {
			return false;
		}

		if ( empty( $pixcare_option['state']['licenses'] ) ) {
			return false;
		}

		if ( empty( $pixcare_option['state']['licenses'][0]['license_hash'] ) ) {
			return false;
		}

		return true;
	}

}
