<?php
/**
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://pixelgrade.com
 * @since      1.0.0
 *
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/includes
 */

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
class PixelgradeCare_DataCollector {

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
	private $config;

	public function __construct( $plugin_name, $version, $config = null ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->config      = $config;
		$this->get_install_data();
	}


	public function filter_wupdates_data_response( $data, $version ) {
		return $this->get_post_data();
	}

	/**
	 * JSON-encoded data to be posted to data-vault
	 */
	public function get_post_data() {
		$response                 = new stdClass();
		$response->install_data   = $this->get_install_data();
		$response->theme_options  = $this->get_theme_options();
		$response->core_options   = $this->get_core_options();
		$response->active_plugins = $this->get_active_plugins();
		$response->system_data    = $this->get_system_data();

		return $response;
	}

	public function get_install_data() {
		$theme_hash_id = '';

		// Get the Product Id
		$slug = basename( get_template_directory() );
		$ids  = apply_filters( 'wupdates_gather_ids', array() );

		if ( array_key_exists( $slug, $ids ) ) {
			$theme_hash_id = $ids[ $slug ]["id"];
		}

		$install_data                 = new stdClass();
		$install_data->url            = home_url( '/' );
		$install_data->theme_name     = $this->config["theme_name"];
		$install_data->theme_version  = $this->config["theme_version"];
		$install_data->is_child_theme = $this->config["is_child"];
		$install_data->template       = $this->config["template"];
		$install_data->product        = $theme_hash_id;

		// Check if the current install has an active license. If it does - send over the license hash
		$license_hash = get_theme_mod( 'pixcare_license_hash' );
		if ( isset( $license_hash ) && ! empty( $license_hash ) ) {
			$install_data->license_hash = $license_hash;
		}

		return $install_data;
	}

	public function get_theme_options() {
		$response = get_option( $this->config["theme_name"] . '_options' );

		return $response;
	}

	/**
	 * Return core options
	 */
	public function get_core_options() {
		$response["core_options"] = array(
			"users_can_register"    => get_option( 'users_can_register' ),
			"start_of_week"         => get_option( 'start_of_week' ),
			"use_balanceTags"       => get_option( 'use_balanceTags' ),
			"use_smilies"           => get_option( 'use_smilies' ),
			"require_name_email"    => get_option( 'require_name_email' ),
			"comments_notify"       => get_option( 'comments_notify' ),
			"posts_per_rss"         => get_option( 'posts_per_rss' ),
			"rss_use_excerpt"       => get_option( 'rss_use_excerpt' ),
			"posts_per_page"        => get_option( 'posts_per_page' ),
			"date_format"           => get_option( 'date_format' ),
			"time_format"           => get_option( 'time_format' ),
			"comment_moderation"    => get_option( 'comment_moderation' ),
			"moderation_notify"     => get_option( 'moderation_notify' ),
			"permalink_structure"   => get_option( 'permalink_structure' ),
			"blog_charset"          => get_option( 'blog_charset' ),
			"template"              => get_option( 'template' ),
			"stylesheet"            => get_option( 'stylesheet' ),
			"comment_whitelist"     => get_option( 'comment_whitelist' ),
			"comment_registration"  => get_option( 'comment_registration' ),
			"html_type"             => get_option( 'html_type' ),
			"default_role"          => get_option( 'default_role' ),
			"db_version"            => get_option( 'db_version' ),
			"blog_public"           => get_option( 'blog_public' ),
			"default_link_category" => get_option( 'default_link_category' ),
			"show_on_front"         => get_option( 'show_on_front' ),
			"thread_comments"       => get_option( 'thread_comments' ),
			"page_comments"         => get_option( 'page_comments' ),
//			"uninstall_plugins"     => get_option( 'uninstall_plugins' ),
			"theme_switched"        => get_option( 'theme_switched' )
		);

		return $response["core_options"];
	}

	/**
	 * Return active plugins
	 */
	public function get_active_plugins() {
		$active_plugins = get_option( 'active_plugins' );
		$response       = array();

		foreach ( $active_plugins as $active_plguin ) {
			$plugin_data                      = get_plugin_data( ABSPATH . 'wp-content/plugins/' . $active_plguin );
			$response[ $plugin_data['Name'] ] = array(
				"version"    => $plugin_data['Version'],
				"pluginUri"  => $plugin_data['PluginURI'],
				"authorName" => $plugin_data['AuthorName']
			);
		}

		return $response;
	}

	/**
	 * Return system data
	 */
	public function get_system_data() {
		global $wpdb;

		// WP memory limit
		$wp_memory_limit = $this->pixelgrade_care_let_to_num( WP_MEMORY_LIMIT );
		if ( function_exists( 'memory_get_usage' ) ) {
			$wp_memory_limit = max( $wp_memory_limit, $this->pixelgrade_care_let_to_num( @ini_get( 'memory_limit' ) ) );
		}

		$web_server = $_SERVER['SERVER_SOFTWARE'] ? $_SERVER['SERVER_SOFTWARE'] : '';

		if ( function_exists( 'phpversion' ) ) {
			$php_version = phpversion();
		}

		$response = array(
			'wp_debug_mode'          => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? "true" : "false",
			'wp_cron'                => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? "true" : "false",
			'wp_version'             => get_bloginfo( 'version' ),
			'web_server'             => $web_server,
			'wp_memory_limit'        => $wp_memory_limit, // in bytes
			'php_post_max_size'      => $this->pixelgrade_care_let_to_num( ini_get( 'post_max_size' ) ), // in bytes
			'php_max_execution_time' => ini_get( 'max_execution_time' ),
			'php_version'            => $php_version,
			'mysql_version'          => $mysql_version = $wpdb->db_version(),
			'wp_locale'              => get_locale(),
			'db_charset'             => DB_CHARSET ? DB_CHARSET : 'undefined'
		);

		return $response;
	}

	function pixelgrade_care_let_to_num( $size ) {
		$l   = substr( $size, - 1 );
		$ret = substr( $size, 0, - 1 );
		switch ( strtoupper( $l ) ) {
			case 'P':
				$ret *= 1024;
			case 'T':
				$ret *= 1024;
			case 'G':
				$ret *= 1024;
			case 'M':
				$ret *= 1024;
			case 'K':
				$ret *= 1024;
		}

		return $ret;
	}

}
