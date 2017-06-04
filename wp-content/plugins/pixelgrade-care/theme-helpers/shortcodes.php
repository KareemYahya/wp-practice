<?php
/**
 * Various shortcodes used in our themes.
 *
 * @link       https://pixelgrade.com
 * @since      1.2.1
 *
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/ThemeHelpers
 */

/*++++++++++++++++++++++++*/
/**
 * Load the Pixelgrade Nova Menu component.
 * https://pixelgrade.com/
 */

/**
 * Returns the main instance of Pixelgrade_Nova_Menu to prevent the need to use globals.
 *
 * @since  1.2.1
 * @return Pixelgrade_Nova_Menu
 */
function PixCare_Nova_Menu() {
	//only load if we have to
	if ( ! class_exists( 'Pixelgrade_Nova_Menu') ) {
		require_once( plugin_dir_path( __FILE__ ) . 'nova-menu/class-nova-menu.php' );
	}
	return Pixelgrade_Nova_Menu::instance();
}

// Load The Nova Menu
$nova_menu_instance = PixCare_Nova_Menu();
/*------------------------*/

/**
 * Add the Page Shortcode used in heroes mainly
 *
 * @param array $atts
 *
 * @return string
 */
function pixelgrade_create_page_shortcode( $atts ) {
	$output = '';

	// Attributes
	extract( shortcode_atts(
			array(
				'id' => '',
			), $atts )
	);

	$post = get_the_ID();

	if ( ! empty( $id ) && intval( $id ) ) {
		$post = intval( $id );
	}

	if ( in_array( 'title', $atts ) || in_array( 'Title', $atts ) ) {
		$output .= get_the_title( $post );
	}

	return $output;
}
// we will register the shortcode with both lovercase and uppercase
add_shortcode( 'page', 'pixelgrade_create_page_shortcode' );
add_shortcode( 'Page', 'pixelgrade_create_page_shortcode' );
