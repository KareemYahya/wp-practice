<?php

/**
 * Fired during plugin activation
 *
 * @link       https://pixelgrade.com
 * @since      1.0.0
 *
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/includes
 * @author     Pixelgrade <email@example.com>
 */
class PixelgradeCareActivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $pixcare_plugin;
		if ( $pixcare_plugin->is_wp_compatible() ) {
			$pixcare_plugin->get_theme_config();
		}
	}
}
