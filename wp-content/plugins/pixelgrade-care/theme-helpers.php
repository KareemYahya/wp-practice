<?php
/**
 * This is where we load all the various theme helpers.
 *
 * @link       https://pixelgrade.com
 * @since      1.2.1
 *
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/ThemeHelpers
 */

/*
 * Load our helper shortcodes
 */
require_once( plugin_dir_path( __FILE__ ) . 'theme-helpers/shortcodes.php' );

/*
 * Load our Jetpack settings customization helper class
 */
require_once( plugin_dir_path( __FILE__ ) . 'theme-helpers/jetpack_customization.php' );

/*
 * Load our theme dependent functionality
 */
require_once( plugin_dir_path( __FILE__ ) . 'theme-helpers/theme-dependent.php' );
