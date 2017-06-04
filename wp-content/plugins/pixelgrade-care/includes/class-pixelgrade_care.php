<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/includes
 * @author     Pixelgrade <email@example.com>
 */
class PixelgradeCare {
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * The lowest supported WordPress version
	 * @var string
	 */
	protected $wp_support = '4.6';

	protected $theme_support = false;

	private $plugin_admin;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'pixelgrade_care';
		$this->version     = '1.2.1';

		if ( $this->is_wp_compatible() ) {
			$this->load_dependencies();
			$this->set_locale();
			$this->define_admin_hooks();
		} else {
			add_action( 'admin_notices', array( $this, 'add_incompatibility_notice' ) );
		}
	}

	function add_incompatibility_notice() {
		global $wp_version;

		printf(
			'<div class="%1$s"><p><strong>%2$s %3$s %4$s </strong></p><p>%5$s %6$s %7$s</p></div>',
			esc_attr( 'notice notice-error' ),
			esc_html__( "Pixelgrade Themes requires WordPress version", 'pixcare' ),
			$this->wp_support,
			esc_html__( "or later", 'pixcare' ),
			esc_html__( 'You\'re using an old version of WordPress', 'pixcare' ),
			$wp_version,
			esc_html__( 'which is not compatible with the current theme. Please update to the latest version to benefit from all its features.', 'pixcare' )
		);
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - PixelgradeCare_i18n. Defines internationalization functionality.
	 * - PixelgradeCareAdmin. Defines all hooks for the admin area.
	 * - PixelgradeCare_Public. Defines all hooks for the public side of the site.
	 *
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pixelgrade_care-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pixelgrade_care-admin.php';

		/**
		 * Import demo-data system
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pixelgrade_care-starter_content.php';

		/**
		 * The class responsible for defining all actions that occur in the setup wizard.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pixelgrade_care-setup.php';

		/**
		 * The class responsible for defining all actions that occur in the data collection section.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pixelgrade_care-data-collector.php';

		/**
		 * The class responsible for defining all actions that occur in support section.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pixelgrade_care-support.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the PixelgradeCare_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new PixelgradeCare_i18n();

		add_action( 'plugins_loaded', array( $plugin_i18n, 'load_plugin_textdomain' ) );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	public function define_admin_hooks() {
		$this->starter_content = new PixelgradeCareStarterContent( $this->get_plugin_name(), $this->get_version() );
		$this->plugin_admin = new PixelgradeCareAdmin( $this->get_plugin_name(), $this->get_version() );

		add_action( 'admin_menu', array( $this->plugin_admin, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this->plugin_admin, 'settings_init' ) );
		add_action( 'after_setup_theme', array( $this->plugin_admin, 'init_knowledgeBase_categories' ) );

		add_action( 'current_screen', array( $this->plugin_admin, 'add_tabs' ) );

		add_filter( 'pre_set_site_transient_update_themes', array( $this->plugin_admin, 'transient_update_theme_version' ), 11 );
		add_filter( 'pre_set_site_transient_update_themes', array( $this->plugin_admin, 'transient_update_remote_config' ), 12 );
		add_filter( 'pre_set_site_transient_update_themes', array( $this->plugin_admin, 'transient_update_kb_categories' ), 13 );
		add_filter( 'pre_set_site_transient_update_themes', array( $this->plugin_admin, 'transient_delete_oauth_token' ), 14 );
		add_filter( 'pre_set_site_transient_update_themes', array( $this->plugin_admin, 'transient_update_license_data' ), 15 );

		add_action( 'admin_enqueue_scripts', array( $this->plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->plugin_admin, 'enqueue_scripts' ) );

		$plugin_support = new PixelgradeCare_Support( $this->plugin_admin, $this->get_version() );

		add_action( 'admin_footer', array( $plugin_support, 'support_setup' ) );

		$plugin_setup_wizard = new PixelgradeCareSetupWizard( $this->plugin_admin, $this->get_version() );

		add_action( 'current_screen', array( $plugin_setup_wizard, 'add_tabs' ) );
		add_action( 'admin_menu', array( $plugin_setup_wizard, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $plugin_setup_wizard, 'setup_wizard' ) );

		$data_collect_value = get_option( 'pixcare_options' );

		if ( isset( $data_collect_value['allow_data_collect'] ) && $data_collect_value['allow_data_collect'] == true ) {
			add_action( 'admin_init', array( $this, 'init_colector' ), 9 );
		}
	}

	function init_colector() {
		$plugin_data_collector = new PixelgradeCare_DataCollector( $this->get_plugin_name(), $this->get_version(), $this->plugin_admin->get_theme_support() );
		// hook to data options that will send data to wupdates
		add_filter( 'wupdates_call_data_request', array(
			$plugin_data_collector,
			'filter_wupdates_data_response'
		), 11, 2 );
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	public function get_theme_config() {
		$this->plugin_admin->get_remote_config();
	}

	function is_wp_compatible(){
		global $wp_version;

		if ( version_compare( $wp_version, $this->wp_support, '>=' )  ) {
			return true;
		}

		return false;
	}
}
