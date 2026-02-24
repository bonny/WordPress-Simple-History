<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;

/**
 * Logs WordPress menu edits
 */
class Menu_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleMenuLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {

		return array(
			'name'        => __( 'Menu Logger', 'simple-history' ),
			'description' => __( 'Logs menu edits', 'simple-history' ),
			'capability'  => 'edit_theme_options',
			'messages'    => array(
				'created_menu'          => __( 'Created menu "{menu_name}"', 'simple-history' ),
				'edited_menu'           => __( 'Edited menu "{menu_name}"', 'simple-history' ),
				'deleted_menu'          => __( 'Deleted menu "{menu_name}"', 'simple-history' ),
				'edited_menu_item'      => __( 'Edited a menu item', 'simple-history' ),
				'edited_menu_locations' => __( 'Updated menu locations', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Menus', 'Menu logger: search', 'simple-history' ),
					'label_all' => _x( 'All menu activity', 'Menu updates logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Created menus', 'Menu updates logger: search', 'simple-history' ) => array(
							'created_menu',
						),
						_x( 'Edited menus', 'Menu updates logger: search', 'simple-history' ) => array(
							'edited_menu',
							'edited_menu_item',
							'edited_menu_locations',
						),
						_x( 'Deleted menus', 'Menu updates logger: search', 'simple-history' ) => array(
							'deleted_menu',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		/*
		 * Fires after a navigation menu has been successfully deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param int $term_id ID of the deleted menu.
		*/
		add_action( 'load-nav-menus.php', array( $this, 'on_load_nav_menus_page_detect_delete' ) );

		/*
		 * Fires after a navigation menu is successfully created.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $term_id   ID of the new menu.
		 * @param array $menu_data An array of menu data.
		*/
		add_action( 'wp_create_nav_menu', array( $this, 'on_wp_create_nav_menu' ), 10, 2 );

		// This is fired when adding nav items in the editor, not at save, so not
		// good to log because user might not end up saving the changes
		// add_action("wp_update_nav_menu_item", array($this, "on_wp_update_nav_menu_item"), 10, 3 );
		// Fired before "wp_update_nav_menu" below, to remember menu layout before it's updated
		// so we can't detect changes.
		add_action( 'load-nav-menus.php', array( $this, 'on_load_nav_menus_page_detect_update' ) );

		// Detect menu location change in "manage locations".
		add_action( 'load-nav-menus.php', array( $this, 'on_load_nav_menus_page_detect_locations_update' ) );

		add_filter( 'simple_history/categories_logger/skip_taxonomies', array( $this, 'on_categories_logger_skip_taxonomy' ) );
	}

	/**
	 * Add taxonomy "nav_menu" to list of categories to not log changes to,
	 * because menus are stored in this taxonomy,
	 * and the menu logger will log menu changes,
	 * so don't let categories logger log this
	 * or there will be duplicates.
	 *
	 * @param mixed $taxonomies_to_skip Array with taxonomies to skip.
	 * @return array
	 */
	public function on_categories_logger_skip_taxonomy( $taxonomies_to_skip ) {
		$taxonomies_to_skip[] = 'nav_menu';
		return $taxonomies_to_skip;
	}

	/**
	 * Can't use action "wp_delete_nav_menu" because
	 * it's fired after menu is deleted, so we don't have the name in this action
	 */
	public function on_load_nav_menus_page_detect_delete() {
		// Check that needed vars are set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['menu'], $_REQUEST['action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $_REQUEST['action'] !== 'delete' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$menu_id = sanitize_text_field( wp_unslash( $_REQUEST['menu'] ) );
		if ( ! is_nav_menu( $menu_id ) ) {
			return;
		}

		$menu = wp_get_nav_menu_object( $menu_id );

		$this->info_message(
			'deleted_menu',
			array(
				'menu_term_id' => $menu_id,
				'menu_name'    => $menu->name,
			)
		);
	}

	/**
	 * Detect menu being created
	 *
	 * @param int   $term_id ID of the new menu.
	 * @param array $menu_data An array of menu data.
	 */
	public function on_wp_create_nav_menu( $term_id, $menu_data ) {

		$menu = wp_get_nav_menu_object( $term_id );

		if ( ! $menu ) {
			return;
		}

		$this->info_message(
			'created_menu',
			array(
				'term_id'   => $term_id,
				'menu_name' => $menu->name,
			)
		);
	}

	/**
	 * Detect menu being saved
	 */
	public function on_load_nav_menus_page_detect_update() {
		/*
		This is the data to be saved
		$_REQUEST:
		Array
		(
			[action] => update
			[menu] => 25
			[menu-name] => Main menu edit
			[menu-item-title] => Array
				(
					[25243] => My new page edited
					[25244] => My new page
					[25245] => This is my new page. How does it look in the logs? <h1>Hej!</h1>
					[25264] => This page have revisions
					[25265] => Lorem ipsum dolor sit amet
				)
			[menu-locations] => Array
				(
					[primary] => 25
				)
		)
		*/

		// Check that needed vars are set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['menu'], $_REQUEST['action'], $_REQUEST['menu-name'] ) ) {
			return;
		}

		// Only go on for update action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $_REQUEST['action'] !== 'update' ) {
			return;
		}

		// Make sure we got the id of a menu.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$menu_id = sanitize_text_field( wp_unslash( $_REQUEST['menu'] ) );
		if ( ! is_nav_menu( $menu_id ) ) {
			return;
		}

		// Get saved menu. May be empty if this is the first time we save the menu.
		$arr_prev_menu_items = wp_get_nav_menu_items( $menu_id );

		// Build map of old items keyed by db_id.
		$old_items_map = array();
		if ( is_array( $arr_prev_menu_items ) ) {
			foreach ( $arr_prev_menu_items as $item ) {
				$old_items_map[ $item->db_id ] = $item;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_ids = array_values( isset( $_POST['menu-item-db-id'] ) ? (array) wp_unslash( $_POST['menu-item-db-id'] ) : array() );
		$old_ids = array_keys( $old_items_map );

		// Detect added, removed, renamed, moved items.
		$arr_removed = array_diff( $old_ids, $new_ids );
		$arr_added   = array_diff( $new_ids, $old_ids );

		$menu_changes = array(
			'added'         => array(),
			'removed'       => array(),
			'renamed'       => array(),
			'moved'         => array(),
			'order_changed' => false,
		);

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$post_titles      = isset( $_POST['menu-item-title'] ) ? (array) wp_unslash( $_POST['menu-item-title'] ) : array();
		$post_parents     = isset( $_POST['menu-item-parent-id'] ) ? (array) wp_unslash( $_POST['menu-item-parent-id'] ) : array();
		$post_types       = isset( $_POST['menu-item-type'] ) ? (array) wp_unslash( $_POST['menu-item-type'] ) : array();
		$post_type_labels = isset( $_POST['menu-item-type-label'] ) ? (array) wp_unslash( $_POST['menu-item-type-label'] ) : array();
		$post_objects     = isset( $_POST['menu-item-object'] ) ? (array) wp_unslash( $_POST['menu-item-object'] ) : array();
		$post_urls        = isset( $_POST['menu-item-url'] ) ? (array) wp_unslash( $_POST['menu-item-url'] ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		// Added items.
		foreach ( $arr_added as $added_id ) {
			$type_label = isset( $post_type_labels[ $added_id ] ) ? sanitize_text_field( $post_type_labels[ $added_id ] ) : '';

			// Type label is often empty for newly added items, resolve from type/object.
			if ( $type_label === '' ) {
				$type_label = $this->get_menu_item_type_label(
					$post_types[ $added_id ] ?? '',
					$post_objects[ $added_id ] ?? ''
				);
			}

			$change = array(
				'id'    => (int) $added_id,
				'title' => isset( $post_titles[ $added_id ] ) ? sanitize_text_field( $post_titles[ $added_id ] ) : '',
				'type'  => $type_label,
			);

			if ( isset( $post_types[ $added_id ] ) && $post_types[ $added_id ] === 'custom' && ! empty( $post_urls[ $added_id ] ) ) {
				$change['url'] = esc_url_raw( $post_urls[ $added_id ] );
			}

			$menu_changes['added'][] = $change;
		}

		// Removed items.
		foreach ( $arr_removed as $removed_id ) {
			if ( ! isset( $old_items_map[ $removed_id ] ) ) {
				continue;
			}

			$old_item                  = $old_items_map[ $removed_id ];
			$menu_changes['removed'][] = array(
				'id'    => (int) $removed_id,
				'title' => $old_item->title,
				'type'  => $old_item->type_label,
			);
		}

		// Renamed and moved items (items present in both old and new).
		$existing_ids = array_intersect( $new_ids, $old_ids );
		foreach ( $existing_ids as $item_id ) {
			if ( ! isset( $old_items_map[ $item_id ] ) ) {
				continue;
			}

			$old_item  = $old_items_map[ $item_id ];
			$new_title = isset( $post_titles[ $item_id ] ) ? sanitize_text_field( $post_titles[ $item_id ] ) : '';

			// Detect rename.
			if ( $new_title !== '' && $new_title !== $old_item->title ) {
				$menu_changes['renamed'][] = array(
					'id'   => (int) $item_id,
					'from' => $old_item->title,
					'to'   => $new_title,
				);
			}

			// Detect move (parent change).
			$new_parent = isset( $post_parents[ $item_id ] ) ? (int) $post_parents[ $item_id ] : 0;
			$old_parent = (int) $old_item->menu_item_parent;

			if ( $new_parent === $old_parent ) {
				continue;
			}

			$title = $new_title !== '' ? $new_title : $old_item->title;

			// Resolve parent titles for readability.
			$from_label = $this->get_menu_item_parent_label( $old_parent, $old_items_map, $post_titles );
			$to_label   = $this->get_menu_item_parent_label( $new_parent, $old_items_map, $post_titles );

			$menu_changes['moved'][] = array(
				'id'    => (int) $item_id,
				'title' => $title,
				'from'  => $from_label,
				'to'    => $to_label,
			);
		}

		// Detect order changes by comparing old and new item sequences
		// for items that existed in both (excluding added/removed).
		// Cast to int for consistent comparison since $_POST values are strings.
		$old_order = array_map( 'intval', array_values( array_intersect( $old_ids, $new_ids ) ) );
		$new_order = array_map( 'intval', array_values( array_intersect( $new_ids, $old_ids ) ) );

		if ( $old_order !== $new_order ) {
			$menu_changes['order_changed'] = true;
		}

		// Detect location changes from "Display location" checkboxes.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$post_locations    = isset( $_POST['menu-locations'] ) ? (array) wp_unslash( $_POST['menu-locations'] ) : array();
		$locations_changed = $this->detect_location_changes( $post_locations, (int) $menu_id );

		// Detect "auto add pages" setting change.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_auto_add     = ! empty( $_POST['auto-add-pages'] );
		$auto_add_options = get_option( 'nav_menu_options', array() );
		$old_auto_add     = isset( $auto_add_options['auto_add'] ) && is_array( $auto_add_options['auto_add'] ) && in_array( (int) $menu_id, $auto_add_options['auto_add'], true );

		// Remove empty change types.
		$menu_changes = array_filter( $menu_changes );

		$context = array(
			'menu_id'            => $menu_id,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			'menu_name'          => sanitize_text_field( wp_unslash( $_POST['menu-name'] ) ),
			'menu_items_added'   => count( $arr_added ),
			'menu_items_removed' => count( $arr_removed ),
			'menu_changes'       => Helpers::json_encode( $menu_changes ),
		);

		if ( ! empty( $locations_changed ) ) {
			$context['locations_changed'] = Helpers::json_encode( $locations_changed );
		}

		if ( $new_auto_add !== $old_auto_add ) {
			$context['auto_add_pages_new']  = $new_auto_add ? '1' : '0';
			$context['auto_add_pages_prev'] = $old_auto_add ? '1' : '0';
		}

		$this->info_message( 'edited_menu', $context );
	}

	/**
	 * Get detailed output.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];

		if ( $message_key === 'edited_menu' ) {
			return $this->get_edited_menu_details( $context );
		}

		if ( $message_key === 'edited_menu_locations' ) {
			return $this->get_edited_locations_details( $context );
		}

		return '';
	}

	/**
	 * Get details output for edited_menu events.
	 *
	 * @param array $context Log row context.
	 * @return Event_Details_Group|string
	 */
	private function get_edited_menu_details( $context ) {
		// New format with detailed changes.
		if ( ! empty( $context['menu_changes'] ) ) {
			$changes = json_decode( $context['menu_changes'], true );

			if ( ! is_array( $changes ) ) {
				return '';
			}

			$group = new Event_Details_Group();

			if ( ! empty( $changes['added'] ) ) {
				$descriptions = array();
				foreach ( $changes['added'] as $item ) {
					$descriptions[] = $this->format_menu_item_description( $item );
				}

				$group->add_item(
					( new Event_Details_Item( null, __( 'Added', 'simple-history' ) ) )
						->set_new_value( implode( _x( ', ', 'Menu item list separator', 'simple-history' ), $descriptions ) )
				);
			}

			if ( ! empty( $changes['removed'] ) ) {
				$descriptions = array();
				foreach ( $changes['removed'] as $item ) {
					$descriptions[] = $this->format_menu_item_description( $item );
				}

				$group->add_item(
					( new Event_Details_Item( null, __( 'Removed', 'simple-history' ) ) )
						->set_new_value( implode( _x( ', ', 'Menu item list separator', 'simple-history' ), $descriptions ) )
				);
			}

			if ( ! empty( $changes['renamed'] ) ) {
				foreach ( $changes['renamed'] as $item ) {
					$group->add_item(
						( new Event_Details_Item( null, __( 'Renamed', 'simple-history' ) ) )
							->set_new_value( $item['to'] )
							->set_prev_value( $item['from'] )
					);
				}
			}

			if ( ! empty( $changes['moved'] ) ) {
				foreach ( $changes['moved'] as $item ) {
					$group->add_item(
						( new Event_Details_Item( null, __( 'Moved', 'simple-history' ) ) )
							->set_new_value(
								sprintf(
									// translators: 1: menu item title, 2: parent location.
									__( '%1$s: %2$s', 'simple-history' ),
									$item['title'],
									$item['to']
								)
							)
							->set_prev_value(
								sprintf(
									// translators: 1: menu item title, 2: parent location.
									__( '%1$s: %2$s', 'simple-history' ),
									$item['title'],
									$item['from']
								)
							)
					);
				}
			}

			if ( ! empty( $changes['order_changed'] ) ) {
				$group->add_item(
					( new Event_Details_Item( null, __( 'Order', 'simple-history' ) ) )
						->set_new_value( __( 'Changed menu item order', 'simple-history' ) )
				);
			}

			// Location changes from "Display location" checkboxes.
			$this->add_location_items_to_group( $group, $context );

			// Auto add pages setting change.
			if ( isset( $context['auto_add_pages_new'] ) ) {
				$enabled_label  = __( 'Enabled', 'simple-history' );
				$disabled_label = __( 'Disabled', 'simple-history' );

				$group->add_item(
					( new Event_Details_Item( null, __( 'Auto add pages', 'simple-history' ) ) )
						->set_new_value( $context['auto_add_pages_new'] === '1' ? $enabled_label : $disabled_label )
						->set_prev_value( $context['auto_add_pages_prev'] === '1' ? $enabled_label : $disabled_label )
				);
			}

			if ( empty( $group->items ) ) {
				return '';
			}

			return $group;
		}

		// Legacy format: count-based display for old log entries.
		if ( ! empty( $context['menu_items_added'] ) || ! empty( $context['menu_items_removed'] ) ) {
			$output  = '<p>';
			$output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
			$output .= sprintf(
				// translators: Number of menu items added.
				_nx( '%1$s menu item added', '%1$s menu items added', $context['menu_items_added'], 'menu logger', 'simple-history' ),
				esc_attr( $context['menu_items_added'] )
			);
			$output .= '</span> ';
			$output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
			$output .= sprintf(
				// translators: Number of menu items removed.
				_nx( '%1$s menu item removed', '%1$s menu items removed', $context['menu_items_removed'], 'menu logger', 'simple-history' ),
				esc_attr( $context['menu_items_removed'] )
			);
			$output .= '</span> ';
			$output .= '</p>';

			return $output;
		}

		return '';
	}

	/**
	 * Get details output for edited_menu_locations events.
	 *
	 * @param array $context Log row context.
	 * @return Event_Details_Group|string
	 */
	private function get_edited_locations_details( $context ) {
		$group = new Event_Details_Group();

		$this->add_location_items_to_group( $group, $context );

		if ( empty( $group->items ) ) {
			return '';
		}

		return $group;
	}

	/**
	 * Add location change items to an Event_Details_Group.
	 *
	 * @param Event_Details_Group $group   Group to add items to.
	 * @param array               $context Log row context.
	 */
	private function add_location_items_to_group( $group, $context ) {
		if ( empty( $context['locations_changed'] ) ) {
			return;
		}

		$changes = json_decode( $context['locations_changed'], true );

		if ( ! is_array( $changes ) || empty( $changes ) ) {
			return;
		}

		foreach ( $changes as $change ) {
			$group->add_item(
				( new Event_Details_Item( null, $change['location'] ) )
					->set_new_value( $change['to'] )
					->set_prev_value( $change['from'] )
			);
		}
	}

	/**
	 * Log updates to theme menu locations
	 */
	public function on_load_nav_menus_page_detect_locations_update() {

		// Check that needed vars are set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['menu'], $_REQUEST['action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $_REQUEST['action'] !== 'locations' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_locations = (array) wp_unslash( $_POST['menu-locations'] );

		$locations_changed = $this->detect_location_changes( $new_locations );

		// Only log if something actually changed.
		if ( empty( $locations_changed ) ) {
			return;
		}

		$this->info_message(
			'edited_menu_locations',
			array(
				'menu_locations'    => Helpers::json_encode( $new_locations ),
				'locations_changed' => Helpers::json_encode( $locations_changed ),
			)
		);
	}

	/**
	 * Get human-readable menu name by ID, or "None" if empty/invalid.
	 *
	 * @param int $menu_id Menu term ID.
	 * @return string Menu name or "None".
	 */
	private function get_menu_name_by_id( $menu_id ) {
		if ( empty( $menu_id ) ) {
			return __( 'None', 'simple-history' );
		}

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( $menu && ! is_wp_error( $menu ) ) {
			return $menu->name;
		}

		return __( 'None', 'simple-history' );
	}

	/**
	 * Compare new menu locations with current ones and return changes.
	 *
	 * @param array $new_locations New menu locations from POST data.
	 * @param int   $menu_id      Optional menu ID being edited, to detect removed locations.
	 * @return array Array of location changes with location/from/to keys.
	 */
	private function detect_location_changes( $new_locations, $menu_id = 0 ) {
		$old_locations    = get_nav_menu_locations();
		$registered_menus = get_registered_nav_menus();
		$changes          = array();

		// Detect added or changed locations.
		foreach ( $new_locations as $location_slug => $new_menu_id ) {
			$old_menu_id = isset( $old_locations[ $location_slug ] ) ? (int) $old_locations[ $location_slug ] : 0;
			$new_menu_id = (int) $new_menu_id;

			if ( $old_menu_id === $new_menu_id ) {
				continue;
			}

			$changes[] = array(
				'location' => $registered_menus[ $location_slug ] ?? $location_slug,
				'from'     => $this->get_menu_name_by_id( $old_menu_id ),
				'to'       => $this->get_menu_name_by_id( $new_menu_id ),
			);
		}

		// Detect removed locations: old locations assigned to this menu
		// that are no longer in the POST data (checkbox was unchecked).
		if ( $menu_id > 0 ) {
			foreach ( $old_locations as $location_slug => $old_menu_id ) {
				if ( (int) $old_menu_id !== $menu_id ) {
					continue;
				}

				if ( isset( $new_locations[ $location_slug ] ) ) {
					continue;
				}

				$changes[] = array(
					'location' => $registered_menus[ $location_slug ] ?? $location_slug,
					'from'     => $this->get_menu_name_by_id( $old_menu_id ),
					'to'       => $this->get_menu_name_by_id( 0 ),
				);
			}
		}

		return $changes;
	}

	/**
	 * Get label for a menu item's parent, resolving ID to title.
	 *
	 * @param int   $parent_id     Parent menu item db_id (0 = top level).
	 * @param array $old_items_map Map of old menu items keyed by db_id.
	 * @param array $post_titles   Array of new titles from $_POST keyed by db_id.
	 * @return string Human-readable parent label.
	 */
	private function get_menu_item_parent_label( $parent_id, $old_items_map, $post_titles ) {
		if ( empty( $parent_id ) ) {
			return __( 'top level', 'simple-history' );
		}

		$parent_title = '';

		// Try new title from POST first (may have been renamed).
		if ( isset( $post_titles[ $parent_id ] ) && $post_titles[ $parent_id ] !== '' ) {
			$parent_title = sanitize_text_field( $post_titles[ $parent_id ] );
		} elseif ( isset( $old_items_map[ $parent_id ] ) ) {
			$parent_title = $old_items_map[ $parent_id ]->title;
		}

		if ( $parent_title === '' ) {
			return __( 'top level', 'simple-history' );
		}

		return sprintf(
			// translators: %s is the parent menu item title.
			__( 'under "%s"', 'simple-history' ),
			$parent_title
		);
	}

	/**
	 * Get a human-readable type label for a menu item from its type and object slug.
	 *
	 * @param string $type        Menu item type (post_type, taxonomy, custom).
	 * @param string $object_slug Menu item object slug (page, post, category, etc.).
	 * @return string Human-readable label.
	 */
	private function get_menu_item_type_label( $type, $object_slug ) {
		if ( $type === 'post_type' && $object_slug !== '' ) {
			$post_type_obj = get_post_type_object( $object_slug );

			if ( $post_type_obj ) {
				return $post_type_obj->labels->singular_name;
			}
		}

		if ( $type === 'taxonomy' && $object_slug !== '' ) {
			$taxonomy_obj = get_taxonomy( $object_slug );

			if ( $taxonomy_obj ) {
				return $taxonomy_obj->labels->singular_name;
			}
		}

		if ( $type === 'custom' ) {
			return __( 'Custom Link', 'simple-history' );
		}

		return '';
	}

	/**
	 * Format a menu item for display in added/removed lists.
	 *
	 * @param array $item Item data with title, type, and optionally url.
	 * @return string Formatted description.
	 */
	private function format_menu_item_description( $item ) {
		if ( ! empty( $item['type'] ) && ! empty( $item['url'] ) ) {
			return sprintf(
				// translators: 1: menu item title, 2: item type, 3: URL.
				__( '"%1$s" (%2$s: %3$s)', 'simple-history' ),
				$item['title'],
				$item['type'],
				$item['url']
			);
		}

		if ( ! empty( $item['type'] ) ) {
			return sprintf(
				// translators: 1: menu item title, 2: item type (e.g. Page, Post, Category).
				__( '"%1$s" (%2$s)', 'simple-history' ),
				$item['title'],
				$item['type']
			);
		}

		return sprintf(
			// translators: %s: menu item title.
			__( '"%s"', 'simple-history' ),
			$item['title']
		);
	}
}
