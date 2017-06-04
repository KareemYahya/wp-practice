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
class PixelgradeCareAdmin {
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
	 * The config for the active theme.
	 * If this is false it means the current theme doesn't declare support for pixelgrade_care
	 *
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array / boolean    $theme_support
	 */
	private $theme_support;

	private $options;

	private $api_version = 'v2';

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

		add_action( 'admin_init', array( $this, 'check_theme_support' ), 11 );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes_api' ) );
		add_action( 'admin_init', array( $this, 'admin_redirects' ), 15 );
		add_filter( "wupdates_call_data_request", array( $this, "add_wupdates_validation" ) );
	}

	/**
	 * The first access to PixCare needs to be redirected to the setup wizard
	 */
	function admin_redirects() {

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin_version     = get_option( 'pixelgrade_care_version' );
		$redirect_transient = get_site_transient( '_pixcare_activation_redirect' );

		if ( $redirect_transient ) {
			// yay this is a fresh install and we are not on a setup page, just go there already
			wp_safe_redirect( admin_url( 'index.php?page=pixelgrade_care-setup-wizard' ) );
			exit;
		} elseif ( empty( $plugin_version ) ) {
			// yay this is a fresh install and we are not on a setup page, just go there already
			wp_safe_redirect( admin_url( 'index.php?page=pixelgrade_care-setup-wizard' ) );
			exit;
		}
	}

	/**
	 * Pass data to wupdates which should validate our theme
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	function add_wupdates_validation( $data ) {
		$license_hash = get_theme_mod( 'pixcare_license_hash' );
		if ( $license_hash ) {
			$data['license_hash'] = $license_hash;
		}

		return $data;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( $this->is_pixelgrade_care_dashboard() ) {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pixelgrade_care-admin.css', array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( $this->is_pixelgrade_care_dashboard() ) {

			wp_enqueue_style( 'galanogrotesquealt', '//pxgcdn.com/fonts/galanogrotesquealt/stylesheet.css' );
			wp_enqueue_style( 'galanoclassic', '//pxgcdn.com/fonts/galanoclassic/stylesheet.css' );

			wp_enqueue_script( 'updates' );

			wp_enqueue_script( 'pixelgrade_care-dashboard', plugin_dir_url( __FILE__ ) . 'js/dashboard.js', array(
				'jquery',
				'wp-util',
			), $this->version, true );

			$this->localize_js_data();
		}
	}

	function add_rest_routes_api() {
		//The Following registers an api route with multiple parameters.
		register_rest_route( 'pixcare/v1', '/global_state', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_get_state' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		register_rest_route( 'pixcare/v1', '/global_state', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_set_state' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		register_rest_route( 'pixcare/v1', '/data_collect', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_get_data_collect' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		register_rest_route( 'pixcare/v1', '/data_collect', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_set_data_collect' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		// debug tools
		register_rest_route( 'pixcare/v1', '/cleanup', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_cleanup' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		register_rest_route( 'pixcare/v1', '/disconnect_user', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_disconnect_user' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		register_rest_route( 'pixcare/v1', '/update_license', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'rest_update_license' )
		) );
	}


	function permission_nonce_callback() {
		return wp_verify_nonce( $this->get_nonce(), 'pixelgrade_care_rest' );
	}

	private function get_nonce() {
		$nonce = null;

		if ( isset( $_REQUEST['pixcare_nonce'] ) ) {
			$nonce = wp_unslash( $_REQUEST['pixcare_nonce'] );
		} elseif ( isset( $_POST['pixcare_nonce'] ) ) {
			$nonce = wp_unslash( $_POST['pixcare_nonce'] );
		}

		return $nonce;
	}

	private function get_user_meta() {
		$nonce = null;

		if ( isset( $_POST['user'] ) ) {
			$nonce = wp_unslash( $_POST['user'] );
		}

		return $nonce;
	}

	private function get_theme_mod() {
		$nonce = null;

		if ( isset( $_POST['theme_mod'] ) ) {
			$nonce = wp_unslash( $_POST['theme_mod'] );
		}

		return $nonce;
	}

	/**
	 * @TODO Find a use for this
	 */
	function rest_get_state() {
		$display_errors = @ini_set( 'display_errors', 0 );
		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		$pixcare_state = $this->get_option( 'state' );

		@ini_set( 'display_errors', $display_errors );
		wp_send_json_success( $pixcare_state );
	}

	/**
	 * @param $request
	 * Gets the new license and updates it
	 */
	function rest_update_license( $request ) {
		$display_errors = @ini_set( 'display_errors', 0 );

		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		$params   = $request->get_params();

		if ( ! isset( $params['old_license'] ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => 'No old license provided!' ) );
		}

		if ( ! isset( $params['new_license'] ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => 'No new license provided!' ) );
		}

		if ( ! isset( $params['new_license_status'] ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => 'No license status provided!' ) );
		}

		if ( ! isset( $params['new_license_type'] ) ) {
			$params['new_license_type'] = 'shop';
		}

		// Check the old license with the current license. If they're the same - update the license with the new one
		$current_license_hash = get_theme_mod( 'pixcare_license_hash' );

		if ( $current_license_hash === $params['old_license'] ) {
			$set_license        = set_theme_mod( 'pixcare_license_hash', $params['new_license'] );
			$set_license_status = set_theme_mod( 'pixcare_license_status', $params['new_license_status'] );
			$set_license_type   = set_theme_mod( 'pixcare_license_type', $params['new_license_type'] );
			$set_license_exp    = set_theme_mod( 'pixcare_license_expiry_date', $params['pixcare_license_expiry_date'] );
		}

		@ini_set( 'display_errors', $display_errors );

		return rest_ensure_response( array(
			'updated_license'             => $set_license,
			'updated_license_status'      => $set_license_status,
			'updated_license_type'        => $set_license_type,
			'updated_license_expiry_date' => $set_license_exp
		) );
	}

	/**
	 * Helper function that gets the value of allow_data_collect option
	 */
	function rest_get_data_collect() {
		$display_errors = @ini_set( 'display_errors', 0 );
		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		$pcoptions          = get_option( 'pixcare_options' );
		$allow_data_collect = $pcoptions['allow_data_collect'];

		wp_send_json( $allow_data_collect );
	}

	/**
	 * Helper function that sets the value of allow_data_collect option
	 */
	function rest_set_data_collect( $request ) {
		$display_errors = @ini_set( 'display_errors', 0 );
		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		$params           = $request->get_params();
		$has_data_collect = $params['allow_data_collect'];

		if ( ! isset( $has_data_collect ) ) {
			wp_send_json( 'Something went wrong. No arguments.' );
		}
		$this->set_options();

		$this->options['allow_data_collect'] = $params['allow_data_collect'];
		$this->save_options();

		wp_send_json( $this->options['allow_data_collect'] );
	}

	function rest_set_state() {
		$display_errors = @ini_set( 'display_errors', 0 );
		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		$user_data  = $this->get_user_meta();
		$theme_data = $this->get_theme_mod();

		if ( ! empty( $user_data ) && is_array( $user_data ) ) {

			$current_user = _wp_get_current_user();

			if ( isset( $user_data['oauth_token'] ) ) {
				update_user_meta( $current_user->ID, 'pixcare_oauth_token', $user_data['oauth_token'] );
			}

			if ( isset( $user_data['pixelgrade_user_ID'] ) ) {
				update_user_meta( $current_user->ID, 'pixcare_user_ID', $user_data['pixelgrade_user_ID'] );
			}

			if ( isset( $user_data['pixelgrade_user_login'] ) ) {
				update_user_meta( $current_user->ID, 'pixelgrade_user_login', $user_data['pixelgrade_user_login'] );
			}

			if ( isset( $user_data['pixelgrade_user_email'] ) ) {
				update_user_meta( $current_user->ID, 'pixelgrade_user_email', $user_data['pixelgrade_user_email'] );
			}

			if ( isset( $user_data['pixelgrade_display_name'] ) ) {
				update_user_meta( $current_user->ID, 'pixelgrade_display_name', $user_data['pixelgrade_display_name'] );
			}

			if ( isset( $user_data['oauth_token_secret'] ) ) {
				update_user_meta( $current_user->ID, 'pixcare_oauth_token_secret', $user_data['oauth_token_secret'] );
			}

			if ( isset( $user_data['oauth_verifier'] ) ) {
				update_user_meta( $current_user->ID, 'pixcare_oauth_verifier', $user_data['oauth_verifier'] );
			}
		}

		if ( ! empty( $theme_data ) && is_array( $theme_data ) ) {

			if ( isset( $theme_data['license_hash'] ) ) {
				set_theme_mod( 'pixcare_license_hash', $theme_data['license_hash'] );
			}

			if ( isset( $theme_data['status'] ) ) {
				set_theme_mod( 'pixcare_license_status', $theme_data['status'] );
			}

			if ( isset( $theme_data['license_type'] ) ) {
				set_theme_mod( 'pixcare_license_type', $theme_data['license_type'] );
			}

			if ( isset( $theme_data['license_exp'] ) ) {
				set_theme_mod( 'pixcare_license_expiry_date', $theme_data['license_exp'] );
			}
		}

		if ( ! empty( $_POST['option'] ) && isset( $_POST['value'] ) ) {
			$option = wp_unslash( $_POST['option'] );
			$value  = wp_unslash( $_POST['value'] );

			$this->options[ $option ] = $value;
			$this->save_options();

			rest_ensure_response( 1 );
		}
	}

	function rest_cleanup() {
		$display_errors = @ini_set( 'display_errors', 0 );
		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		if ( empty( $_POST['test1'] ) || empty( $_POST['test2'] ) || empty( $_POST['confirm'] ) ) {
			wp_send_json_error( 'nah' );
		}

		if ( (int) $_POST['test1'] + (int) $_POST['test2'] === (int) $_POST['confirm'] ) {
			$current_user = _wp_get_current_user();

			delete_user_meta( $current_user->ID, 'pixcare_oauth_token' );
			delete_user_meta( $current_user->ID, 'pixcare_oauth_token_secret' );
			delete_user_meta( $current_user->ID, 'pixcare_oauth_verifier' );
			delete_user_meta( $current_user->ID, 'pixcare_user_ID' );
			delete_user_meta( $current_user->ID, 'pixelgrade_user_login' );
			delete_user_meta( $current_user->ID, 'pixelgrade_user_email' );
			delete_user_meta( $current_user->ID, 'pixelgrade_display_name' );

			remove_theme_mod( 'pixcare_theme_config' );
			remove_theme_mod( 'pixcare_license_hash' );
			remove_theme_mod( 'pixcare_license_status' );
			remove_theme_mod( 'pixcare_license_type' );
			remove_theme_mod( 'pixcare_license_expiry_date' );
			remove_theme_mod( 'pixcare_new_theme_version' );

			delete_option( 'pixcare_options' );

			wp_send_json_success( 'ok' );
		}

		wp_send_json_error( array(
			$_POST['test1'],
			$_POST['test2'],
			$_POST['confirm']
		) );
	}

	function rest_disconnect_user() {
		$display_errors = @ini_set( 'display_errors', 0 );
		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		if ( empty( $_POST['user_id'] ) ) {
			wp_send_json_error( 'no user?' );
		}

		$user_id = $_POST['user_id'];

		$current_user = _wp_get_current_user();
		if ( (int) $user_id === $current_user->ID ) {
			delete_user_meta( $current_user->ID, 'pixcare_oauth_token' );
			delete_user_meta( $current_user->ID, 'pixcare_oauth_token_secret' );
			delete_user_meta( $current_user->ID, 'pixcare_oauth_verifier' );
			delete_user_meta( $current_user->ID, 'pixcare_user_ID' );
			delete_user_meta( $current_user->ID, 'pixelgrade_user_login' );
			delete_user_meta( $current_user->ID, 'pixelgrade_user_email' );
			delete_user_meta( $current_user->ID, 'pixelgrade_display_name' );

			remove_theme_mod( 'pixcare_license_hash' );
			remove_theme_mod( 'pixcare_license_status' );
			remove_theme_mod( 'pixcare_license_type' );
			remove_theme_mod( 'pixcare_license_expiry_date' );

			wp_send_json_success( 'ok' );
		}

		wp_send_json_error( 'You cannot disconnect someone else!' );
	}

	function check_theme_support() {
		if ( ! current_theme_supports( 'pixelgrade_care' ) ) {
			return false;
		}
		$config = get_theme_support( 'pixelgrade_care' );

		if ( ! is_array( $config ) ) {
			return false;
		}

		$config = $this->validate_theme_supports( $config[0] );

		if ( ! $config ) {
			return false;
		}
		$this->theme_support = $config;
	}

	function get_theme_support() {
		if ( empty( $this->theme_support ) ) {
			$this->check_theme_support();
		}

		return $this->theme_support;
	}

	function add_admin_menu() {
		$admin_page = add_submenu_page( 'themes.php', 'Pixelgrade Care', 'Theme Dashboard', 'manage_options', 'pixelgrade_care', array(
			$this,
			'pixelgrade_care_options_page'
		) );
	}

	function localize_js_data( $key = 'pixelgrade_care-dashboard' ) {
		$theme_config = array();

		if ( empty( $this->theme_support ) ) {
			$this->check_theme_support();
		}

		$current_user = wp_get_current_user();

		$theme_config = $this->get_config();

		if ( class_exists( 'TGM_Plugin_Activation' ) ) {
			$theme_config['pluginManager']['tgmpaPlugins'] = $this->localize_tgmpa_data();
		}

		// use camelCase since it is going in js
		$localized_data = array(
			'themeSupports' => $this->theme_support,
			'themeConfig'   => $theme_config,
			'wp_rest'       => array(
				'root'          => esc_url_raw( rest_url() ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'pixcare_nonce' => wp_create_nonce( 'pixelgrade_care_rest' )
			),
			// why is this a global prop?
			'systemStatus'  => array(
				'allowCollectData' => $this->get_option( 'allow_data_collect', true ),
				'installData'      => $this->get_install_data()
			),
			'knowledgeBase' => get_theme_mod( 'support' ),
			'dev_mode'      => PIXELGRADE_CARE_DEV ? "true" : "false",
			'dashboardUrl'  => admin_url( 'themes.php?page=pixelgrade_care' ),
			'adminUrl'      => admin_url(),
			'customizerUrl' => admin_url( 'customize.php' ),
			'user'          => array(
				'name'  => ( empty( $current_user->display_name ) ? $current_user->user_login : $current_user->display_name ),
				'id'    => $current_user->ID,
				'email' => $current_user->user_email,
				'name'  => $current_user->user_nicename
			),
			'themeMod'      => array(),
		);

		// user data
		$oauth_token = get_user_meta( $current_user->ID, 'pixcare_oauth_token', 1 );
		if ( ! empty( $oauth_token ) ) {
			$localized_data['user']['oauth_token'] = $oauth_token;
		}

		$oauth_token_secret = get_user_meta( $current_user->ID, 'pixcare_oauth_token_secret', 1 );
		if ( ! empty( $oauth_token_secret ) ) {
			$localized_data['user']['oauth_token_secret'] = $oauth_token_secret;
		}

		$oauth_verifier = get_user_meta( $current_user->ID, 'pixcare_oauth_verifier', 1 );
		if ( ! empty( $oauth_verifier ) ) {
			$localized_data['user']['oauth_verifier'] = $oauth_verifier;
		}

		$pixcare_user_ID = get_user_meta( $current_user->ID, 'pixcare_user_ID', 1 );
		if ( ! empty( $pixcare_user_ID ) ) {
			$localized_data['user']['pixcare_user_ID'] = $pixcare_user_ID;
		}

		$pixelgrade_user_login = get_user_meta( $current_user->ID, 'pixelgrade_user_login', 1 );
		if ( ! empty( $pixelgrade_user_login ) ) {
			$localized_data['user']['pixelgrade_user_login'] = $pixelgrade_user_login;
		}

		$pixelgrade_user_email = get_user_meta( $current_user->ID, 'pixelgrade_user_email', 1 );
		if ( ! empty( $pixelgrade_user_email ) ) {
			$localized_data['user']['pixelgrade_user_email'] = $pixelgrade_user_email;
		}

		$pixelgrade_display_name = get_user_meta( $current_user->ID, 'pixelgrade_display_name', 1 );
		if ( ! empty( $pixelgrade_user_email ) ) {
			$localized_data['user']['pixelgrade_display_name'] = $pixelgrade_display_name;
		}

		// theme data
		// first get the wupdates theme id
		$wupdates_ids = apply_filters( 'wupdates_gather_ids', array() );
		$theme_name   = strtolower( $this->theme_support['theme_name'] );
		if ( ! empty( $wupdates_ids[ $theme_name ] ) ) {
			$localized_data['themeSupports']['theme_id'] = $wupdates_ids[ $theme_name ]['id'];
		}

		$license_hash = get_theme_mod( 'pixcare_license_hash' );
		if ( ! empty( $license_hash ) ) {
			$localized_data['themeMod']['licenseHash'] = $license_hash;
		}

		$license_status = get_theme_mod( 'pixcare_license_status' );

		if ( ! empty( $license_status ) ) {
			$localized_data['themeMod']['licenseStatus'] = $license_status;
		}

		// localize the license type - can be either shop or envato
		$license_type = get_theme_mod( 'pixcare_license_type' );

		if ( ! empty( $license_type ) ) {
			$localized_data['themeMod']['licenseType'] = $license_type;
		}

		// localize the license expiry date
		$license_exp = get_theme_mod( 'pixcare_license_expiry_date' );
		if ( ! empty( $license_exp ) ) {
			$localized_data['themeMod']['licenseExpiryDate'] = $license_exp;
		}

		$new_theme_version = get_theme_mod( 'pixcare_new_theme_version' );
		if ( ! empty( $new_theme_version ) ) {
			$localized_data['themeMod']['themeNewVersion'] = $new_theme_version;
		}

		$localized_data = apply_filters( 'pixcare_localized_data', $localized_data );

		wp_localize_script( $key, 'pixcare', $localized_data );
	}

	protected function localize_tgmpa_data() {
		global $tgmpa;

		foreach ( $tgmpa->plugins as $slug => $plugin ) {

			// do not add pixelgrade care in the required plugins array
			if ( $slug === 'pixelgrade-care' ) {
				unset( $tgmpa->plugins[ $slug ] );
				continue;
			}

			$tgmpa->plugins[ $slug ]['is_installed']  = false;
			$tgmpa->plugins[ $slug ]['is_active']     = false;
			$tgmpa->plugins[ $slug ]['is_up_to_date'] = true;
			if ( $tgmpa->is_plugin_installed( $slug ) ) {
				$tgmpa->plugins[ $slug ]['is_installed'] = true;

				if ( $tgmpa->is_plugin_active( $slug ) ) {
					$tgmpa->plugins[ $slug ]['is_active'] = true;
				}

				if ( $tgmpa->does_plugin_have_update( $slug ) ) {
					$tgmpa->plugins[ $slug ]['is_up_to_date'] = false;
				}

				$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin['file_path'], false );

				$tgmpa->plugins[ $slug ]['description']    = $data['Description'];
				$tgmpa->plugins[ $slug ]['active_version'] = $data['Version'];
			}

			if ( current_user_can( 'activate_plugins' ) && is_plugin_inactive( $plugin['file_path'] ) ) {

				$plugins_url = admin_url( 'plugins.php' );

				$tgmpa->plugins[ $slug ]['activate_url'] = wp_nonce_url(
					add_query_arg(
						array(
							'plugin'         => urlencode( $slug ),
							'tgmpa-activate' => 'activate-plugin',
						),
						$tgmpa->get_tgmpa_url()
					),
					'tgmpa-activate',
					'tgmpa-nonce'
				);

				$tgmpa->plugins[ $slug ]['install_url'] = wp_nonce_url(
					add_query_arg(
						array(
							'plugin'        => urlencode( $slug ),
							'tgmpa-install' => 'install-plugin',
						),
						$tgmpa->get_tgmpa_url()
					),
					'tgmpa-install',
					'tgmpa-nonce'
				);
			}
		}

		return $tgmpa->plugins;
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

	function settings_init() {
		register_setting( 'pixelgrade_care', 'pixelgrade_care_settings' );

		add_settings_section(
			'pixelgrade_care_section',
			esc_html__( 'Pixelgrade Care description', 'pixelgrade_care' ),
			null,
			'pixelgrade_care'
		);
	}

	function pixelgrade_care_settings_section_callback() {
		echo esc_html__( 'This section description', 'pixelgrade_care' );
	}

	function pixelgrade_care_options_page() { ?>
		<div class="pixelgrade_care-wrapper">
			<div id="pixelgrade_care_dashboard"></div>
		</div>
		<?php
	}

	/** === HELPERS=== */

	function validate_theme_supports( $config ) {

		if ( ! empty( $config['support_url'] ) && ! wp_http_validate_url( $config['support_url'] ) ) {
			unset( $config['support_url'] );
		}

		if ( empty( $config['ock'] ) ) {
			$config['ock'] = 'Lm12n034gL19';
		}

		if ( empty( $config['ocs'] ) ) {
			$config['ocs'] = '6AU8WKBK1yZRDerL57ObzDPM7SGWRp21Csi5Ti5LdVNG9MbP';
		}

		if ( ! empty( $config['support_url'] ) && ! wp_http_validate_url( $config['support_url'] ) ) {
			unset( $config['support_url'] );
		}

		if ( empty( $config['onboarding'] ) ) {
			$config['onboarding'] = 1;
		}

		if ( empty( $config['market'] ) ) {
			$config['market'] = 'pixelgrade';
		}

		// Complete the config with theme details
		$theme = wp_get_theme();

		if ( is_child_theme() ) {
			$theme = $theme->parent();
		}

		if ( empty( $config['theme_name'] ) ) {
			$config['theme_name'] = $theme->get( 'Name' );
		}

		if ( empty( $config['theme_uri'] ) ) {
			$config['theme_uri'] = $theme->get( 'ThemeURI' );
		}

		if ( empty( $config['theme_desc'] ) ) {
			$config['theme_desc'] = $theme->get( 'Description' );
		}

		if ( empty( $config['theme_version'] ) ) {
			$config['theme_version'] = $theme->get( 'Version' );
		}

		if ( empty( $config['shop_url'] ) ) {
			// the url of the mother shop, trailing slash is required
			$config['shop_url'] = apply_filters( 'pixelgrade_care_shop_url', 'https://pixelgrade.com/' );
		}

		$config['is_child'] = is_child_theme();

		$config['template'] = $theme->template;

		return apply_filters( 'pixcare_validate_theme_supports', $config );
	}

	function is_pixelgrade_care_dashboard() {
		if ( ! empty( $_GET['page'] ) && 'pixelgrade_care' === $_GET['page'] ) {
			return true;
		}

		return false;
	}

	function set_options() {
		$this->options = get_option( 'pixcare_options' );
	}

	function save_options() {
		update_option( 'pixcare_options', $this->options );
	}

	function get_options() {
		if ( empty( $this->options ) ) {
			$this->set_options();
		}

		return $this->options;
	}

	function get_option( $option, $default = null ) {
		$options = $this->get_options();

		if ( ! empty( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		if ( $default !== null ) {
			return $default;
		}

		return null;
	}

	function get_state_option( $option, $default = null ) {

		$pixcare_state = $this->get_option( 'state' );

		if ( empty( $pixcare_state ) ) {
//			$this->set_default_state_option();
		}

		if ( ! empty( $pixcare_state[ $option ] ) ) {
			return $pixcare_state[ $option ];
		}

		if ( $default !== null ) {
			return $default;
		}

		return null;
	}

	function init_knowledgeBase_categories() {
		$support_mod = get_theme_mod( 'support' );

		if ( empty( $support_mod ) ) {
			$this->_set_knowledgeBase_categories();
		}
	}

	function transient_update_kb_categories( $transient ) {
		$this->_set_knowledgeBase_categories();

		return $transient;
	}

	private function _set_knowledgeBase_categories() {
		// Get default
		$knowledge_base = array(
			// @TODO find out why this exists
			'categories' => $this->_get_kb_categories()
		);

		// Save to theme mod
		set_theme_mod( 'support', $knowledge_base );
	}

	private function _get_kb_categories() {
		// Get existing categories
		$args             = array(
			'timeout' => 20
		);
		$get_category_url = 'https://pixelgrade.com/wp-json/pxm/v1/front/get_theme_categories?kb_current_product_sku=' . $this->theme_support['template'];

		$categories = wp_remote_get( $get_category_url, $args );

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		$response = json_decode( wp_remote_retrieve_body( $categories ), true );

		return $response;
	}

	/**
	 * Helper function that sends the System Status Data to our Dashboard
	 */
	function get_install_data() {

		if ( ! isset( $this->options['allow_data_collect'] ) ) {
			$this->options['allow_data_collect'] = "true";
			$this->save_options();
		} elseif ( false === $this->options['allow_data_collect'] ) {
			return "false";
		}

		$data     = new PixelgradeCare_DataCollector( $this->plugin_name, $this->version, $this->get_theme_support() );
		$response = $data->get_post_data();

		return json_encode( $response );
	}

	function pixelgrade_care_switch_theme() {
		if ( is_child_theme() ) {
			return null;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return null;
		}

//		$this->set_default_state_option();
	}

	function transient_update_theme_version( $transient ) {

		// Nothing to do here if the checked transient entry is empty
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Let's start gathering data about the theme
		// First get the theme directory name (the theme slug - unique)
		$slug = basename( get_template_directory() );

		$theme_data['new_version'] = '';

		if ( isset( $transient->checked[ $slug ] ) && $transient->checked[ $slug ] ) {
			$theme_data['new_version'] = $transient->checked[ $slug ];
		}

		if ( isset( $transient->response[ $slug ] ) && $transient->response[ $slug ] ) {
			$theme_data['new_version'] = $transient->response[ $slug ]["new_version"];
		}

		set_theme_mod( 'pixcare_new_theme_version', $theme_data['new_version'] );

		return $transient;
	}

	/**
	 * Returns the config resulted from merging the default config with the remote one
	 * @return array|bool|mixed|object|string
	 */
	private function get_config() {
		$config = get_theme_mod( 'pixcare_theme_config' );

		if ( empty( $config ) ) {
			$config = $this->get_remote_config();
		}

		// Get a default config
		$default_config = $this->get_default_config();


		if ( empty( $config ) || ! is_array( $config ) ) {
			return $default_config;
		}

		return $this->array_merge_recursive_ex( $default_config, $config );
	}

	/**
	 * Merge two arrays recursively first by key
	 *
	 * An entry can be specifically removed if in the first array `null` is given as value
	 *
	 * @param array $array1
	 * @param array $array2
	 *
	 * @return array
	 */
	function array_merge_recursive_ex( array & $array1, array & $array2 ) {
		$merged = $array1;

		foreach ( $array2 as $key => & $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->array_merge_recursive_ex( $merged[ $key ], $value );
			} else if ( is_numeric( $key ) ) {
				if ( ! in_array( $value, $merged ) ) {
					$merged[] = $value;
				}
			} else if ( null === $value ) {
				unset( $merged[ $key ] );
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	function transient_update_remote_config( $transient ) {
		// Nothing to do here if the checked transient entry is empty
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->get_remote_config();

		return $transient;
	}

	/**
	 *
	 */
	function transient_update_license_data( $transient ) {

		// Nothing to do here if the checked transient entry is empty
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Check the status of the user's license
		$this->check_theme_license();

		return $transient;
	}

	/**
	 * Checks the status of the current theme's license
	 */
	function check_theme_license() {
		$theme_support = get_theme_support( 'pixelgrade_care' );

		// Get the id of the current theme
		$wupdates_ids = apply_filters( 'wupdates_gather_ids', array() );

		// Get the theme's name
		if ( isset( $theme_support['theme_name'] ) ) {
			$theme_name = strtolower( $theme_support['theme_name'] );
		} else {
			$theme_name = strtolower( basename( get_template_directory() ) );
		}

		if ( ! empty( $wupdates_ids[ $theme_name ] ) ) {
			$theme_id = $wupdates_ids[ $theme_name ]['id'];
		}

		// get the pixelgrade user id
		$current_user = _wp_get_current_user();
		$user_id      = $this->get_user_meta( $current_user->ID, 'pixcare_user_ID' );

		if ( ! $user_id ) {
			// not authenticated
			return false;
		}

		$args = array(
			'user_id' => $user_id,
			'hash_id' => $theme_id
		);

		// get the user's licenses from wupdates
		$get_licenses = wp_remote_post( 'https://pixelgrade.com/wp-json/wupl/v1/front/get_licenses?user_id=' . $user_id . '&hash_id=' . $theme_id, $args );

		if ( is_wp_error( $get_licenses ) ) {
			return false;
		}

		$subscriptions = json_decode( wp_remote_retrieve_body( $get_licenses ), true );

		if ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $key => $value ) {

				if ( ! isset( $value['licenses'] ) || empty( $value['licenses'] ) ) {
					//no licenses found
					return false;
				}

				foreach ( $value['licenses'] as $license ) {
					if ( $license['wupdates_product_hashid'] == $theme_id && $license['license_status'] == 'valid' ) {
						// valid license - we can allow the user to continue
						set_theme_mod( 'pixcare_license_hash', $license['license_hash'] );
						set_theme_mod( 'pixcare_license_status', $license['license_status'] );
						set_theme_mod( 'pixcare_license_type', $license['license_type'] );
						set_theme_mod( 'pixcare_license_expiry_date', $license['license_expiry_date'] );

						return true;
					} elseif ( $license['wupdates_product_hashid'] == $theme_id && $license['license_status'] == 'active' ) {
						//the license is in use somewhere else
						set_theme_mod( 'pixcare_license_hash', $license['license_hash'] );
						set_theme_mod( 'pixcare_license_status', $license['license_status'] );
						set_theme_mod( 'pixcare_license_type', $license['license_type'] );
						set_theme_mod( 'pixcare_license_expiry_date', $license['license_expiry_date'] );

						return true;
					} elseif ( $license['wupdates_product_hashid'] == $theme_id && ( $license['license_status'] == 'expired' || $license['license_status'] == 'overused' ) ) {
						//the license is expired

						set_theme_mod( 'pixcare_license_hash', $license['license_hash'] );
						set_theme_mod( 'pixcare_license_status', $license['license_status'] );
						set_theme_mod( 'pixcare_license_type', $license['license_type'] );
						set_theme_mod( 'pixcare_license_expiry_date', $license['license_expiry_date'] );

						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * A function that will serve the config file for the current theme
	 */
	function get_remote_config() {
		// get the theme
		$theme_support = get_theme_support( 'pixelgrade_care' );

		if ( empty( $theme_support ) ) {
			return false;
		}

		if ( ! $this->options ) {
			$this->options = array();
		}

		$slug = basename( get_template_directory() );

		$ids = apply_filters( 'wupdates_gather_ids', array() );

		if ( ! isset( $ids[ $slug ] ) ) {
			return false;
		}

		$theme_hash_id = $ids[ $slug ]["id"];

		$url = apply_filters( 'pixelgrade_care_shop_url', 'https://pixelgrade.com/' ) . 'wp-json/pxm/v1/front/get_config';

		$args = array(
			'body'   => array(
				'hash_id' => $theme_hash_id,
				'version' => $this->api_version
			),
			'method' => 'GET'
		);

		$response = wp_remote_post( $url, $args );

		// to check for error
		if ( is_wp_error( $response ) || $response["response"]["code"] !== 200 || ! isset( $response['body'] ) || empty( $response['body'] ) ) {
			// Nada
			return false;
		}

		$config = json_decode( $response['body'], true );

		// such a shim ... keep it just two versions
		if ( ! empty( $config['pixelgrade_care'] ) ) {
			$config['dashboard'] = $config['pixelgrade_care'];
			unset( $config['pixelgrade_care'] );
		}

		set_theme_mod( 'pixcare_theme_config', $config );

		return $config;
	}

	function get_default_config() {
		global $tgmpa;

		// General strings ready to be translated
		$return['l10n'] = array(
			'myAccountBtn'                  => esc_html__( 'My Account', 'pixelgrade_care' ),
			'needHelpBtn'                   => esc_html__( 'Need Help?', 'pixelgrade_care' ),
			'returnToDashboard'             => esc_html__( 'Return to the WordPress Dashboard', 'pixelgrade_care' ),
			'nextButton'                    => esc_html__( 'Next Step', 'pixelgrade_care' ),
			'skipButton'                    => esc_html__( 'Skip this step', 'pixelgrade_care' ),
			'notRightNow'                   => esc_html__( 'Not right now', 'pixelgrade_care' ),
			'validationErrorTitle'          => esc_html__( 'Theme Validation Error', 'pixelgrade_care' ),
			'themeValidationNoticeFail'     => esc_html__( 'Not Activated', 'pixelgrade_care' ),
			'themeValidationNoticeOk'       => esc_html__( 'Verified & Up-to-date!', 'pixelgrade_care' ),
			'themeValidationNoticeOutdated' => esc_html__( 'Your theme version is old!', 'pixelgrade_care' ),
			'themeUpdateAvailableTitle'     => esc_html__( 'New Theme Update is Available!', 'pixelgrade_care' ),
			'themeUpdateAvailableContent'   => esc_html__( 'A new version is available.', 'pixelgrade_care' ),
			'themeUpdateButton'             => esc_html__( 'Update', 'pixelgrade_care' ),
			'kbButton'                      => esc_html__( 'Theme Help', 'pixelgrade_care' ),
		);

		$return['setupWizard'] = array(
			'start' => array(
				'stepName' => 'Start',
				'nextText' => 'Let\'s go',
				'blocks'   => array(
					'main_block' => array(
						'class'  => 'full white',
						'fields' => array(
							'title'          => array(
								'type'  => 'h2',
								'value' => 'Welcome to {{theme_name}}',
								'class' => 'section__title'
							),
							'first_content'  => array(
								'type'  => 'text',
								'value' => 'Thank you for being awesome and choosing {{theme_name}}! Let’s make it shine. This quick setup wizard helps you configure the upcoming website by installing the required plugins and validate the theme.'
							),
							'second_content' => array(
								'type'  => 'text',
								'value' => 'But hey, it’s fully optional, and it won’t take longer than two minutes. No mood for this? No worries, you can skip it right now and come back later on the WordPress dashboard to walk through the wizard.'
							)
						)
					),
				)
			),

			'activation' => array(
				'stepName' => 'Theme Activation',
				'blocks'   => array(
					'authenticator' => array(
						'class'  => 'full white',
						'fields' => array(
							'authenticator_component' => array(
								'title' => 'Activate {{theme_name}}!',
								'type'  => 'component',
								'value' => 'authenticator',
							),
						)
					),
				)
			),

			'plugins' => array(
				'stepName' => 'Plugins',
				'blocks'   => array(
					'plugins' => array(
						'class'  => 'full white',
						'fields' => array(
							'title'             => array(
								'type'  => 'h2',
								'value' => 'Installing plugins',
								'class' => 'section__title'
							),
							'plugins_component' => array(
								'title' => 'Plugins {{theme_name}}!',
								'type'  => 'component',
								'value' => 'plugin-manager',
							),
						)
					),
				)
			),

			'support' => array(
				'stepName' => 'Support',
				'nextText' => 'Continue',
				'blocks'   => array(
					'support' => array(
						'class'  => 'full white',
						'fields' => array(
							'title'          => array(
								'type'  => 'h2',
								'value' => 'Help & Support',
								'class' => 'section__title'
							),
							'head_content'   => array(
								'type'  => 'text',
								'value' => 'Start this journey with the right foot and make the most out of this WordPress theme.'
							),
							'content'        => array(
								'type'  => 'text',
								'value' => 'Here is a list of the most common resources from our growing Knowledge Base:'
							),
							'links'          => array(
								'type'  => 'links',
								'value' => array(
									array(
										'label' => 'Adding your Logo',
										'url'   => 'https://pixelgrade.com/docs/header-and-footer/adding-your-logo'
									),
									array(
										'label' => 'Managing your Navigation',
										'url'   => 'https://pixelgrade.com/docs/header-and-footer/managing-your-navigation'
									),
									array(
										'label' => 'Design & Style Overview',
										'url'   => 'https://pixelgrade.com/docs/design-and-style/overview'
									),
									array(
										'label' => 'Using the Custom CSS Editor',
										'url'   => 'https://pixelgrade.com/docs/design-and-style/custom-code/using-custom-css-editor'
									),
								)
							),
							'footer_content' => array(
								'type'  => 'text',
								'value' => 'If you still have questions, reach our customer service if you need further assistance. We\'re eager to lend a hand!'
							),
						)
					),
				),
			),

			'ready' => array(
				'stepName' => 'Ready',
				'blocks'   => array(

					'ready' => array(
						'class'  => 'full white',
						'fields' => array(
							'title'   => array(
								'type'  => 'h2',
								'value' => 'Your Theme is Ready!',
								'class' => 'section__title'
							),
							'content' => array(
								'type'  => 'text',
								'value' => 'Big congrats, mate! 👏 The theme has been activated, and your website is ready to get some traction. Login to your Wordpress dashboard to make any changes you want, and feel free to change the default content to match your needs.'
							),
						)
					),

					'redirect_area' => array(
						'class'  => 'half',
						'fields' => array(
							'title' => array(
								'type'  => 'h4',
								'value' => 'Next Steps'
							),
							'cta'   => array(
								'type'  => 'button',
								'label' => 'Customize your site!',
								'url'   => '{{customizer_url}}?return=' . urlencode( admin_url( 'themes.php?page=pixelgrade_care' ) )
							),
						)
					),

					'help_links' => array(
						'class'  => 'half',
						'fields' => array(
							'title' => array(
								'type'  => 'h4',
								'value' => 'Learn More'
							),
							'links' => array(
								'type'  => 'links',
								'value' => array(
									array(
										'label' => 'Read the Theme Documentation',
										'url'   => 'https://pixelgrade.com/docs'
									),
									array(
										'label' => 'Learn how to use WordPress',
										'url'   => 'https://easywpguide.com'
									),
									array(
										'label' => 'Get Help and Support',
										'url'   => 'https://pixelgrade.com/get-support/'
									),
									array(
										'label' => 'Join our Facebook group',
										'url'   => 'https://www.facebook.com/groups/PixelGradeUsersGroup/'
									),
								)
							),
						)
					),
				)
			)
		);

		$return['dashboard'] = array(
			'general' => array(
				'name'   => 'General',
				'blocks' => array(
					'authenticator' => array(
						'class'  => 'full white',
						'fields' => array(
							'authenticator' => array(
								'type'  => 'component',
								'value' => 'authenticator'
							),
						)
					),
				),
			),

			'customizations' => array(
				'name'   => 'Customizations',
				'class'  => 'sections-grid__item',
				'blocks' => array(
					'featured'  => array(
						'class'  => 'u-text-center',
						'fields' => array(
							'title'   => array(
								'type'  => 'h2',
								'value' => 'Customizations',
								'class' => 'section__title'
							),
							'content' => array(
								'type'  => 'text',
								'value' => 'We know that each website needs to have an unique voice which defines your charisma. That’s why we created a smart option systems so you can easily make handy color changes, spacing adjustements and balacing fonts, each step bringing you closer to a striking result.',
								'class' => 'section__content'
							),
							'cta'     => array(
								'type'  => 'button',
								'class' => 'btn--action  btn--green',
								'label' => 'Access the Customizer',
								'url'   => '{{customizer_url}}'
							),
						),
					),
					'subheader' => array(
						'class'  => 'section--airy  u-text-center',
						'fields' => array(
							'subtitle' => array(
								'type'  => 'h3',
								'value' => 'Learn more',
								'class' => 'section__subtitle'
							),
							'title'    => array(
								'type'  => 'h2',
								'value' => 'Design & Style',
								'class' => 'section__title'
							),
						),
					),
					'colors'    => array(
						'class'  => 'half sections-grid__item',
						'fields' => array(
							'title'   => array(
								'type'  => 'h4',
								'value' => '<img class="emoji" alt="🎨" src="https://s.w.org/images/core/emoji/2.2.1/svg/1f3a8.svg"> Tweaking Colors Schemes',
								'class' => 'section__title'
							),
							'content' => array(
								'type'  => 'text',
								'value' => 'Choose colors that resonate with the statement you want to draw. For example, blue inspires safety and peace, while yellow is translated into energy and joyfulness.'
							),
							'cta'     => array(
								'type'  => 'button',
								'label' => 'Changing Colors',
								'class' => 'btn--action btn--small  btn--blue',
								'url'   => 'https://pixelgrade.com/docs/design-and-style/style-changes/changing-colors/'
							)
						),
					),

					'fonts' => array(
						'class'  => 'half sections-grid__item',
						'fields' => array(
							'title'   => array(
								'type'  => 'h4',
								'value' => '<img class="emoji" alt="🎨" src="https://s.w.org/images/core/emoji/2.2.1/svg/1f3a8.svg"> Managing Fonts',
								'class' => 'section__title'
							),
							'content' => array(
								'type'  => 'text',
								'value' => 'We recommend you to limit yourself to only a few fonts: it’s best to stick with two fonts but if you’re feeling ambitious, three is the maximum.'
							),
							'cta'     => array(
								'type'  => 'button',
								'label' => 'Changing Fonts',
								'class' => 'btn--action btn--small  btn--blue',
								'url'   => 'https://pixelgrade.com/docs/design-and-style/style-changes/changing-fonts/'
							)
						),
					),

					'custom_css' => array(
						'class'  => 'half sections-grid__item',
						'fields' => array(
							'title'   => array(
								'type'  => 'h4',
								'value' => '<img class="emoji" alt="🎨" src="https://s.w.org/images/core/emoji/2.2.1/svg/1f3a8.svg"> Custom CSS',
								'class' => 'section__title'
							),
							'content' => array(
								'type'  => 'text',
								'value' => 'If you’re looking for changes that are not possible through the current set of options, you can use Custom CSS code to override the default styles of your theme.'
							),
							'cta'     => array(
								'type'  => 'button',
								'label' => 'Using the Custom CSS Editor',
								'class' => 'btn--action btn--small  btn--blue',
								'url'   => 'https://pixelgrade.com/docs/design-and-style/custom-code/using-custom-css-editor'
							)
						),
					),

					'advanced' => array(
						'class'  => 'half sections-grid__item',
						'fields' => array(
							'title'   => array(
								'type'  => 'h4',
								'value' => '<img class="emoji" alt="🎨" src="https://s.w.org/images/core/emoji/2.2.1/svg/1f3a8.svg"> Advanced Customizations',
								'class' => 'section__title'
							),
							'content' => array(
								'type'  => 'text',
								'value' => 'For changes regarding HTML or PHP code, to preserve your custom code from being overwritten on next theme update, the best way is within a child theme.'
							),
							'cta'     => array(
								'type'  => 'button',
								'label' => 'Using a Child Theme',
								'class' => 'btn--action btn--small  btn--blue',
								'url'   => 'https://pixelgrade.com/docs/getting-started/using-child-theme'
							)
						),
					),
				),
			),

			'system-status' => array(
				'name'   => 'System Status',
				'blocks' => array(
					'system-status' => array(
						'class'  => 'u-text-center',
						'fields' => array(
							'title'        => array(
								'type'  => 'h2',
								'class' => 'section__title',
								'value' => 'System Status'
							),
							'systemStatus' => array(
								'type'  => 'component',
								'value' => 'system-status'
							),
							'tools'        => array(
								'type'  => 'component',
								'value' => 'pixcare-tools'
							),
						),
					)
				)
			)
		);

		$return['systemStatus'] = array(
			'phpRecommendedVersion'     => 5.6,
			'title'                     => 'System Status',
			'description'               => 'Allow Pixelgrade to collect non-sensitive diagnostic data and usage information. This will help us give you better service when you reach us through our support system.',
			'phpOutdatedNotice'         => 'This version is not fully supported. We recommend you update to PHP ',
			'wordpress_outdated_notice' => 'This version is outdated. We recommend you update to the latest WordPress release for a better functionality.',
		);

		$return['pluginManager'] = array(
			'updateButton' => esc_html__( 'Update', 'pixelgrade_care' )
		);

		$return['knowledgeBase'] = array(
			'selfHelp'   => array(
				'name'   => 'Self Help',
				'blocks' => array(
					'search' => array(
						'class'  => 'support-autocomplete-search',
						'fields' => array(
							'placeholder' => 'Search through the Knowledge Base'
						)
					),
					'info'   => array(
						'class'  => '',
						'fields' => array(
							'title'     => array(
								'type'  => 'h1',
								'value' => 'Theme Help & Support',
							),
							'content'   => array(
								'type'  => 'text',
								'value' => 'You have an <u>active theme license</u> for {{theme_name}}. This means you\'re able to get front-of-the-line support service. Check out our documentation in order to get quick answers in no time. Chances are it\'s been answered already!'
							),
							'subheader' => array(
								'type'  => 'h2',
								'value' => 'How can we help?'
							)
						)
					)
				)
			),
			'openTicket' => array(
				'name'   => 'Open Ticket',
				'blocks' => array(
					'topics'        => array(
						'class'  => '',
						'fields' => array(
							'title'  => array(
								'type'  => 'h2',
								'value' => 'What can we help with?'
							),
							'topics' => array(
								'class'  => 'topics-list',
								'fields' => array(
									'start'          => array(
										'type'  => 'text',
										'value' => 'I have a question about how to start'
									),
									'feature'        => array(
										'type'  => 'text',
										'value' => 'I have a question about how a distinct feature works'
									),
									'plugins'        => array(
										'type'  => 'text',
										'value' => 'I have a question about plugins'
									),
									'productUpdates' => array(
										'type'  => 'text',
										'value' => 'I have a question about product updates'
									),
									'payments'       => array(
										'type'  => 'text',
										'value' => 'I have a question about payments'
									),
								)
							)
						)
					),
					'ticket'        => array(
						'class'  => '',
						'fields' => array(
							'title'             => array(
								'type'  => 'h1',
								'value' => 'Give us more details'
							),
							'changeTopic'       => array(
								'type'  => 'button',
								'label' => 'Change Topic',
								'class' => 'btn__dark',
								'url'   => '#'
							),
							'descriptionHeader' => array(
								'type'  => 'text',
								'value' => 'How can we help?'
							),
							'descriptionInfo'   => array(
								'type'  => 'text',
								'class' => 'label__more-info',
								'value' => 'Briefly describe how we can help.'
							),
							'detailsHeader'     => array(
								'type'  => 'text',
								'value' => 'Tell Us More'
							),
							'detailsInfo'       => array(
								'type'  => 'text',
								'class' => 'label__more-info',
								'value' => 'Share all the details. Be specific and include some steps to recreate things and help us get to the bottom of things more quickly! Use a free service like <a href="http://imgur.com/" target="_blank">Imgur</a> or <a href="http://tinypic.com/" target="_blank">Tinypic to upload files and include the link.</a> '
							),
							'nextButton'        => array(
								'type'  => 'button',
								'label' => 'Next Step',
								'class' => 'form-row submit-wrapper',
							)
						)
					),
					'searchResults' => array(
						'class'  => '',
						'fields' => array(
							'title'       => array(
								'type'  => 'h1',
								'value' => 'Try these solutions first'
							),
							'description' => array(
								'type'  => 'text',
								'value' => 'Based on the details you provided, we found a set of articles that could help you instantly. Before you submit a ticket, please check these resources first:'
							),
							'noResults'   => array(
								'type'  => 'text',
								'value' => 'Sorry, we couldn\'t find any articles in our knowledge base that match your questions'
							)
						)
					),
					'sticky'        => array(
						'class'  => 'notification__blue clear sticky',
						'fields' => array(
							'noLicense'       => array(
								'type'  => 'text',
								'value' => 'Please activate your theme, in order to be able to submit tickets.'
							),
							'initialQuestion' => array(
								'type'  => 'text',
								'value' => 'Did any of the above resources answer your question?'
							),
							'success'         => array(
								'type'  => 'text',
								'value' => '😊 Yaaay! You did it by yourself!'
							),
							'noSuccess'       => array(
								'type'  => 'text',
								'value' => '😕 Sorry we couldn\'t find an instant answer.'
							),
							'submitTicket'    => array(
								'type'  => 'button',
								'label' => 'Submit a ticket',
								'class' => 'btn__dark'
							)
						)
					),
					'success'       => array(
						'class'  => 'success',
						'fields' => array(
							'title'       => array(
								'type'  => 'h1',
								'value' => '👍 Thanks!'
							),
							'description' => array(
								'type'  => 'text',
								'value' => 'Your ticket was submitted successfully! As soon as a member of our crew has had a chance to review it they will be in touch with you at dev-email@pressmatic.dev (the email used to purchase this theme).'
							),
							'footer'      => array(
								'type'  => 'text',
								'value' => 'Cheers'
							),
							'links'       => array(
								'type'  => 'links',
								'value' => array(
									array(
										'label' => 'Back to Self Help',
										'url'   => '#'
									)
								)
							),
						)
					)
				)
			)
		);

		// the authenticator config is based on the component status which can be: not_validatesd, loading, validated
		$return['authentication'] = array(
			//general strings
			'title'               => 'You are almost finished!',
			// validated string
			'validatedTitle'      => 'Well done, <strong>{{username}}</strong>!',
			'validatedContent'    => 'Congratulations! Your site is now active. You can find below some useful links and if you have any questions, do not hesitate to reach us.',
			'validatedButton'     => '{{theme_name}} Activated!',
			//  not validated strings
			'notValidatedContent' => 'In order to get access to support, demo content and automatic updates you need to validate the theme by simply linking this site to your Pixelgrade shop account. Learn more about product validation.',
			'notValidatedButton'  => 'Activate the Theme License!',
			// no themes form shop
			'noThemeContent'      => 'Ups! You are logged in, but it seems you haven\'t purchased this theme yet.',
			'noThemeRetryButton'  => 'Retry to activate',
			'noThemeLicense'      => 'You don\'t seem to have any licenses for this theme.',
			// loading strings
			'loadingContent'      => 'Getting theme details ...',
			'loadingPrepare'      => 'Prepare ...',
			'loadingError'        => 'Sorry .. I can\'t do this right now!',
			// license urls
			'buyThemeUrl'         => esc_url( 'https://pixelgrade.com/themes' ),
			'renewLicenseUrl'     => esc_url( 'https://pixelgrade.com/themes' )
		);

		$update_core = get_site_transient( 'update_core' );

		if ( ! empty( $update_core->updates ) && ! empty( $update_core->updates[0] ) ) {
			$new_update                                     = $update_core->updates[0];
			$return['systemStatus']['wpRecommendedVersion'] = $new_update->current;
		}

		$return = apply_filters( 'pixcare_default_config', $return );

		return $return;
	}

	function transient_delete_oauth_token( $transient ) {
		$current_user    = _wp_get_current_user();
		$user_token_meta = get_user_meta( $current_user->ID, 'pixcare_oauth_token' );

		if ( $user_token_meta ) {
			delete_user_meta( $current_user->ID, 'pixcare_oauth_token' );
			delete_user_meta( $current_user->ID, 'pixcare_oauth_token_secret' );
			delete_user_meta( $current_user->ID, 'pixcare_oauth_verifier' );
		}

		return $transient;
	}
}


