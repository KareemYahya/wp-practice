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
class PixelgradeCareSetupWizard {

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


	/**
	 * Add Contextual help tabs.
	 */
	public function add_tabs() {
		$screen = get_current_screen();

		$screen->add_help_tab( array(
			'id'      => 'pixelgrade_care_setup_wizard_tab',
			'title'   => __( 'Pixelgrade Care Setup', 'pixelgrade_care' ),
			'content' =>
				'<h2>' . __( 'Pixelgrade Care Setup', 'pixelgrade_care' ) . '</h2>' .
				'<p><a href="' . admin_url( 'index.php?page=pixelgrade_care-setup-wizard' ) . '" class="button button-primary">' . __( 'Setup Pixelgrade Care', 'pixelgrade_care' ) . '</a></p>'

		) );
	}

	function add_admin_menu() {
		add_submenu_page( null, '', '', 'manage_options', 'pixelgrade_care-setup-wizard', null );
	}

	function setup_wizard() {
		if ( ! $this->is_pixelgrade_care_setup_wizard() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style( 'galanogrotesquealt', '//pxgcdn.com/fonts/galanogrotesquealt/stylesheet.css' );
		wp_enqueue_style( 'galanoclassic', '//pxgcdn.com/fonts/galanoclassic/stylesheet.css' );

		wp_enqueue_style( 'pixelgrade_care_style', plugin_dir_url( __FILE__ ) . 'css/pixelgrade_care-admin.css', array(), $this->version, 'all' );

		wp_enqueue_script( 'pixelgrade_care_setup_wizard', plugin_dir_url( __FILE__ ) . 'js/setup_wizard.js', array(
			'jquery',
			'wp-util',
			'updates'
		), $this->version, true );

		$this->plugin_name->localize_js_data( 'pixelgrade_care_setup_wizard' );

		update_option( 'pixelgrade_care_version', $this->version );
		// Delete redirect transient
		$this->delete_redirect_transient();

		ob_start();
		$this->setup_wizard_header();
		$this->setup_wizard_content();
		$this->setup_wizard_footer();
		exit;
	}

	/**
	 * Setup Wizard Header.
	 */
	public function setup_wizard_header() {
		global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow,
		       $update_title, $total_update_count, $parent_file;

		if ( empty( $current_screen ) ) {
			set_current_screen();
		} ?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width"/>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<title><?php _e( 'Pixelgrade Care &rsaquo; Setup Wizard', 'pixelgrade_care' ); ?></title>
			<script type="text/javascript">
				var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
					pagenow = 'plugins';
			</script>
		</head>
		<body class="pixelgrade_care-setup wp-core-ui">

		<?php
	}

	/**
	 * Output the content for the current step.
	 */
	public function setup_wizard_content() { ?>
		<div class="pixelgrade_care-wrapper">
			<div id="pixelgrade_care_setup_wizard"></div>
			<div id="valdationError"></div>
		</div>
	<?php }

	public function setup_wizard_footer() { ?>
		<?php
		wp_print_scripts( 'pixelgrade_care_wizard' );
		wp_print_footer_scripts();
		wp_print_update_row_templates();
		wp_print_admin_notice_templates(); ?>
		</body>
		</html>
		<?php
	}

	/** === HELPERS=== */

	function is_pixelgrade_care_setup_wizard() {
		if ( ! empty( $_GET['page'] ) && 'pixelgrade_care-setup-wizard' === $_GET['page'] ) {
			return true;
		}

		return false;
	}

	public function delete_redirect_transient() {
		$delete_transient = delete_site_transient( '_pixcare_activation_redirect' );

		return $delete_transient;
	}
}
