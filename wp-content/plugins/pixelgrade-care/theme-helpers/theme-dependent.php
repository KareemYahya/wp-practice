<?php
/**
 * Various functionality that should be loaded only if a theme declared support for it
 *
 * @link       https://pixelgrade.com
 * @since      1.2.1
 *
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/ThemeHelpers
 */

function pixelgrade_load_theme_dependent_functionality() {

	if ( current_theme_supports( 'pixelgrade_opentable_widget' ) ) {
		// Load the OpenTable custom widget and shortcode code
		require_once( plugin_dir_path( __FILE__ ) . 'wp-open-table/wp-open-table.php' );

		// Register the [ot_reservation_widget] shortcode
		add_shortcode( 'ot_reservation_widget', 'osteria_ot_reservation_widget_shortcode' );
	}

}
add_action( 'after_setup_theme', 'pixelgrade_load_theme_dependent_functionality', 20 );
