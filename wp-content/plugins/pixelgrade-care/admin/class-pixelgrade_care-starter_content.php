<?php
/**
 *
 * Class responsable for the Starter Content Component
 * Basically this is an Import Demo Data system
 *
 * @since    1.1.6
 * @package    PixelgradeCare
 * @subpackage PixelgradeCare/admin
 * @author     Pixelgrade <email@example.com>
 */
class PixelgradeCareStarterContent {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.6
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.1.6
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.1.6
	 *
	 * @param      string $plugin_name The name of this plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'rest_api_init', array( $this, 'add_rest_routes_api' ) );

		add_filter( 'pixcare_localized_data', array( $this, 'localize_js_data' ) );

		if ( apply_filters( 'pixcare_sce_allow_options_filtering', true ) ) {
			add_filter( 'pixcare_sce_import_post_option_page_on_front', array( $this, 'filter_post_option_page_on_front' ) );
			add_filter( 'pixcare_sce_import_post_option_page_for_posts', array( $this, 'filter_post_option_page_for_posts' ) );
			add_filter( 'pixcare_sce_import_post_theme_mod_nav_menu_locations', array( $this, 'filter_post_theme_mod_nav_menu_locations' ) );
			add_filter( 'pixcare_sce_import_post_theme_mod_custom_logo', array( $this, 'filter_post_theme_mod_custom_logo' ) );
			//widgets
			add_filter( 'pixcare_sce_import_widget_nav_menu', array( $this, 'filter_menu_widgets' ), 10, 2 );

			// content links
			add_action( 'pixcare_sce_after_insert_post', array( $this, 'prepare_menus_links' ), 10, 2 );
			add_action( 'pixcare_sce_import_end', array( $this, 'end_import' ) );
		}
	}

	/** PixCare hookables */
	function localize_js_data( $localized_data ) {
		$starter_content = $this->get_option( 'imported_starter_content' );

		if ( ! empty( $starter_content ) ) {
			$localized_data['themeMod']['starterContent'] = $starter_content;
		}

		return $localized_data;
	}

	/** RESTful methods */
	function add_rest_routes_api() {
		register_rest_route( 'pixcare/v1', '/import', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_import_step' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
		) );

		register_rest_route( 'pixcare/v1', '/upload_media', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_upload_media' ),
			'permission_callback' => array( $this, 'permission_nonce_callback' ),
			'args'                => array(
				'file_data'     => array( 'required' => true ),
				'title'         => array( 'required' => true ),
				'group'         => array( 'required' => true ),
				'ext'           => array( 'required' => true ),
				'remote_id'     => array( 'required' => true ),
				'pixcare_nonce' => array( 'required' => true ),
			)
		) );
	}

	/**
	 * The callback for POST `pixcare/v1/upload_media` request
	 * @return mixed|void|WP_REST_Response
	 */
	function rest_upload_media() {
		$display_errors = @ini_set( 'display_errors', 0 );

		if( ob_get_length() ) {
			ob_get_clean();
		}

		$wp_upload_dir = wp_upload_dir();

		$group = $_POST['group'];

		$title = $_POST['title'];

		$remote_id = $_POST['remote_id'];

		$filename = $title . '.' . $_POST['ext']; //basename( $file_path );

		$file_path = trailingslashit( $wp_upload_dir['path'] ) . $filename;

		$file_data = $this->decode_chunk( $_POST['file_data'] );

		if ( false === $file_data ) {
			@ini_set( 'display_errors', $display_errors );
			wp_send_json_error( 'no data' );
		}

		$upload_file = wp_upload_bits( $filename, null, $file_data );

		if ( $upload_file['error'] ) {
			@ini_set( 'display_errors', $display_errors );
			return rest_ensure_response( 'File permission error' );
		}

		$wp_filetype = wp_check_filetype( $filename, null );

		$attachment = array(
			'guid'           => $upload_file['url'],
			'post_mime_type' => $wp_filetype['type'],
			'post_parent'    => 0,
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'] );

		if ( ! is_wp_error( $attachment_id ) ) {
			// cache posts already imported
			$this->get_option( 'imported_starter_content' );

			if ( ! isset( $this->options['imported_starter_content'] ) ) {
				$this->options['imported_starter_content'] = array(
					'media' => array(
						$group => array()
					)
				);
			}

			$this->options['imported_starter_content']['media'][ $group ][ $remote_id ] = $attachment_id;

			$this->save_options();

			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );

			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );

			$attachment_data['imported_with_pixcare'] = true;

			wp_update_attachment_metadata( $attachment_id, $attachment_data );

			@ini_set( 'display_errors', $display_errors );

			return wp_send_json_success( $attachment_id );
		}

		@ini_set( 'display_errors', $display_errors );

		return wp_send_json_error( array( $attachment_id ) );
	}

	function rest_import_step() {
		$display_errors = @ini_set( 'display_errors', 0 );

		// clear whatever was printed before, we only need a pure json
		if( ob_get_length() ) {
			ob_get_clean();
		}

		// we need to import posts without the intervetion of cache system
		wp_suspend_cache_invalidation( true );

		if ( empty( $_POST['args'] ) || empty( $_POST['type'] ) || empty( $_POST['url'] ) ) {
			@ini_set( 'display_errors', $display_errors );
			return rest_ensure_response( 'Not enough params!' );
		}

		$base_url = $_POST['url'];
		$type     = $_POST['type'];
		$args     = $_POST['args'];

		switch ( $type ) {
			case 'post_type': {

				$response = $this->import_post_type( $base_url, $args );
				$output = rest_ensure_response( $response );
				break;
			}

			case 'taxonomy': {

				$response = $this->import_taxonomy( $base_url, $args );
				$output = rest_ensure_response( $response );
				break;
			}

			case 'widgets': {

				if ( empty( $args['data'] ) ) {
					break;
				}

				$response = $this->import_widgets( $args['data'] );
				$output = rest_ensure_response( $response );
				break;
			}

			case 'pre_settings': {

				if ( empty( $args['data'] ) ) {
					break;
				}

				$response = $this->import_settings( 'pre', $args['data'] );
				$output = rest_ensure_response( $response );
				break;
			}

			case 'post_settings': {
				if ( empty( $args['data'] ) ) {
					break;
				}

				$response = $this->import_settings( 'post', $args['data'] );
				$output = rest_ensure_response( $response );
				break;
			}

			default :
				$output = rest_ensure_response('Nothing here');
				break;
		}

		// add cache invalidation as before
		wp_suspend_cache_invalidation( false );

		@ini_set( 'display_errors', $display_errors );

		return rest_ensure_response( $output );
	}

	private function import_post_type( $base_url, $args = array() ) {
		$imported_ids = array();

		if ( empty( $args['ids'] ) ) {
			return false;
		}

		// cache posts already imported
		$starter_content = $this->get_option( 'imported_starter_content' );

		$this_url = $base_url . '/wp-json/sce/v1/posts';

		$response = wp_remote_post( $this_url, array(
			'body' => array(
				'post_type'      => $args['post_type'],
				'include'        => implode( ',', $args['ids'] ),
				'placeholders'   => $this->get_placeholders(),
				'ignored_images' => $this->get_ignored_images()
			),
		) );

		$posts = json_decode( $response['body'], true );

		if ( empty( $posts ) ) {
			return false;
		}

		foreach ( $posts as $i => $post ) {
			if ( $this->the_slug_exists( $post['post_name'], $post['post_type'] ) ) {
				// @TODO Send a notification that this post already exists
				continue;
			}

			$post_args = array(
				'import_id'             => $post['ID'],
				'post_title'            => wp_strip_all_tags( $post['post_title'] ),
				'post_content'          => $post['post_content'],
				'post_content_filtered' => $post['post_content_filtered'],
				'post_excerpt'          => $post['post_excerpt'],
				'post_status'           => $post['post_status'],
				'post_name'             => $post['post_name'],
				'post_type'             => $post['post_type'],
				'post_date'             => $post['post_date'],
				'post_date_gmt'         => $post['post_date_gmt'],
				'post_modified'         => $post['post_modified'],
				'post_modified_gmt'     => $post['post_modified_gmt'],
				'menu_order'            => $post['menu_order'],
				'meta_input'            => array(
					'imported_with_pixcare' => true
				)
			);

			if ( ! empty( $post['meta'] ) ) {

				$must_break = false;

				foreach ( $post['meta'] as $key => $meta ) {

					if ( ! empty( $meta ) ) {
						// we only need  the first value
						if ( isset( $meta[0] ) ) {
							$meta = $meta[0];
						}
						$meta = maybe_unserialize( $meta );
					}

					if ( $key === '_menu_item_object' && $meta === 'post_format' ) {
						$must_break = true;
						break;
					}
					$post_args['meta_input'][ $key ] = apply_filters( 'sce_pre_postmeta', $meta, $key );
				}

				if ( $must_break ) {
					continue;
				}
			}

			if ( ! empty( $post['taxonomies'] ) ) {
				$post_args['post_category'] = array();
				$post_args['tax_input']     = array();

				foreach ( $post['taxonomies'] as $taxonomy => $terms ) {

					if ( ! taxonomy_exists( $taxonomy ) ) {
						// @TODO inform the user that the taxonomy doesn't exist and maybe he should install a plugin
						continue;
					}

					$post_args['tax_input'][ $taxonomy ] = array();

					foreach ( $terms as $term ) {
						if ( is_numeric( $term ) && isset( $starter_content['taxonomies'][ $taxonomy ][ $term ] ) ) {
							$term = $starter_content['taxonomies'][ $taxonomy ][ $term ];
						}

						$post_args['tax_input'][ $taxonomy ][] = $term;
					}
				}
			}

			$post_id = wp_insert_post( $post_args );

			if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
				// well ... error
				$imported_ids[ $post['ID'] ] = $post_id;
			} else {
				$imported_ids[ $post['ID'] ] = $post_id;
			}
		}

		// post processing
		foreach ( $posts as $i => $post ) {
			$update_this = false;

			if ( ! isset( $imported_ids[ $post['ID'] ] ) ) {
				continue;
			}

			$update_args = array(
				'ID' => $imported_ids[ $post['ID'] ],
			);

			// bind parents after we have all the posts
			if ( ! empty( $post['post_parent'] ) && isset( $imported_ids[ $post['post_parent'] ] ) ) {
				$update_args['post_parent'] = $imported_ids[ $post['post_parent'] ];
				$update_this                = true;
			}

			// recheck the guid
			$new_perm = get_permalink( $post['ID'] );

			// if the guid takes the place of the permalink, rebase it
			if ( ! empty( $new_perm ) && ! is_numeric( $post['guid'] ) ) {
				$update_args['guid'] = $new_perm;
				$update_this         = true;
			}

			if ( $update_this ) {
				wp_update_post( $update_args );
			}

			do_action( 'pixcare_sce_after_insert_post', $post, $imported_ids );
		}

		$this->options['imported_starter_content']['post_types'][ $args['post_type'] ] = $imported_ids;

		$this->save_options();

		return $this->options['imported_starter_content']['post_types'][ $args['post_type'] ];
	}

	private function import_taxonomy( $base_url, $args ) {
		$imported_ids = array();

		if ( empty( $args['ids'] ) ) {
			return false;
		}

		if ( ! taxonomy_exists( $args['tax'] ) ) {
			return rest_ensure_response( $args['tax'] . ' does not exists!' );
		}

		// to cache terms already imported
		$starter_content = $this->get_option( 'imported_starter_content' );

		$this_url = $base_url . '/wp-json/sce/v1/terms?include=' . implode( ',', $args['ids'] ) . '&taxonomy=' . $args['tax'];

		$response = wp_remote_get( $this_url );

		$terms = json_decode( $response['body'], true );

		if ( empty( $terms ) ) {
			return false;
		}

		foreach ( $terms as $i => $term ) {

			$term_args = array(
				'description' => $term['description'],
				'slug'        => $term['slug'],
			);

			$new_id = wp_insert_term(
				$term['name'], // the term
				$term['taxonomy'], // the taxonomy
				$term_args
			);

			if ( is_wp_error( $new_id ) ) {
				$imported_ids[ $term['term_id'] ] = $new_id->error_data;
			} else {
				$imported_ids[ $term['term_id'] ] = $new_id['term_id'];

				if ( ! empty( $term['meta'] ) ) {

					foreach ( $term['meta'] as $key => $meta ) {
						$value = false;
						if ( ! $value && isset( $meta[0] ) ) {
							$value = maybe_unserialize( $meta[0] );
						}

						if ( 'pix_term_icon' === $key && isset( $starter_content['media']['ignored'][ $value ] ) ) {
							$value = $starter_content['media']['ignored'][ $value ];
						}

						update_term_meta( $new_id['term_id'], $key, $value );
					}
					update_term_meta( $new_id['term_id'], 'imported_with_pixcare', true );
				}
			}
		}

		// bind the parents
		foreach ( $terms as $i => $term ) {
			if ( ! empty( $term['parent'] ) && isset( $imported_ids[ $term['parent'] ] ) ) {
				wp_update_term( $imported_ids[ $term['term_id'] ], $args['tax'], array(
					'parent' => $imported_ids[ $term['parent'] ]
				) );
			}
		}

//		if ( isset( $starter_content['taxonomies'][ $args['tax'] ] ) ) {
//			$this->options['imported_starter_content']['taxonomies'][ $args['tax'] ] = array_merge( $starter_content['taxonomies'][ $args['tax'] ], $imported_ids );
//		} else {
		$this->options['imported_starter_content']['taxonomies'][ $args['tax'] ] = $imported_ids;
//		}

		$this->save_options();

		return $this->options['imported_starter_content']['taxonomies'][ $args['tax'] ];
	}

	private function import_settings( $type, $data ) {
		if ( ! is_array( $data ) ) {
			$data = json_decode( $data, true );
		}

		$settings_key = $type . '_settings';

		if ( empty( $data ) ) {
			return false;
		}

		$imported_content = $this->get_option( 'imported_starter_content' );

		if ( ! isset( $imported_content[ $settings_key ] ) ) {
			$imported_content[ $settings_key ] = array();
		}

		if ( ! empty( $data['mods'] ) ) {

			if ( ! isset( $imported_content[ $settings_key ]['mods'] ) ) {
				$imported_content[ $settings_key ]['mods'] = array();
			}

			foreach ( $data['mods'] as $mod => $value ) {
				$imported_content[ $settings_key ]['mods'][ $mod ] = get_theme_mod( $mod );

				$value = apply_filters( "pixcare_sce_import_{$type}_theme_mod_{$mod}", $value );

				set_theme_mod( $mod, $value );
			}
		}

		if ( ! empty( $data['options'] ) ) {
			if ( ! isset( $imported_content[ $settings_key ]['options'] ) ) {
				$imported_content[ $settings_key ]['options'] = array();
			}

			foreach ( $data['options'] as $option => $value ) {
				$imported_content[ $settings_key ]['options'][ $option ] = get_option( $option );

				$value = apply_filters( "pixcare_sce_import_{$type}_option_{$option}", $value );

				update_option( $option, $value );
			}
		}

		if ( 'pre' === $type ) {
			do_action( 'pixcare_sce_import_start' );
		}


		if ( 'post' === $type ) {
			do_action( 'pixcare_sce_import_end' );
		}

		$this->options['imported_starter_content'] = $imported_content;

		$this->save_options();

		return true;
	}

	private function import_widgets( $data ) {

		if ( empty( $data ) ) {
			return false;
		}

		$imported_content = $this->get_option( 'imported_starter_content' );

		//first let's remove all the widgets in the sidebars to avoid a big mess
		$sidebars_widgets = wp_get_sidebars_widgets();
		foreach ( $sidebars_widgets as $sidebarID => $widgets ) {
			if ( $sidebarID != 'wp_inactive_widgets' ) {
				$sidebars_widgets[ $sidebarID ] = array();
			}
		}

		wp_set_sidebars_widgets( $sidebars_widgets );

		//let's get to work
		$json_data = json_decode( base64_decode( $data ), true );

		$sidebar_data = $json_data[0];
		$widget_data  = $json_data[1];

		foreach ( $sidebar_data as $title => $sidebar ) {
			$count = count( $sidebar );
			for ( $i = 0; $i < $count; $i ++ ) {
				$widget               = array();
				$widget['type']       = trim( substr( $sidebar[ $i ], 0, strrpos( $sidebar[ $i ], '-' ) ) );
				$widget['type-index'] = trim( substr( $sidebar[ $i ], strrpos( $sidebar[ $i ], '-' ) + 1 ) );
				if ( ! isset( $widget_data[ $widget['type'] ][ $widget['type-index'] ] ) ) {
					unset( $sidebar_data[ $title ][ $i ] );
				}
			}
			$sidebar_data[ $title ] = array_values( $sidebar_data[ $title ] );
		}

		$sidebar_data = array( array_filter( $sidebar_data ), $widget_data );

		$this->options['imported_starter_content']['widgets'] = false;

		if ( ! $this->parse_import_data( $sidebar_data ) ) {

			$this->options['imported_starter_content']['widgets'] = true;

			$this->save_options();

			return false;
		}

		return $this->options['imported_starter_content']['widgets'];
	}

	/**
	 * Widgets helpers
	 */
	private function parse_import_data( $import_array ) {
		$sidebars_data = $import_array[0];
		$widget_data   = $import_array[1];

		$current_sidebars = get_option( 'sidebars_widgets' );
		$new_widgets      = array();

		foreach ( $sidebars_data as $import_sidebar => $import_widgets ) :
			$current_sidebars[ $import_sidebar ] = array();
			foreach ( $import_widgets as $import_widget ) :

				//if the sidebar exists
				//if ( isset( $current_sidebars[$import_sidebar] ) ) :
				$title               = trim( substr( $import_widget, 0, strrpos( $import_widget, '-' ) ) );
				$index               = trim( substr( $import_widget, strrpos( $import_widget, '-' ) + 1 ) );
				$current_widget_data = get_option( 'widget_' . $title );
				$new_widget_name     = $this->get_new_widget_name( $title, $index );
				$new_index           = trim( substr( $new_widget_name, strrpos( $new_widget_name, '-' ) + 1 ) );

				if ( ! empty( $new_widgets[ $title ] ) && is_array( $new_widgets[ $title ] ) ) {
					while ( array_key_exists( $new_index, $new_widgets[ $title ] ) ) {
						$new_index ++;
					}
				}
				$current_sidebars[ $import_sidebar ][] = $title . '-' . $new_index;
				if ( array_key_exists( $title, $new_widgets ) ) {
					$new_widgets[ $title ][ $new_index ] = $widget_data[ $title ][ $index ];
					if ( ! empty( $new_widgets[ $title ]['_multiwidget'] ) ) {
						$multiwidget = $new_widgets[ $title ]['_multiwidget'];
						unset( $new_widgets[ $title ]['_multiwidget'] );
						$new_widgets[ $title ]['_multiwidget'] = $multiwidget;
					} else {
						$new_widgets[ $title ]['_multiwidget'] = null;
					}
				} else {
					$current_widget_data[ $new_index ] = $widget_data[ $title ][ $index ];
					if ( ! empty( $current_widget_data['_multiwidget'] ) ) {
						$current_multiwidget = $current_widget_data['_multiwidget'];
						$new_multiwidget     = $widget_data[ $title ]['_multiwidget'];
						$multiwidget         = ( $current_multiwidget != $new_multiwidget ) ? $current_multiwidget : 1;
						unset( $current_widget_data['_multiwidget'] );
						$current_widget_data['_multiwidget'] = $multiwidget;
					} else {
						$current_widget_data['_multiwidget'] = null;
					}
					$new_widgets[ $title ] = $current_widget_data;
				}

				//endif;
			endforeach;
		endforeach;

		if ( isset( $new_widgets ) && isset( $current_sidebars ) ) {
			update_option( 'sidebars_widgets', $current_sidebars );

			foreach ( $new_widgets as $title => $content ) {

				$content = apply_filters( "pixcare_sce_import_widget_{$title}", $content, $title );

				update_option( 'widget_' . $title, $content );
			}

			return true;
		}

		return false;
	}

	private function get_new_widget_name( $widget_name, $widget_index ) {
		$current_sidebars = get_option( 'sidebars_widgets' );
		$all_widget_array = array();
		foreach ( $current_sidebars as $sidebar => $widgets ) {
			if ( ! empty( $widgets ) && is_array( $widgets ) && $sidebar != 'wp_inactive_widgets' ) {
				foreach ( $widgets as $widget ) {
					$all_widget_array[] = $widget;
				}
			}
		}
		while ( in_array( $widget_name . '-' . $widget_index, $all_widget_array ) ) {
			$widget_index ++;
		}
		$new_widget_name = $widget_name . '-' . $widget_index;

		return $new_widget_name;
	}

	/** CUSTOM FILTERS */
	function prepare_menus_links( $post, $imported_ids ) {

		if ( 'nav_menu_item' !== $post['post_type'] ) {
			return;
		}

		/**
		 * We need to remap the nav menu item parent
		 */
		if ( 'nav_menu_item' !== $post['post_type'] && isset( $post['meta']['_menu_item_menu_item_parent'] ) ) {
			if ( ! empty( $post['meta']['_menu_item_menu_item_parent'] ) && isset( $imported_ids[ $post['meta']['_menu_item_menu_item_parent'] ] ) ) {
				update_post_meta( $imported_ids[ $post['ID'] ], '_menu_item_menu_item_parent', $imported_ids[ $post['meta']['_menu_item_menu_item_parent'] ] );
			}
		}

		$starter_content     = $this->get_option( 'imported_starter_content' );
		$menu_item_type      = maybe_unserialize( $post['meta']['_menu_item_type'] );
		$menu_item_type      = wp_slash( $menu_item_type[0] );
		$menu_item_object    = maybe_unserialize( $post['meta']['_menu_item_object'] );
		$menu_item_object    = wp_slash( $menu_item_object[0] );
		$menu_item_object_id = maybe_unserialize( $post['meta']['_menu_item_object_id'] );
		$menu_item_object_id = wp_slash( $menu_item_object_id[0] );

		// try to remmap custom objects in nav items
		switch ( $menu_item_type ) {
			case 'taxonomy':
				if ( isset( $starter_content['taxonomies'][ $menu_item_object ] ) ) {
					$menu_item_object_id = $starter_content['taxonomies'][ $menu_item_object ][ $menu_item_object_id ];
				}
				break;
			case 'post_type':
				$menu_item_object_id = $starter_content['post_types'][ $menu_item_object ][ $menu_item_object_id ];
				break;
			case 'custom':
				/**
				 * Remap custom links
				 */
				$meta_url = get_post_meta( $post['ID'], '_menu_item_url', true );
				if ( isset( $_POST['url'] ) && ! empty( $meta_url ) ) {
					$meta_url = str_replace( $_POST['url'], site_url(), $meta_url );
					update_post_meta( $post['ID'], '_menu_item_url', $meta_url );
				}
				break;
			default:
				// no clue
				break;
		}

		update_post_meta( $imported_ids[ $post['ID'] ], '_menu_item_object_id', wp_slash( $menu_item_object_id ) );
	}

	function end_import() {
		$this->replace_demo_urls_in_content();
	}

	/**
	 * Here we need to re-map all the link inside the post content
	 * @TODO this is awful, we need to better handle this
	 */
	private function replace_demo_urls_in_content() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $_POST['url'], site_url() ) );

		// remap enclosure urls
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $_POST['url'], site_url() ) );
	}

	/**
	 * Replace the value of the `page_on_front` option with the id of the local front page
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	function filter_post_option_page_on_front( $value ) {
		$starter_content = $this->get_option( 'imported_starter_content' );
		if ( isset( $starter_content['post_types']['page'][ $value ] ) ) {
			return $starter_content['post_types']['page'][ $value ];
		}

		return $value;
	}

	/**
	 * Replace the value of the `page_for_posts` option with the id of the local blog page
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	function filter_post_option_page_for_posts( $value ) {
		$starter_content = $this->get_option( 'imported_starter_content' );
		if ( isset( $starter_content['post_types']['page'][ $value ] ) ) {
			return $starter_content['post_types']['page'][ $value ];
		}

		return $value;
	}

	/**
	 * Replace each menu id from `nav_menu_locations` with the new menus ids
	 *
	 * @param $locations
	 *
	 * @return mixed
	 */
	function filter_post_theme_mod_nav_menu_locations( $locations ) {
		if ( empty( $locations ) ) {
			return $locations;
		}

		$starter_content = $this->get_option( 'imported_starter_content' );

		foreach ( $locations as $location => $menu ) {
			if ( ! empty( $starter_content['taxonomies']['nav_menu'][ $menu ] ) ) {
				$locations[ $location ] = $starter_content['taxonomies']['nav_menu'][ $menu ];
			}
		}

		return $locations;
	}

	/**
	 * If there is a custom logo set, it will surely come with another attachment_id
	 * Wee need to replace the old attachment id with the local one
	 *
	 * @param $attach_id
	 *
	 * @return mixed
	 */
	function filter_post_theme_mod_custom_logo( $attach_id ) {
		if ( empty( $attach_id ) ) {
			return $attach_id;
		}

		$starter_content = $this->get_option( 'imported_starter_content' );

		if ( ! empty( $starter_content['media']['ignored'][ $attach_id ] ) ) {
			return $starter_content['media']['ignored'][ $attach_id ];
		}

		if ( ! empty( $starter_content['media']['placeholders'][ $attach_id ] ) ) {
			return $starter_content['media']['placeholders'][ $attach_id ];
		}

		return $attach_id;
	}

	function filter_menu_widgets( $widget_data, $title ) {
		$starter_content = $this->get_option( 'imported_starter_content' );

		foreach ( $widget_data as $widget_key => $widget ) {
			if ( '_multiwidget' === $widget_key ) {
				continue;
			}

			if ( ! isset( $widget_data[ $widget_key ]['nav_menu'] ) ) {
				continue;
			}

			$id = $widget_data[ $widget_key ]['nav_menu'];

			if ( isset( $starter_content['taxonomies']['nav_menu'][ $id ] ) ) {
				$widget_data[ $widget_key ]['nav_menu'] = $starter_content['taxonomies']['nav_menu'][ $id ];
			}
		}

		return $widget_data;
	}
	/** END CUSTOM FILTERS */

	/**
	 * HELPERS
	 */
	private function get_placeholders() {
		$imported_ids = array();

		if ( ! empty( $this->options['imported_starter_content']['media']['placeholders'] ) ) {
			foreach ( $this->options['imported_starter_content']['media']['placeholders'] as $old_id => $new_id ) {
				$src                     = wp_get_attachment_image_src( $new_id, 'full' );
				$imported_ids[ $old_id ] = array(
					'id'    => $new_id,
					'sizes' => array(
						'full' => $src[0]
					)
				);

				foreach ( get_intermediate_image_sizes() as $size ) {
					$src                                       = wp_get_attachment_image_src( $new_id, $size );
					$imported_ids[ $old_id ]['sizes'][ $size ] = $src[0];
				}
			}
		}

		return $imported_ids;
	}

	private function get_ignored_images() {
		$imported_ids = array();

		if ( ! empty( $this->options['imported_starter_content']['media']['ignored'] ) ) {
			foreach ( $this->options['imported_starter_content']['media']['ignored'] as $old_id => $new_id ) {
				$src                     = wp_get_attachment_image_src( $new_id, 'full' );
				$imported_ids[ $old_id ] = array(
					'id'    => $new_id,
					'sizes' => array(
						'full' => $src[0]
					)
				);
			}
		}

		return $imported_ids;
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

	private function decode_chunk( $data ) {
		$data = explode( ';base64,', $data );

		if ( ! is_array( $data ) || ! isset( $data[1] ) ) {
			return false;
		}

		$data = base64_decode( $data[1] );
		if ( ! $data ) {
			return false;
		}

		return $data;
	}

	private function is_pixelgrade_care_dashboard() {
		if ( ! empty( $_GET['page'] ) && 'pixelgrade_care' === $_GET['page'] ) {
			return true;
		}

		return false;
	}

	private function set_options() {
		$this->options = get_option( 'pixcare_options' );
	}

	private function save_options() {
		update_option( 'pixcare_options', $this->options );
	}

	private function get_options() {
		if ( empty( $this->options ) ) {
			$this->set_options();
		}

		return $this->options;
	}

	private function get_option( $option, $default = null ) {
		$options = $this->get_options();

		if ( ! empty( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		if ( $default !== null ) {
			return $default;
		}

		return null;
	}

	private function the_slug_exists( $post_name, $post_type ) {
		global $wpdb;
		if ( $wpdb->get_row( "SELECT post_name FROM $wpdb->posts WHERE post_name = '" . $post_name . "' AND post_type = '" . $post_type . "'", 'ARRAY_A' ) ) {
			return true;
		} else {
			return false;
		}
	}

	private function debug_a_random_request() {
		$ret = $this->import_post_type( 'https://demos.pixelgrade.com/listable', array(
			'post_type' => 'nav_menu_item',
			'ids'       => array(
				"4479",
				"4478",
				"3735",
				"3655",
				"3654",
				"3653",
				"3657",
				"3656",
				"3644",
				"3639",
				"3638",
				"3636",
				"2946",
				"1449",
				"1443",
				"1442",
				"1441",
				"1438",
				"1437",
				"1436",
				"994",
				"993",
				"980",
				"927",
				"921",
				"920",
				"919",
				"918",
				"917",
				"916",
				"915",
				"914",
				"913",
				"912",
				"911",
				"910",
				"909",
				"908",
				"907",
				"905",
				"904",
				"903",
				"861",
				"770",
				"769",
				"766",
				"763",
				"762",
				"761",
				"94",
				"93",
				"89",
				"85",
				"84",
				"82",
				"52",
				"50",
				"88"
			)
		) );

		var_dump( $ret );

//		foreach ( $ret as $r ) {
//			print_r( $r ['post_content']);
//		}
	}
}
