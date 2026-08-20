<?php

class PGM_Visibility_API {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'pgm/v1', '/roles', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_roles' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_role' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_role' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_role' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			),
		) );

		register_rest_route( 'pgm/v1', '/items', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_items' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			),
		) );
		
		register_rest_route( 'pgm/v1', '/items/roles', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_item_roles' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			),
		) );
	}

	public static function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	public static function get_roles() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		
		$roles = array();
		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			$roles[] = array(
				'id' => $role_key,
				'name' => $role_data['name'],
				'count' => 0
			);
		}

		return rest_ensure_response( $roles );
	}

	public static function create_role( WP_REST_Request $request ) {
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		if ( empty( $name ) ) {
			return new WP_Error( 'empty_name', 'Nimi puuttuu', array( 'status' => 400 ) );
		}
		$id = 'pgm_sub_' . sanitize_key( $name );
		if ( get_role( $id ) ) {
			return new WP_Error( 'role_exists', 'Rooli on jo olemassa', array( 'status' => 400 ) );
		}
		add_role( $id, $name, array( 'read' => true ) );
		return rest_ensure_response( array( 'success' => true, 'id' => $id, 'name' => $name ) );
	}

	public static function update_role( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function delete_role( WP_REST_Request $request ) {
		$id = sanitize_key( $request->get_param( 'id' ) );
		if ( strpos( $id, 'pgm_sub_' ) === 0 ) {
			remove_role( $id );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function get_items( WP_REST_Request $request ) {
		$original_error_reporting = error_reporting(0);
		$items = array();
		
		$pages = get_posts( array(
			'post_type' => 'page',
			'posts_per_page' => -1,
			'post_status' => 'publish',
		) );

		foreach ( $pages as $page ) {
			$roles = get_post_meta( $page->ID, '_pgm_visibility_roles', true );
			$roles = is_array( $roles ) ? $roles : array();
			
			$protection_type = get_post_meta( $page->ID, '_pgm_protection_type', true );
			if ( empty( $protection_type ) ) {
				$protection_type = 'none';
			}
			
			$is_protected = false;
			
			// Check if page has WP password
			if ( ! empty( $page->post_password ) ) {
				$protection_type = 'password';
				$is_protected = true;
			}
			
			// Check if FloAuth extranet protects this page
			if ( function_exists('floauth_get_extranet_post_id') ) {
				$extranet_id = (int) floauth_get_extranet_post_id();
				if ( $extranet_id !== 0 ) {
					$ancestors = get_post_ancestors( $page->ID );
					if ( $extranet_id === $page->ID || in_array( $extranet_id, $ancestors ) ) {
						$protection_type = 'floauth';
						$is_protected = true;
					}
				}
			}
			
			// Custom Roles override
			if ( $protection_type === 'roles' || ( ! empty( $roles ) && $protection_type !== 'floauth' ) ) {
				$protection_type = 'roles';
				$is_protected = true;
			} else if ( $protection_type === 'logged_in' || $protection_type === 'floauth' ) {
				$is_protected = true;
			}

			$linked_folders = get_post_meta( $page->ID, '_pgm_linked_folders', true );
			if ( ! is_array( $linked_folders ) ) {
				$old_linked = get_post_meta( $page->ID, '_pgm_linked_folder', true );
				$linked_folders = ! empty( $old_linked ) ? array( $old_linked ) : array();
			}
			$no_access_message = get_post_meta( $page->ID, '_pgm_no_access_message', true );

			$items[] = array(
				'id' => 'p_' . $page->ID,
				'post_id' => $page->ID,
				'title' => $page->post_title,
				'url' => get_permalink( $page->ID ),
				'type' => 'page',
				'allowedRoles' => $roles,
				'isProtected' => $is_protected,
				'protectionType' => $protection_type,
				'linkedFolders' => $linked_folders,
				'noAccessMessage' => $no_access_message
			);
		}

		// Fetch Media Folders
		$foldersList = array();
		if ( class_exists( 'PGM_Media_Organizer' ) ) {
			$folders = get_terms( array(
				'taxonomy'   => PGM_Media_Organizer::TAXONOMY,
				'hide_empty' => false,
			) );

			if ( ! is_wp_error( $folders ) && ! empty( $folders ) ) {
				$children = array();
				foreach ( $folders as $folder ) {
					$folder->pgm_order = (int) get_term_meta( $folder->term_id, PGM_Media_Organizer::ORDER_META_KEY, true );
					$children[ $folder->parent ][] = $folder;
				}
				
				foreach ( $children as $parent_id => &$child_list ) {
					usort( $child_list, function( $a, $b ) {
						if ( $a->pgm_order === $b->pgm_order ) {
							return strcasecmp( $a->name, $b->name );
						}
						return $a->pgm_order <=> $b->pgm_order;
					});
				}
				
				$traverse = function( $parent_id, $depth ) use ( &$traverse, &$children, &$foldersList ) {
					if ( ! isset( $children[ $parent_id ] ) ) {
						return;
					}
					foreach ( $children[ $parent_id ] as $folder ) {
						$foldersList[] = array(
							'id'             => (string) $folder->term_id,
							'title'          => $folder->name,
							'depth'          => $depth
						);
						$traverse( $folder->term_id, $depth + 1 );
					}
				};
				
				$traverse( 0, 0 );
			}
		}

		$response_data = array(
			'items'   => $items,
			'folders' => $foldersList
		);
		file_put_contents( WP_CONTENT_DIR . '/pgm_api_debug.log', date('Y-m-d H:i:s') . " - Items count: " . count($items) . "\n", FILE_APPEND );
		error_reporting($original_error_reporting);
		return rest_ensure_response( $response_data );
	}

	public static function save_item_roles( WP_REST_Request $request ) {
		$item_id = sanitize_text_field( $request->get_param( 'itemId' ) );
		$roles = $request->get_param( 'roles' );
		$roles = is_array( $roles ) ? array_map( 'sanitize_key', $roles ) : array();
		
		$protection_type = sanitize_text_field( $request->get_param( 'protectionType' ) );
		$linked_folders = $request->get_param( 'linkedFolders' );
		$no_access_message = $request->get_param( 'noAccessMessage' );

		if ( strpos( $item_id, 'p_' ) === 0 ) {
			$post_id = intval( substr( $item_id, 2 ) );
			update_post_meta( $post_id, '_pgm_visibility_roles', $roles );
			if ( ! empty( $protection_type ) ) {
				update_post_meta( $post_id, '_pgm_protection_type', $protection_type );
			}
			
			if ( $no_access_message !== null ) {
				update_post_meta( $post_id, '_pgm_no_access_message', wp_kses_post( $no_access_message ) );
			}
			
			if ( $linked_folders !== null ) {
				$linked_folders = is_array( $linked_folders ) ? array_map( 'sanitize_text_field', $linked_folders ) : array();
				$old_folders = get_post_meta( $post_id, '_pgm_linked_folders', true );
				if ( ! is_array( $old_folders ) ) {
					$old_linked = get_post_meta( $post_id, '_pgm_linked_folder', true );
					$old_folders = ! empty( $old_linked ) ? array( $old_linked ) : array();
				}

				update_post_meta( $post_id, '_pgm_linked_folders', $linked_folders );
				
				if ( class_exists( 'PGM_Media_Organizer' ) ) {
					// Poistetaan oikeudet kansioista, joita ei enää ole valittuna
					$removed_folders = array_diff( $old_folders, $linked_folders );
					foreach ( $removed_folders as $f_id ) {
						$f_id = intval( $f_id );
						if ( $f_id > 0 ) {
							update_term_meta( $f_id, PGM_Media_Organizer::ACCESS_META_KEY, PGM_Media_Organizer::ACCESS_PUBLIC );
							delete_term_meta( $f_id, PGM_Media_Organizer::ROLES_META_KEY );
							do_action( 'pgm_mo_folder_access_changed', $f_id, PGM_Media_Organizer::ACCESS_PUBLIC, '' );
						}
					}

					// Lisätään/päivitetään oikeudet valittuihin kansioihin
					foreach ( $linked_folders as $f_id ) {
						$f_id = intval( $f_id );
						if ( $f_id > 0 ) {
							$new_access = PGM_Media_Organizer::ACCESS_PUBLIC;
							
							if ( $protection_type === 'none' || empty( $protection_type ) ) {
								update_term_meta( $f_id, PGM_Media_Organizer::ACCESS_META_KEY, PGM_Media_Organizer::ACCESS_PUBLIC );
								delete_term_meta( $f_id, PGM_Media_Organizer::ROLES_META_KEY );
								$new_access = PGM_Media_Organizer::ACCESS_PUBLIC;
							} elseif ( $protection_type === 'logged_in' || $protection_type === 'floauth' ) {
								update_term_meta( $f_id, PGM_Media_Organizer::ACCESS_META_KEY, PGM_Media_Organizer::ACCESS_LOGGED_IN );
								delete_term_meta( $f_id, PGM_Media_Organizer::ROLES_META_KEY );
								$new_access = PGM_Media_Organizer::ACCESS_LOGGED_IN;
							} else {
								if ( ! in_array( 'administrator', $roles, true ) ) {
									$roles[] = 'administrator';
								}
								update_term_meta( $f_id, PGM_Media_Organizer::ACCESS_META_KEY, PGM_Media_Organizer::ACCESS_ROLE_BASED );
								update_term_meta( $f_id, PGM_Media_Organizer::ROLES_META_KEY, $roles );
								$new_access = PGM_Media_Organizer::ACCESS_ROLE_BASED;
							}
							
							update_term_meta( $f_id, '_pgm_linked_page_id', $post_id );
							do_action( 'pgm_mo_folder_access_changed', $f_id, $new_access, '' );
						}
					}
				}
			}

			return rest_ensure_response( array( 'success' => true ) );
		} elseif ( strpos( $item_id, 'f_' ) === 0 && class_exists( 'PGM_Media_Organizer' ) ) {
			$folder_id = intval( substr( $item_id, 2 ) );
			
			if ( empty( $roles ) ) {
				update_term_meta( $folder_id, PGM_Media_Organizer::ACCESS_META_KEY, PGM_Media_Organizer::ACCESS_PUBLIC );
				delete_term_meta( $folder_id, PGM_Media_Organizer::ROLES_META_KEY );
			} else {
				update_term_meta( $folder_id, PGM_Media_Organizer::ACCESS_META_KEY, PGM_Media_Organizer::ACCESS_ROLE_BASED );
				update_term_meta( $folder_id, PGM_Media_Organizer::ROLES_META_KEY, $roles );
			}
			
			do_action( 'pgm_mo_folder_access_changed', $folder_id, empty($roles) ? PGM_Media_Organizer::ACCESS_PUBLIC : PGM_Media_Organizer::ACCESS_ROLE_BASED, '' );
			return rest_ensure_response( array( 'success' => true ) );
		}

		return new WP_Error( 'invalid_id', 'Virheellinen kohde', array( 'status' => 400 ) );
	}
}

PGM_Visibility_API::init();
