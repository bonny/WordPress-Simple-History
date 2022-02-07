<?php

defined( 'ABSPATH' ) || die();

/**
 * Logger for the Advanced Custom Fields (ACF) plugin
 * https://sv.wordpress.org/plugins/advanced-custom-fields/
 *
 * @package SimpleHistory
 * @since 2.21
 */
if ( ! class_exists( 'Plugin_ACF' ) ) {

	/**
	 * Class for ACF logging.
	 */
	class Plugin_ACF extends SimpleLogger {


		/**
		 * The slug for this logger.
		 *
		 * @var string $slug
		 */
		public $slug = __CLASS__;

		/**
		 * Will contain field groups and fields, before and after post save.
		 *
		 * @var string $oldAndNewFieldGroupsAndFields
		 */
		private $oldAndNewFieldGroupsAndFields = array(
			'fieldGroup'     => array(
				'old' => null,
				'new' => null,
			),
			'modifiedFields' => array(
				'old' => null,
				'new' => null,
			),
			'addedFields'    => array(),
			'deletedFields'  => array(),
		);

		/**
		 * Will contain the post data before save, i.e. the previous version of the post.
		 *
		 * @var string $oldPostData
		 */
		private $oldPostData = array();

		/**
		 * Get info for this logger.
		 *
		 * @return array Array with info about the logger.
		 */
		public function getInfo() {
			$arr_info = array(
				'name'        => _x( 'Plugin: Advanced Custom Fields Logger', 'Logger: Plugin ACF', 'simple-history' ),
				'description' => _x( 'Logs ACF stuff', 'Logger: Plugin ACF', 'simple-history' ),
				'name_via'    => _x( 'Using plugin ACF', 'Logger: Plugin ACF', 'simple-history' ),
				'capability'  => 'manage_options',
			);

			return $arr_info;
		}

		private function isACFInstalled() {
			return defined( 'ACF' ) && ACF;
		}

		/**
		 * Method called when logger is loaded.
		 */
		public function loaded() {

			// Bail if no ACF found.
			if ( ! $this->isACFInstalled() ) {
				return;
			}

			// Remove ACF Fields from the post types that postlogger logs.
			add_filter( 'simple_history/post_logger/skip_posttypes', array( $this, 'remove_acf_from_postlogger' ) );

			// Get prev version of acf field group.
			// This is called before transition_post_status.
			add_filter( 'wp_insert_post_data', array( $this, 'on_wp_insert_post_data' ), 10, 2 );

			// Store old and new field data when a post is saved.
			add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 5, 3 );

			// Append ACF data to post context
			add_filter( 'simple_history/post_logger/post_updated/context', array( $this, 'on_post_updated_context' ), 10, 2 );

			// Add ACF diff data to activity feed detailed output.
			add_filter( 'simple_history/post_logger/post_updated/diff_table_output', array( $this, 'on_diff_table_output_field_group' ), 10, 2 );

			// Store prev ACF field values before new values are added.
			// Called from filter admin_action_editpost that is fired at top of admin.php
			add_action( 'admin_action_editpost', array( $this, 'on_admin_action_editpost' ) );

			// Fired when ACF saves a post. Adds ACF context to logged row.
			add_filter( 'acf/save_post', array( $this, 'on_acf_save_post' ), 50 );

			// Fired after a log row is inserted. Add filter so field group save is is not logged again.
			add_action( 'simple_history/log/inserted', array( $this, 'on_log_inserted' ), 10, 3 );
		}

		/**
		 * Fired after a log row is inserted.
		 */
		public function on_log_inserted( $context, $data_parent_row, $simple_history_instance ) {
			$message_key = ! empty( $context['_message_key'] ) ? $context['_message_key'] : false;
			$logger = ! empty( $data_parent_row['logger'] ) ? $data_parent_row['logger'] : false;
			$post_id = ! empty( $context['post_id'] ) ? $context['post_id'] : false;
			$post_type = ! empty( $context['post_type'] ) ? $context['post_type'] : false;

			// Bail if not all required vars are set.
			if ( ! $message_key || ! $logger || ! $post_id || ! $post_type ) {
				return;
			}

			// Only act when logger was SimplePostLogger.
			if ( $logger !== 'SimplePostLogger' ) {
				return;
			}

			// Only act when the saved type was a ACF Field Group.
			if ( $post_type !== 'acf-field-group' ) {
				return;
			}

			// Ok, a row was inserted using the log function on SimplePostLogger,
			// now ACF will call save_post again and trigger
			// another log of the same row. To prevent this we
			// now add a filter to prevent the next log.
			add_filter( 'simple_history/post_logger/post_updated/ok_to_log', array( $this, 'prevent_second_acf_field_group_post_save_log' ), 10, 4 );
		}

		/**
		 * Fired from SimpleLogger action 'simple_history/post_logger/post_updated/ok_to_log' and added only after
		 * a row already has been logged.
		 *
		 * This function checks if post type logged by SimplePostLogger is a ACF Field Group, and if it is
		 * then don't log that log. This way we prevent the post logger from logging the field group changes twice.
		 */
		public function prevent_second_acf_field_group_post_save_log( $ok_to_log, $new_status, $old_status, $post ) {
			if ( isset( $post->post_type ) && $post->post_type === 'acf-field-group' ) {
				$ok_to_log = false;
			}

			return $ok_to_log;
		}

		/**
		 * Append info about changes in ACF fields input,
		 * i.e. store all info we later use to show changes that a user has done.
		 *
		 * Called when ACF saves a post.
		 *
		 * @param mixed int $post_id ID of post that is being saved. string "option" or "options" when saving an options page.
		 */
		public function on_acf_save_post( $post_id ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Only act when $post_id is numeric, can be "options" too when
			// ACF saves an options page.
			if ( ! is_numeric( $post_id ) ) {
				return;
			}

			// Don't act on post revision.
			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}

			/*
			Meta values look like
			[product_images_0_image] => 625
			[_product_images_0_image] => field_59a091044812e
			[product_images_0_image_caption] => Image row yes
			[_product_images_0_image_caption] => field_59a0910f4812f
			[product_images_0_image_related_0_related_name] => Related one
			[_product_images_0_image_related_0_related_name] => field_59aaedd43ae11
			[product_images_0_image_related_0_related_item_post] =>
			[_product_images_0_image_related_0_related_item_post] => field_59aaede43ae12
			[product_images_0_image_related_1_related_name] => Another related
			[_product_images_0_image_related_1_related_name] => field_59aaedd43ae11
			[product_images_0_image_related_1_related_item_post] =>
			[_product_images_0_image_related_1_related_item_post] => field_59aaede43ae12
			[product_images_0_image_related] => 2
			[_product_images_0_image_related] => field_59aaedbc3ae10
			[product_images_1_image] => 574
			*/
			$prev_post_meta = isset( $this->oldPostData['prev_post_meta'] ) ? $this->oldPostData['prev_post_meta'] : array();

			$new_post_meta = get_post_custom( $post_id );
			array_walk(
				$new_post_meta,
				function( &$value, $key ) {
					$value = reset( $value );
				}
			);

			// New and old post meta can contain different amount of keys,
			// join them so we have the name of all post meta that have been added, removed, or modified.
			$new_and_old_post_meta = array_merge( $prev_post_meta, $new_post_meta );
			ksort( $new_and_old_post_meta, SORT_REGULAR );

			// array1 - The array to compare from
			// array2 - An array to compare against
			// Returns an array containing all the values from array1 that are not present in any of the other arrays.
			// Keep only ACF fields in prev and new post meta.
			$prev_post_meta                   = $this->keep_only_acf_stuff_in_array( $prev_post_meta, $new_and_old_post_meta );
			$new_post_meta                    = $this->keep_only_acf_stuff_in_array( $new_post_meta, $new_and_old_post_meta );
			$new_and_old_post_meta_acf_fields = array_merge( $prev_post_meta, $new_post_meta );

			// Map field name with fieldkey so we can get field objects when needed.
			// Final array have values like:
			// [product_images_0_image] => field_59a091044812e
			// [product_images_0_image_caption] => field_59a0910f4812f
			// [product_images_0_image_related_0_related_name] => field_59aaedd43ae11.
			$fieldnames_to_field_keys = array();
			foreach ( $new_and_old_post_meta_acf_fields as $meta_key => $meta_value ) {
				// $key is like [product_images_0_image_related_1_related_name].
				// Get ACF fieldkey for that value. Will be in $new_and_old_post_meta
				// as the same as key but with underscore first
				$meta_key_to_look_for = "_{$meta_key}";
				if ( isset( $new_and_old_post_meta[ $meta_key_to_look_for ] ) ) {
					$fieldnames_to_field_keys[ $meta_key ] = $new_and_old_post_meta[ $meta_key_to_look_for ];
				}
			}

			// Compare old with new = get only changed, not added, deleted are here.
			$post_meta_diff1 = array_diff_assoc( $prev_post_meta, $new_post_meta );

			// Compare new with old = get an diff with added and changed stuff.
			$post_meta_diff2 = array_diff_assoc( $new_post_meta, $prev_post_meta );

			// Compare keys, gets added fields.
			$post_meta_added_fields = array_diff( array_keys( $post_meta_diff2 ), array_keys( $post_meta_diff1 ) );
			$post_meta_added_fields = array_values( $post_meta_added_fields );

			// Keys that exist in diff1 but not in diff2 = deleted.
			$post_meta_removed_fields = array_diff_assoc( array_keys( $post_meta_diff1 ), array_keys( $post_meta_diff2 ) );
			$post_meta_removed_fields = array_values( $post_meta_removed_fields );

			$post_meta_changed_fields = array_keys( $post_meta_diff1 );

			/*
			 * value is changed: added to both diff and diff2
			 * value is added, like in repeater: added to diff2 (not to diff)
			 * $diff3: contains only added things.
			 * Compare old and new values
			 * Loop all keys in $new_and_old_post_meta
			 * But act only on those whose keys begins with "_" and where the value begins with "field_" and ends with alphanum.
			 */

			/*
			 * We have the diff, now add it to the context
			 * This is called after Simple History already has added its row
			 * So... we must add to the context late somehow
			 * Get the latest inserted row from the SimplePostLogger, check if that postID is
			 * same as the
			 */
			$post_logger = $this->simpleHistory->getInstantiatedLoggerBySlug( 'SimplePostLogger' );

			// Save ACF diff if detected post here is same as the last one used in Postlogger.
			if ( isset( $post_logger->lastInsertContext['post_id'] ) && $post_id === $post_logger->lastInsertContext['post_id'] ) {
				$last_insert_id = $post_logger->lastInsertID;

				// Append new info to the context of history item with id $post_logger->lastInsertID.
				$acf_context = array();
				$acf_context = $this->add_acf_context( $acf_context, 'added', $post_meta_added_fields, $prev_post_meta, $new_post_meta, $fieldnames_to_field_keys );
				$acf_context = $this->add_acf_context( $acf_context, 'changed', $post_meta_changed_fields, $prev_post_meta, $new_post_meta, $fieldnames_to_field_keys );
				$acf_context = $this->add_acf_context( $acf_context, 'removed', $post_meta_removed_fields, $prev_post_meta, $new_post_meta, $fieldnames_to_field_keys );

				$post_logger->append_context( $last_insert_id, $acf_context );

				// Prev and new post meta for testing.
				/*
				$post_logger->append_context(
					$last_insert_id,
					array(
						'prev_post_meta' => $prev_post_meta,
					)
				);
				$post_logger->append_context(
					$last_insert_id,
					array(
						'new_post_meta' => $new_post_meta,
					)
				);
				*/
			} // End if().
		}

		/**
		 * Add ACF context for added, removed, or changed fields.
		 *
		 * @param array  $context Context.
		 * @param string $modify_type Type. added | removed | changed.
		 * @param array  $relevant_acf_fields Fields.
		 * @param array  $prev_post_meta Prev meta.
		 * @param array  $new_post_meta New meta.
		 * @param array  $fieldnames_to_field_keys Fieldnames to field keys mapping.
		 * @return array Modified context.
		 */
		public function add_acf_context( $context = array(), $modify_type = '', $relevant_acf_fields = array(), $prev_post_meta = array(), $new_post_meta = array(), $fieldnames_to_field_keys = array() ) {

			if ( ! is_array( $context ) || empty( $modify_type ) || empty( $relevant_acf_fields ) ) {
				return $context;
			}

			$loopnum = 0;
			foreach ( $relevant_acf_fields as $field_slug ) {
				/*
				Store just the names to begin with
				acf_field_added_0 = url.
				acf_field_added_1 = first_name.

				If field slug contains a number, like in "product_images_2_image"
				that probably means that that field is a repeater with name "product_images"
				with a sub field called "image" and that the image is the 2:nd among it's selected sub fields.

				Example of how fields can look:
				acf_field_added_0   product_images_2_image
				acf_field_added_1   product_images_2_image_caption
				acf_field_added_2   product_images_2_image_related
				acf_field_changed_0 my_field_in_acf
				acf_field_changed_1 product_images
				acf_field_changed_2 price
				acf_field_changed_3 description
				*/
				$context_key                      = "acf_field_{$modify_type}_{$loopnum}";
				$context[ "{$context_key}/slug" ] = $field_slug;

				/*
				 * Try to get som extra info, like display name and type for this field.
				 * For a nice context in the feed we want: parent field group name and type?
				 */
				if ( isset( $fieldnames_to_field_keys[ $field_slug ] ) ) {
					$field_key                       = $fieldnames_to_field_keys[ $field_slug ];
					$context[ "{$context_key}/key" ] = $field_key;

					// Interesting stuff in field object:
					// - Label = the human readable name of the field
					// - Type = the type of the field
					// - Parent = id of parent field post id.
					$field_object = get_field_object( $field_key );

					if ( is_array( $field_object ) ) {
						$context[ "{$context_key}/label" ] = $field_object['label'];
						if ( ! empty( $field_object['type'] ) ) {
							$context[ "{$context_key}/type" ] = $field_object['type'];
						}

						// If no parent just continue to next field.
						if ( empty( $field_object['parent'] ) ) {
							continue;
						}

						// We have at least one parent, get them all, including the field group
						// $context[ "{$context_key}/field_parent_object" ] = $parent_field;
						$field_parents     = array();
						$field_field_group = null;

						// Begin with the direct parent.
						$parent_field = $field_object;

						while ( ! empty( $parent_field['parent'] ) ) {
							$parentFieldParent = $parent_field['parent'];

							// acf-field | acf-field-group.
							$parent_field_post_type = get_post_type( $parentFieldParent );

							if ( false === $parent_field_post_type ) {
								break;
							}

							if ( 'acf-field' === $parent_field_post_type ) {
								// Field is when field is for example a sub field of a repeater.
								if ( function_exists( 'acf_get_field' ) ) {
									// Since ACF 5.7.10 the acf_get_field() function is available.
									$parent_field = acf_get_field( $parentFieldParent );
								} elseif ( function_exists( '_acf_get_field_by_id' ) ) {
									// ACF function _acf_get_field_by_id() is available before ACF 5.7.10.
									$parent_field = _acf_get_field_by_id( $parentFieldParent );
								}
							} elseif ( 'acf-field-group' === $parent_field_post_type ) {
								$parent_field = acf_get_field_group( $parentFieldParent );
							} else {
								// Unknown post type.
								break;
							}

							if ( false === $parent_field ) {
								break;
							}

							if ( 'acf-field' === $parent_field_post_type ) {
								$field_parents[] = $parent_field;
							} elseif ( 'acf-field-group' === $parent_field_post_type ) {
								$field_field_group = $parent_field;
							} // End if().
						} // End while().

						$field_parents = array_reverse( $field_parents );

						// Array with info about each parent.
						$arr_field_path = array();

						if ( ! empty( $field_field_group['title'] ) ) {
							$arr_field_path[] = array(
								'name' => $field_field_group['title'],
								'type' => 'field_group',
							);
						}

						foreach ( $field_parents as $one_field_parent ) {
							$arr_field_path[] = array(
								'name'       => $one_field_parent['label'],
								'type'       => 'field',
								'field_type' => $one_field_parent['type'],
							);
						}

						if ( ! empty( $arr_field_path ) ) {
							$path_loop_num = 0;
							foreach ( $arr_field_path as $one_field_path ) {
								$context[ "{$context_key}/path_{$path_loop_num}/name" ] = $one_field_path['name'];
								$context[ "{$context_key}/path_{$path_loop_num}/type" ] = $one_field_path['type'];
								if ( ! empty( $one_field_path['field_type'] ) ) {
									$context[ "{$context_key}/path_{$path_loop_num}/field_type" ] = $one_field_path['field_type'];
								}
								$path_loop_num++;
							}
						}

						// Add value of fields if they are not part of
						// repeatable or flexible fields or similar.
						// error_log( "Final parents" . print_r( $field_parents, 1 ) );
						// error_log( "Final field group" . print_r( $field_field_group['title'], 1 ) );
						// error_log( "context" . print_r( $context, 1 ) );
					} // End if().
				} // End if().

				$loopnum++;
			} // End foreach().

			// error_log( "---------------------------" );
			// error_log( "field_path_string: $field_path_string");
			// error_log( "context" . print_r( $context, 1 ) );
			return $context;
		}

		/**
		 * Clean array and keep only ACF related things.
		 *
		 * Remove
		 *  - underscore fields
		 *  - fields with value field_*
		 *
		 * Keep
		 *  - vals that are acf
		 *
		 * @param array $arr Array.
		 * @param array $all_fields Array fields.
		 */
		public function keep_only_acf_stuff_in_array( $arr, $all_fields ) {
			$new_arr = array();

			foreach ( $arr as $key => $val ) {
				// Don't keep keys that begin with underscore "_".
				if ( strpos( $key, '_' ) === 0 ) {
					continue;
				}

				// Don't keep keys that begin with "field_".
				if ( strpos( $val, 'field_' ) === 0 ) {
					continue;
				}

				// Don't keep fields that does not have a corresponding _field value.
				// Each key has both the name, for example 'color' and another
				// key called '_color'. We check that the underscore version exists
				// and contains 'field_'. After this check only ACF fields should exist
				// in the array..
				if ( ! isset( $all_fields[ "_{$key}" ] ) ) {
					continue;
				}

				if ( strpos( $all_fields[ "_{$key}" ], 'field_' ) !== 0 ) {
					continue;
				}

				$new_arr[ $key ] = $val;
			}

			return $new_arr;
		}

		/**
		 * Store prev post meta when post is saved.
		 * Stores data in $this->oldPostData.
		 */
		public function on_admin_action_editpost() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_ID = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

			if ( ! $post_ID ) {
				return;
			}

			$prev_post = get_post( $post_ID );

			if ( is_wp_error( $prev_post ) ) {
				return;
			}

			$post_meta = get_post_custom( $post_ID );

			// Meta is array of arrays, get first value of each array value.
			array_walk(
				$post_meta,
				function( &$value, $key ) {
					$value = reset( $value );
				}
			);

			$this->oldPostData['prev_post_meta'] = $post_meta;
		}

		/**
		 * Called from PostLogger and its diff table output using filter 'simple_history/post_logger/post_updated/diff_table_output'.
		 * Diff table is generated only for post type 'acf-field-group'.
		 *
		 * @param string $diff_table_output
		 * @param array  $context
		 * @return string
		 */
		public function on_diff_table_output_field_group( $diff_table_output, $context ) {
			$post_type = ! empty( $context['post_type'] ) ? $context['post_type'] : false;

			// Bail if not ACF Field Group.
			if ( $post_type !== 'acf-field-group' ) {
				return $diff_table_output;
			}

			// Field group fields to check for and output if found
			$arrKeys = array(
				'instruction_placement' => array(
					'name' => _x( 'Instruction placement', 'Logger: Plugin ACF', 'simple-history' ),
				),
				'label_placement'       => array(
					'name' => _x( 'Label placement', 'Logger: Plugin ACF', 'simple-history' ),
				),
				'description'           => array(
					'name' => _x( 'Description', 'Logger: Plugin ACF', 'simple-history' ),
				),
				'menu_order'            => array(
					'name' => _x( 'Menu order', 'Logger: Plugin ACF', 'simple-history' ),
				),
				'position'              => array(
					'name' => _x( 'Position', 'Logger: Plugin ACF', 'simple-history' ),
				),
				'active'                => array(
					'name' => _x( 'Active', 'Logger: Plugin ACF', 'simple-history' ),
				),
				'style'                 => array(
					'name' => _x( 'Style', 'Logger: Plugin ACF', 'simple-history' ),
				),
			);

			foreach ( $arrKeys as $acfKey => $acfVals ) {
				if ( isset( $context[ "acf_new_$acfKey" ] ) && isset( $context[ "acf_prev_$acfKey" ] ) ) {
					$diff_table_output .= sprintf(
						'<tr>
							<td>%1$s</td>
							<td>
								<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%2$s</ins>
								<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%3$s</del>
							</td>
						</tr>',
						$acfVals['name'],
						esc_html( $context[ "acf_new_$acfKey" ] ),
						esc_html( $context[ "acf_prev_$acfKey" ] )
					);
				}
			}

			// If only acf_hide_on_screen_removed exists nothing is outputed.
			$acf_hide_on_screen_added   = empty( $context['acf_hide_on_screen_added'] ) ? null : $context['acf_hide_on_screen_added'];
			$acf_hide_on_screen_removed = empty( $context['acf_hide_on_screen_removed'] ) ? null : $context['acf_hide_on_screen_removed'];

			if ( $acf_hide_on_screen_added || $acf_hide_on_screen_removed ) {
				$strCheckedHideOnScreen   = '';
				$strUncheckedHideOnScreen = '';

				if ( $acf_hide_on_screen_added ) {
					$strCheckedHideOnScreen = sprintf(
						'%1$s %2$s',
						_x( 'Checked', 'Logger: Plugin ACF', 'simple-history' ), // 1
						esc_html( $acf_hide_on_screen_added ) // 2
					);
				}

				if ( $acf_hide_on_screen_removed ) {
					$strUncheckedHideOnScreen = sprintf(
						'%1$s %2$s',
						_x( 'Unchecked', 'Logger: Plugin ACF', 'simple-history' ), // 1
						esc_html( $acf_hide_on_screen_removed ) // 2
					);
				}

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>
							%2$s
							%3$s
						</td>
					</tr>',
					_x( 'Hide on screen', 'Logger: Plugin ACF', 'simple-history' ), // 1
					$strCheckedHideOnScreen, // 2
					$strUncheckedHideOnScreen // 3
				);
			}

			// Check for deleted fields.
			if ( isset( $context['acf_deleted_fields_0_key'] ) ) {
				// 1 or more deleted fields exist in context.
				$loopnum          = 0;
				$strDeletedFields = '';

				while ( isset( $context[ "acf_deleted_fields_{$loopnum}_key" ] ) ) {
					$strDeletedFields .= sprintf(
						'%1$s (%3$s), ',
						esc_html( $context[ "acf_deleted_fields_{$loopnum}_label" ] ),
						esc_html( $context[ "acf_deleted_fields_{$loopnum}_name" ] ),
						esc_html( $context[ "acf_deleted_fields_{$loopnum}_type" ] )
					);

					$loopnum++;
				}

				$strDeletedFields = trim( $strDeletedFields, ', ' );

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>',
					_nx( 'Deleted field', 'Deleted fields', $loopnum, 'Logger: Plugin ACF', 'simple-history' ), // 1
					$strDeletedFields
				);
			} // if deleted fields

			// Check for added fields
			if ( isset( $context['acf_added_fields_0_key'] ) ) {
				// 1 or more deleted fields exist in context
				$loopnum        = 0;
				$strAddedFields = '';

				while ( isset( $context[ "acf_added_fields_{$loopnum}_key" ] ) ) {
					$strAddedFields .= sprintf(
						'%1$s (%3$s), ',
						esc_html( $context[ "acf_added_fields_{$loopnum}_label" ] ), // 1
						esc_html( $context[ "acf_added_fields_{$loopnum}_name" ] ), // 2
						esc_html( $context[ "acf_added_fields_{$loopnum}_type" ] ) // 3
					);

					$loopnum++;
				}

				$strAddedFields = trim( $strAddedFields, ', ' );

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>',
					_nx( 'Added field', 'Added fields', $loopnum, 'Logger: Plugin ACF', 'simple-history' ), // 1
					$strAddedFields
				);
			} // if deleted fields

			// Check for modified fields
			if ( isset( $context['acf_modified_fields_0_ID_prev'] ) ) {
				// 1 or more modifiedfields exist in context
				$loopnum                   = 0;
				$strModifiedFields         = '';
				$arrAddedFieldsKeysToCheck = array(
					'name'   => array(
						'name' => _x( 'Name: ', 'Logger: Plugin ACF', 'simple-history' ),
					),
					'parent' => array(
						'name' => _x( 'Parent: ', 'Logger: Plugin ACF', 'simple-history' ),
					),
					'key'    => array(
						'name' => _x( 'Key: ', 'Logger: Plugin ACF', 'simple-history' ),
					),
					'label'  => array(
						'name' => _x( 'Label: ', 'Logger: Plugin ACF', 'simple-history' ),
					),
					'type'   => array(
						'name' => _x( 'Type: ', 'Logger: Plugin ACF', 'simple-history' ),
					),
				);

				while ( isset( $context[ "acf_modified_fields_{$loopnum}_name_prev" ] ) ) {
					// One modified field, with one or more changed things
					$strOneModifiedField = '';

					// Add the field name manually, if it is not among the changed field,
					// or we don't know what field the other changed values belongs to.
					/*
					if (empty($context["acf_modified_fields_{$loopnum}_name_new"])) {
						$strOneModifiedField .= sprintf(
							_x('Name: %1$s', 'Logger: Plugin ACF', 'simple-history'), // 1
							esc_html($context["acf_modified_fields_{$loopnum}_name_prev"]) // 2
						);
					}
					*/

					// Add the label name manually, if it is not among the changed field,
					// or we don't know what field the other changed values belongs to.
					if ( empty( $context[ "acf_modified_fields_{$loopnum}_label_new" ] ) ) {
						$strOneModifiedField .= sprintf(
							_x( 'Label: %1$s', 'Logger: Plugin ACF', 'simple-history' ), // 1
							esc_html( $context[ "acf_modified_fields_{$loopnum}_label_prev" ] ) // 2
						);
					}

					// Check for other keys changed for this field
					foreach ( $arrAddedFieldsKeysToCheck as $oneAddedFieldKeyToCheck => $oneAddedFieldKeyToCheckVals ) {
						$newAndOldValsExists = isset( $context[ "acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_new" ] ) && isset( $context[ "acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_new" ] );
						if ( $newAndOldValsExists ) {
							$strOneModifiedField .= sprintf(
								'
									%4$s
									%3$s
									<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>
									<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>
								',
								esc_html( $context[ "acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_new" ] ), // 1
								esc_html( $context[ "acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_prev" ] ), // 2
								esc_html( $oneAddedFieldKeyToCheckVals['name'] ), // 3
								empty( $strOneModifiedField ) ? '' : '<br>' // 4 new line
							);
						}
					}

					$strOneModifiedField = trim( $strOneModifiedField, ", \n\r\t" );

					if ( $strOneModifiedField ) {
						$strModifiedFields .= sprintf(
							'<tr>
								<td>%1$s</td>
								<td>%2$s</td>
							</tr>',
							_x( 'Modified field', 'Logger: Plugin ACF', 'simple-history' ), // 1
							$strOneModifiedField
						);
					}

					$loopnum++;
				}

				/*
				if ($strModifiedFields) {
					$strModifiedFields = sprintf(
						'<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>',
						_nx('Modified field', 'Modified fields', $loopnum, 'Logger: Plugin ACF', 'simple-history'), // 1
						$strModifiedFields
					) . $strModifiedFields;
				}*/

				$diff_table_output .= $strModifiedFields;
			} // if deleted fields

			return $diff_table_output;
		}

		/**
		 * Append ACF data to post context.
		 *
		 * Called via filter `simple_history/post_logger/post_updated/context`.
		 *
		 * @param array   $context
		 * @param WP_Post $post
		 */
		public function on_post_updated_context( $context, $post ) {

			// Only act if this is a ACF field group that is saved
			if ( $post->post_type !== 'acf-field-group' ) {
				return $context;
			}

			// Remove some keys that we don't want,
			// for example the content because that's just a json string
			// in acf-field-group posts.
			unset(
				$context['post_prev_post_content'],
				$context['post_new_post_content'],
				$context['post_prev_post_name'],
				$context['post_new_post_name'],
				$context['post_prev_post_date'],
				$context['post_new_post_date'],
				$context['post_prev_post_date_gmt'],
				$context['post_new_post_date_gmt']
			);

			$acf_data_diff = array();

			// 'fieldGroup' fields to check.
			$arr_field_group_keys_to_diff = array(
				'menu_order',
				'position',
				'style',
				'label_placement',
				'instruction_placement',
				'active',
				'description',
			);

			$fieldGroup = $this->oldAndNewFieldGroupsAndFields['fieldGroup'];

			foreach ( $arr_field_group_keys_to_diff as $key ) {
				if ( isset( $fieldGroup['old'][ $key ] ) && isset( $fieldGroup['new'][ $key ] ) ) {
					$acf_data_diff = $this->add_diff( $acf_data_diff, $key, (string) $fieldGroup['old'][ $key ], (string) $fieldGroup['new'][ $key ] );
				}
			}

			foreach ( $acf_data_diff as $diff_key => $diff_values ) {
				$context[ "acf_prev_{$diff_key}" ] = $diff_values['old'];
				$context[ "acf_new_{$diff_key}" ]  = $diff_values['new'];
			}

			// Add checked or uncheckd hide on screen-items to context
			$arrhHideOnScreenAdded  = array();
			$arrHideOnScreenRemoved = array();

			$fieldGroup['new']['hide_on_screen'] = isset( $fieldGroup['new']['hide_on_screen'] ) && is_array( $fieldGroup['new']['hide_on_screen'] ) ? $fieldGroup['new']['hide_on_screen'] : array();
			$fieldGroup['old']['hide_on_screen'] = isset( $fieldGroup['old']['hide_on_screen'] ) && is_array( $fieldGroup['old']['hide_on_screen'] ) ? $fieldGroup['old']['hide_on_screen'] : array();

			// dd($fieldGroup['old']['hide_on_screen'], $fieldGroup['new']['hide_on_screen']);
			// Act when new or old hide_on_screen is set
			if ( ! empty( $fieldGroup['new']['hide_on_screen'] ) || ! empty( $fieldGroup['old']['hide_on_screen'] ) ) {
				$arrhHideOnScreenAdded  = array_diff( $fieldGroup['new']['hide_on_screen'], $fieldGroup['old']['hide_on_screen'] );
				$arrHideOnScreenRemoved = array_diff( $fieldGroup['old']['hide_on_screen'], $fieldGroup['new']['hide_on_screen'] );

				// ddd($arrhHideOnScreenAdded, $arrHideOnScreenRemoved);
				if ( $arrhHideOnScreenAdded ) {
					$context['acf_hide_on_screen_added'] = implode( ',', $arrhHideOnScreenAdded );
				}

				if ( $arrHideOnScreenRemoved ) {
					$context['acf_hide_on_screen_removed'] = implode( ',', $arrHideOnScreenRemoved );
				}
			}

			// ddd($context, $arrhHideOnScreenAdded, $arrHideOnScreenRemoved);
			// Add removed fields to context
			if ( ! empty( $this->oldAndNewFieldGroupsAndFields['deletedFields'] ) && is_array( $this->oldAndNewFieldGroupsAndFields['deletedFields'] ) ) {
				$loopnum = 0;
				foreach ( $this->oldAndNewFieldGroupsAndFields['deletedFields'] as $oneDeletedField ) {
					$context[ "acf_deleted_fields_{$loopnum}_key" ]   = $oneDeletedField['key'];
					$context[ "acf_deleted_fields_{$loopnum}_name" ]  = $oneDeletedField['name'];
					$context[ "acf_deleted_fields_{$loopnum}_label" ] = $oneDeletedField['label'];
					$context[ "acf_deleted_fields_{$loopnum}_type" ]  = $oneDeletedField['type'];
					$loopnum++;
				}
			}

			// Add added fields to context
			if ( ! empty( $this->oldAndNewFieldGroupsAndFields['addedFields'] ) && is_array( $this->oldAndNewFieldGroupsAndFields['addedFields'] ) ) {
				$loopnum = 0;

				foreach ( $this->oldAndNewFieldGroupsAndFields['addedFields'] as $oneAddedField ) {
					// Id not available here, wold be nice to have
					// $context["acf_added_fields_{$loopnum}_ID"] = $oneAddedField['ID'];
					$context[ "acf_added_fields_{$loopnum}_key" ]   = $oneAddedField['key'];
					$context[ "acf_added_fields_{$loopnum}_name" ]  = $oneAddedField['name'];
					$context[ "acf_added_fields_{$loopnum}_label" ] = $oneAddedField['label'];
					$context[ "acf_added_fields_{$loopnum}_type" ]  = $oneAddedField['type'];
					$loopnum++;
				}
			}

			// Add modified fields to context
			// dd('on_post_updated_context', $context, $this->oldAndNewFieldGroupsAndFields);
			if ( ! empty( $this->oldAndNewFieldGroupsAndFields['modifiedFields']['old'] ) && ! empty( $this->oldAndNewFieldGroupsAndFields['modifiedFields']['new'] ) ) {
				$modifiedFields = $this->oldAndNewFieldGroupsAndFields['modifiedFields'];

				$arr_added_fields_keys_to_add = array(
					'parent',
					'key',
					'label',
					'name',
					'type',
				);

				$loopnum = 0;

				foreach ( $modifiedFields['old'] as $modifiedFieldId => $modifiedFieldValues ) {
					// Both old and new values mest exist
					if ( empty( $modifiedFields['new'][ $modifiedFieldId ] ) ) {
						continue;
					}

					// Always add ID, name, and lavel
					$context[ "acf_modified_fields_{$loopnum}_ID_prev" ]    = $modifiedFields['old'][ $modifiedFieldId ]['ID'];
					$context[ "acf_modified_fields_{$loopnum}_name_prev" ]  = $modifiedFields['old'][ $modifiedFieldId ]['name'];
					$context[ "acf_modified_fields_{$loopnum}_label_prev" ] = $modifiedFields['old'][ $modifiedFieldId ]['label'];

					foreach ( $arr_added_fields_keys_to_add as $one_key_to_add ) {
						// Check that new and old exist.
						$new_exists = isset( $modifiedFields['new'][ $modifiedFieldId ][ $one_key_to_add ] );
						$old_exists = isset( $modifiedFields['old'][ $modifiedFieldId ][ $one_key_to_add ] );

						if ( ! $new_exists || ! $old_exists ) {
							continue;
						}

						// Only add to context if modified.
						if ( $modifiedFields['new'][ $modifiedFieldId ][ $one_key_to_add ] != $modifiedFields['old'][ $modifiedFieldId ][ $one_key_to_add ] ) {
							$context[ "acf_modified_fields_{$loopnum}_{$one_key_to_add}_prev" ] = $modifiedFields['old'][ $modifiedFieldId ][ $one_key_to_add ];
							$context[ "acf_modified_fields_{$loopnum}_{$one_key_to_add}_new" ]  = $modifiedFields['new'][ $modifiedFieldId ][ $one_key_to_add ];
						}
					}

					$loopnum++;
				}
			}

			return $context;
		}

		public function add_diff( $post_data_diff, $key, $old_value, $new_value ) {
			if ( $old_value != $new_value ) {
				$post_data_diff[ $key ] = array(
					'old' => $old_value,
					'new' => $new_value,
				);
			}

			return $post_data_diff;
		}

		/**
		 * Store a version of the field group as it was before the save
		 * Called before field group post/values is added to db
		 *
		 * @param array $data Post data.
		 * @param array $postarr Post data.
		 */
		public function on_wp_insert_post_data( $data, $postarr ) {

			// Only do this if ACF field group is being saved.
			if ( $postarr['post_type'] !== 'acf-field-group' ) {
				return $data;
			}

			if ( empty( $postarr['ID'] ) ) {
				return $data;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( empty( $_POST['acf_field_group'] ) ) {
				return $data;
			}

			$this->oldAndNewFieldGroupsAndFields['fieldGroup']['old'] = acf_get_field_group( $postarr['ID'] );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->oldAndNewFieldGroupsAndFields['fieldGroup']['new'] = acf_get_valid_field_group( $_POST['acf_field_group'] );

			return $data;
		}

		/**
		 * ACF field group is saved
		 * Called before ACF calls its save_post filter
		 * Here we save the new fields values and also get the old values so we can compare
		 */
		public function on_transition_post_status( $new_status, $old_status, $post ) {
			static $isCalled = false;

			if ( $isCalled ) {
				return;
			}

			$isCalled = true;

			$post_id = $post->ID;

			// do not act if this is an auto save routine
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// bail early if not acf-field-group
			if ( $post->post_type !== 'acf-field-group' ) {
				return;
			}

			// only save once! WordPress save's a revision as well.
			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}

			// Store info about fields that are going to be deleted
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $_POST['_acf_delete_fields'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$deletedFieldsIDs = explode( '|', (string) $_POST['_acf_delete_fields'] );
				$deletedFieldsIDs = array_map( 'intval', $deletedFieldsIDs );

				foreach ( $deletedFieldsIDs as $id ) {
					if ( ! $id ) {
						continue;
					}

					$field_info = acf_get_field( $id );

					if ( ! $field_info ) {
						continue;
					}

					$this->oldAndNewFieldGroupsAndFields['deletedFields'][ $id ] = $field_info;
				}
			}

			// Store info about added or modified fields
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $_POST['acf_fields'] ) && is_array( $_POST['acf_fields'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				foreach ( $_POST['acf_fields'] as $oneFieldAddedOrUpdated ) {
					if ( empty( $oneFieldAddedOrUpdated['ID'] ) ) {
						// New fields have no id
						// 'ID' => string(0) ""
						$this->oldAndNewFieldGroupsAndFields['addedFields'][] = $oneFieldAddedOrUpdated;
					} else {
						// Existing fields have an id
						// 'ID' => string(3) "383"
						$this->oldAndNewFieldGroupsAndFields['modifiedFields']['old'][ $oneFieldAddedOrUpdated['ID'] ] = acf_get_field( $oneFieldAddedOrUpdated['ID'] );

						$this->oldAndNewFieldGroupsAndFields['modifiedFields']['new'][ $oneFieldAddedOrUpdated['ID'] ] = $oneFieldAddedOrUpdated;
					}
				}
			}

			// We don't do anything else here, but we make the actual logging
			// in filter 'acf/update_field_group' because it's safer because
			// ACF has done it's validation and it's after ACF has saved the fields,
			// so less likely that we make some critical error
		}


		/**
		 * Add the post types that ACF uses for fields to the array of post types
		 * that the default post logger should not log. If not each field will cause one
		 * post update log message.
		 */
		public function remove_acf_from_postlogger( $skip_posttypes ) {
			array_push(
				$skip_posttypes,
				'acf-field'
			);

			return $skip_posttypes;
		}
	}
} // End if().
