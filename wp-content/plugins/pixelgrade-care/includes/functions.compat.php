<?php
/**
 * About this file
 */

if ( ! function_exists('filter_post_theme_mod_osteria_transparent_logo' ) ) {
	/**
	 * @TODO Move this into Osteria theme
	 * If there is a custom logo set, it will surely come with another attachment_id
	 * Wee need to replace the old attachment id with the local one
	 *
	 * @param $attach_id
	 *
	 * @return mixed
	 */
	function filter_post_theme_mod_osteria_transparent_logo( $attach_id ){
		if ( empty( $attach_id ) ) {
			return $attach_id;
		}

		$starter_content = $this->get_option( 'imported_starter_content' );

		if ( ! empty( $starter_content['media']['ignored'][$attach_id] ) ) {
			return $starter_content['media']['ignored'][$attach_id];
		}

		if ( ! empty( $starter_content['media']['placeholders'][$attach_id] ) ) {
			return $starter_content['media']['placeholders'][$attach_id];
		}

		return $attach_id;
	}
}
add_filter( 'pixcare_sce_import_post_theme_mod_osteria_transparent_logo', 'filter_post_theme_mod_osteria_transparent_logo' );
