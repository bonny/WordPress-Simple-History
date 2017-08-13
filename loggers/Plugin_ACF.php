<?php

defined( 'ABSPATH' ) || die();

// Only enable in development mode.
if ( ! defined( 'SIMPLE_HISTORY_DEV' ) || ! SIMPLE_HISTORY_DEV ) {
	return;
}

/**
 * Logger for the Advanced Custom Fields (ACF) plugin
 * https://sv.wordpress.org/plugins/advanced-custom-fields/
 *
 * @TODO
 * - store fields diff
 *
 * @package SimpleHistory
 * @since 2.x
 */
if (! class_exists("Plugin_ACF")) {

    class Plugin_ACF extends SimpleLogger
    {
        public $slug = __CLASS__;

        private $oldAndNewFieldGroupsAndFields = array(
        	'fieldGroup' => array(
        		'old' => null,
        		'new' => null
        	),
        	'modifiedFields' => array(
        		'old' => null,
        		'new' => null
        	),
        	'addedFields' => array(),
        	'deletedFields' => array(),
    	);

        public function getInfo()
        {
            $arr_info = array(
                "name" => "Plugin ACF",
                "description" => _x("Logs ACF stuff", "Logger: Plugin Duplicate Post", "simple-history"),
                "name_via" => _x("Using plugin ACF", "Logger: Plugin Duplicate Post", "simple-history"),
                "capability" => "manage_options",
                "messages" => array(
                    'post_duplicated' => _x('Cloned "{duplicated_post_title}" to a new post', "Logger: Plugin Duplicate Post", 'simple-history')
                ),
            );

            return $arr_info;
        }

        public function loaded()
        {

        	// Bail if no ACF found
        	if (!function_exists('acf_verify_nonce')) {
        		return;
        	}

        	$this->remove_acf_from_postlogger();

        	/*
			possible filters to use
			do_action('acf/update_field_group', $field_group);
			apply_filters( "acf/update_field", $field); (return field)
			do_action('acf/trash_field_group', $field_group);
        	*/
        	#add_action('acf/update_field_group', array($this, 'on_update_field_group'), 10);

        	// This is the action that Simple History and the post logger uses to log
       		add_action( 'transition_post_status', array($this, 'on_transition_post_status'), 5, 3);

        	// ACF calls save_post with prio 10, we call it earlier to be able to get field info before it might be deleted.
			#add_action('save_post', array($this, 'on_save_post'), 5, 2);

			// Get prev version of acf field group
			// This is called before transition_post_status
			add_filter('wp_insert_post_data', array($this, 'on_wp_insert_post_data'), 10, 2);

        	add_filter('simple_history/post_logger/post_updated/context', array($this, 'on_post_updated_context'), 10, 2);

			add_filter('simple_history/post_logger/post_updated/diff_table_output', array($this, 'on_diff_table_output'), 10, 2 );
        }

        public function on_diff_table_output($diff_table_output, $context) {

        	// Check for keys that begin with 'acf_'
        	$keyPrefixToCheckFor = 'acf_';

        	$arrKeys = array(
				'instruction_placement' => array(
					'name' => 'Instruction placement'
				),
				'label_placement' => array(
					'name' => 'Label placement'
				),
				'description' => array(
					'name' => 'Description'
				),
				'menu_order' => array(
					'name' => 'Menu order'
				),
				'position' => array(
					'name' => 'Position'
				),
				'active' => array(
					'name' => 'Active'
				),
				'style' => array(
					'name' => 'Style'
				),
        	);

        	foreach ($arrKeys as $acfKey => $acfVals) {
        		if (isset($context["acf_new_$acfKey"]) && isset($context["acf_prev_$acfKey"])) {
					$diff_table_output .= sprintf(
						'<tr>
							<td>%1$s</td>
							<td>
								<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%2$s</ins>
								<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%3$s</del>
							</td>
						</tr>',
						$acfVals['name'],
						esc_html($context["acf_new_$acfKey"]),
						esc_html($context["acf_prev_$acfKey"])
					);
        		}
        	}

        	// acf_hide_on_screen_added
        	// acf_hide_on_screen_removed
        	if (!empty($context["acf_hide_on_screen_added"])) {
				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>
							%4$s %2$s
							<br>
							%5$s %3$s
						</td>
					</tr>',
					__('Hide on screen'),
					esc_html($context['acf_hide_on_screen_added']),
					esc_html($context['acf_hide_on_screen_removed']),
					__('Checked'), // 4
					__('Unchecked') // 5
				);
        	}
        	/*foreach ($context as $contextKey => $contextVal) {
        		if (strpos($contextKey, $keyPrefixToCheckFor) !== 0) {
        			continue;
        		}

        		$diff_table_output .= '<tr><td>found acf key: ' . $contextKey . '</td></tr>';
        	}
        	*/

        	return $diff_table_output;
        }

       	public function on_post_updated_context($context, $post) {
        	$acf_data_diff = array();

        	# 'fieldGroup' fields to check
        	$arr_keys_to_diff = array(
				'menu_order',
				'position',
				'style',
				'label_placement',
				'instruction_placement',
				'active',
				'description',
        	);

        	$fieldGroup = $this->oldAndNewFieldGroupsAndFields['fieldGroup'];

        	foreach ( $arr_keys_to_diff as $key ) {
        		if (isset($fieldGroup['old'][$key]) && isset($fieldGroup['new'][$key])) {
        			$acf_data_diff = $this->add_diff($acf_data_diff, $key, (string) $fieldGroup['old'][$key], (string) $fieldGroup['new'][$key]);
        		}
        	}

        	foreach ( $acf_data_diff as $diff_key => $diff_values ) {
				$context["acf_prev_{$diff_key}"] = $diff_values["old"];
				$context["acf_new_{$diff_key}"] = $diff_values["new"];
        	}

			$arrhHideOnScreenAdded = [];
			$arrHideOnScreenRemoved = [];
        	if (!empty($fieldGroup['new']['hide_on_screen']) && !empty($fieldGroup['old']['hide_on_screen'])) {
        		$arrhHideOnScreenAdded = array_diff($fieldGroup['new']['hide_on_screen'], $fieldGroup['old']['hide_on_screen']);

        		$arrHideOnScreenRemoved = array_diff($fieldGroup['old']['hide_on_screen'], $fieldGroup['new']['hide_on_screen']);

        		if ($arrhHideOnScreenAdded) {
        			$context["acf_hide_on_screen_added"] = implode(',', $arrhHideOnScreenAdded);
        		}

        		if ($arrHideOnScreenRemoved) {
        			$context["acf_hide_on_screen_removed"] = implode(',', $arrHideOnScreenRemoved);
        		}

        	}
        	#dd($acf_data_diff);
        	#dd('on_post_updated_context', $context, $this->oldAndNewFieldGroupsAndFields);
        	return $context;

        	// 'modifiedFields'
        	// 'addedFields'
        	// 'deletedFields'
        }

		function add_diff($post_data_diff, $key, $old_value, $new_value) {
			if ( $old_value != $new_value ) {
				$post_data_diff[$key] = array(
					"old" => $old_value,
					"new" => $new_value
				);
			}

			return $post_data_diff;
		}

        /**
         * Store a version of the field group as it was before the save
         * Called before field group post/values is added to db
         */
        public function on_wp_insert_post_data($data, $postarr) {

        	// Only do this if ACF field group is being saved
        	if ($postarr['post_type'] !== 'acf-field-group') {
        		return $data;
        	}

        	if (empty($postarr['ID'])) {
        		return $data;
        	}

       		$this->oldAndNewFieldGroupsAndFields['fieldGroup']['old'] = acf_get_field_group($postarr['ID']);

			$this->oldAndNewFieldGroupsAndFields['fieldGroup']['new'] = acf_get_valid_field_group($_POST['acf_field_group']);

        	return $data;
        }

        /**
         * ACF field group is saved
         * Called before ACF calls its save_post filter
         * Here we save the new fields values and also get the old values so we can compare
         */
		#public function on_save_post( $post_id, $post ) {
        public function on_transition_post_status($new_status, $old_status, $post) {

			static $isCalled = false;

			if ($isCalled) {
				// echo "is called already, bail out";exit;
				return;
			}

			$isCalled = true;

			$post_id = $post->ID;

			// do not act if this is an auto save routine
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
				return;
			}

			// bail early if not acf-field-group
			if ( $post->post_type !== 'acf-field-group' ) {
				return;
			}

			// only save once! WordPress save's a revision as well.
			if ( wp_is_post_revision($post_id) ) {
		    	return;
	        }

			// Store info about fields that are going to be deleted
			if (!empty($_POST['_acf_delete_fields'])) {
		    	$deletedFieldsIDs = explode('|', $_POST['_acf_delete_fields']);
		    	$deletedFieldsIDs = array_map( 'intval', $deletedFieldsIDs );

				foreach ( $deletedFieldsIDs as $id ) {
					if ( !$id ) {
						continue;
					}

					$field_info = acf_get_field($id);

					if (!$field_info) {
						continue;
					}

					$this->oldAndNewFieldGroupsAndFields['deletedFields'][$id] = $field_info;
				}

			}

			// Store info about added or modified fields
			if (!empty($_POST['acf_fields']) && is_array($_POST['acf_fields'])) {
				foreach ($_POST['acf_fields'] as $oneFieldAddedOrUpdated) {
					if (empty($oneFieldAddedOrUpdated['ID'])) {
						// New fields have no id
						// 'ID' => string(0) ""
						$this->oldAndNewFieldGroupsAndFields['addedFields'][] = $oneFieldAddedOrUpdated;
					} else {
						// Existing fields have an id
						// 'ID' => string(3) "383"
						$this->oldAndNewFieldGroupsAndFields['modifiedFields']['old'][$oneFieldAddedOrUpdated['ID']] = acf_get_field($oneFieldAddedOrUpdated['ID']);

						$this->oldAndNewFieldGroupsAndFields['modifiedFields']['new'][$oneFieldAddedOrUpdated['ID']] = $oneFieldAddedOrUpdated;
					}
				}
			}

			// We don't do anything else here, but we make the actual logging
			// in filter 'acf/update_field_group' beacuse it's safer because
			// ACF has done it's validation and it's after ACF has saved the fields,
			// so less likely that we make some critical error

		}

        public function on_update_field_group($field_group) {
        	/*echo '<pre>on_update_field_group';
        	print_r($field_group);
        	print_r(acf_get_fields($field_group));
        	exit;*/

        	#echo "yo";
        	#!dd($this->oldAndNewFieldGroupsAndFields);
        	#exit;

        	#$this->oldAndNewFieldGroupsAndFields['fieldGroup']['new'] = $field_group;

        	// Now compare old and new values and store in context
			#ddd($this->oldAndNewFieldGroupsAndFields);

        	/*



			On admin post save:

			- $_POST['acf_fields'] is only set when a new field or subfield is added or changed,
				when a field is deleted is contains a subset somehow..

			- calls acf_update_field()
				$field = apply_filters( "acf/update_field", $field);

			- $_POST['_acf_delete_fields'] is only set when a field is deleted
				contains string like "0|328" with the id's that have been removed
				do_action( "acf/delete_field", $field);

			- then lastly field group is updated

			// Get field group
			$field_group = acf_get_field_group( $selector );
			// Get field
			acf_get_field()
			// Get fields in field group
			$fields = acf_get_fields($field_group);

			Field group data looks like
			Array
			(
			    [ID] => 326
			    [key] => group_59819a4beefe0
			    [title] => A new field group in ACF
			    [fields] => Array
			        (
			        )

			    [location] => Array
			        (
			            [0] => Array
			                (
			                    [0] => Array
			                        (
			                            [param] => post_type
			                            [operator] => ==
			                            [value] => post
			                        )

			                )

			        )

			    [menu_order] => 5
			    [position] => normal
			    [style] => default
			    [label_placement] => top
			    [instruction_placement] => label
			    [hide_on_screen] => Array
			        (
			            [0] => the_content
			            [1] => custom_fields
			            [2] => format
			            [3] => featured_image
			            [4] => categories
			            [5] => tags
			        )

			    [active] => 1
			    [description] => Description of field group
			)

			Fields data looks like:

			Array
			(
			    [0] => Array
			        (
			            [ID] => 327
			            [key] => field_59819a5867afb
			            [label] => One field in ACF
			            [name] => one_field_in_acf
			            [prefix] => acf
			            [type] => text
			            [value] =>
			            [menu_order] => 0
			            [instructions] =>
			            [required] => 0
			            [id] =>
			            [class] =>
			            [conditional_logic] => 0
			            [parent] => 326
			            [wrapper] => Array
			                (
			                    [width] =>
			                    [class] =>
			                    [id] =>
			                )

			            [_name] => one_field_in_acf
			            [_prepare] => 0
			            [_valid] => 1
			            [default_value] =>
			            [placeholder] =>
			            [prepend] =>
			            [append] =>
			            [maxlength] =>
			        )

			    [1] => Array
			        (
			            [ID] => 328
			            [key] => field_59819acb51846
			            [label] => Another field
			            [name] => another_field
			            [prefix] => acf
			            [type] => text
			            [value] =>
			            [menu_order] => 1
			            [instructions] =>
			            [required] => 0
			            [id] =>
			            [class] =>
			            [conditional_logic] => 0
			            [parent] => 326
			            [wrapper] => Array
			                (
			                    [width] =>
			                    [class] =>
			                    [id] =>
			                )

			            [_name] => another_field
			            [_prepare] => 0
			            [_valid] => 1
			            [default_value] =>
			            [placeholder] =>
			            [prepend] =>
			            [append] =>
			            [maxlength] =>
			        )

			    [2] => Array
			        (
			            [ID] => 364
			            [key] => field_59837ec284708
			            [label] => A brand new field yoyo
			            [name] => a_brand_new_field_yoyo
			            [prefix] => acf
			            [type] => text
			            [value] =>
			            [menu_order] => 2
			            [instructions] =>
			            [required] => 0
			            [id] =>
			            [class] =>
			            [conditional_logic] => 0
			            [parent] => 326
			            [wrapper] => Array
			                (
			                    [width] =>
			                    [class] =>
			                    [id] =>
			                )

			            [_name] => a_brand_new_field_yoyo
			            [_prepare] => 0
			            [_valid] => 1
			            [default_value] =>
			            [placeholder] =>
			            [prepend] =>
			            [append] =>
			            [maxlength] =>
			        )

			    [3] => Array
			        (
			            [ID] => 372
			            [key] => field_59842bdd72001
			            [label] => This field will have sub fields and stuff
			            [name] => this_field_will_have_sub_fields_and_stuff
			            [prefix] => acf
			            [type] => repeater
			            [value] =>
			            [menu_order] => 3
			            [instructions] => This is the instruction text
			            [required] => 0
			            [id] =>
			            [class] =>
			            [conditional_logic] => 0
			            [parent] => 326
			            [wrapper] => Array
			                (
			                    [width] =>
			                    [class] =>
			                    [id] =>
			                )

			            [_name] => this_field_will_have_sub_fields_and_stuff
			            [_prepare] => 0
			            [_valid] => 1
			            [collapsed] =>
			            [min] => 0
			            [max] => 0
			            [layout] => table
			            [button_label] =>
			            [sub_fields] => Array
			                (
			                    [0] => Array
			                        (
			                            [ID] => 373
			                            [key] => field_59842bf772002
			                            [label] => Subfield that is a repeater
			                            [name] => subfield_that_is_a_repeater
			                            [prefix] => acf
			                            [type] => repeater
			                            [value] =>
			                            [menu_order] => 0
			                            [instructions] => This in instruction for sub field that is repeater
			                            [required] => 0
			                            [id] =>
			                            [class] =>
			                            [conditional_logic] => 0
			                            [parent] => 372
			                            [wrapper] => Array
			                                (
			                                    [width] =>
			                                    [class] =>
			                                    [id] =>
			                                )

			                            [_name] => subfield_that_is_a_repeater
			                            [_prepare] => 0
			                            [_valid] => 1
			                            [collapsed] =>
			                            [min] => 0
			                            [max] => 0
			                            [layout] => table
			                            [button_label] =>
			                            [sub_fields] => Array
			                                (
			                                    [0] => Array
			                                        (
			                                            [ID] => 375
			                                            [key] => field_59842c1d72004
			                                            [label] => Repeater sub field 2
			                                            [name] => repeater_sub_field_2
			                                            [prefix] => acf
			                                            [type] => wysiwyg
			                                            [value] =>
			                                            [menu_order] => 0
			                                            [instructions] =>
			                                            [required] => 0
			                                            [id] =>
			                                            [class] =>
			                                            [conditional_logic] => 0
			                                            [parent] => 373
			                                            [wrapper] => Array
			                                                (
			                                                    [width] =>
			                                                    [class] =>
			                                                    [id] =>
			                                                )

			                                            [_name] => repeater_sub_field_2
			                                            [_prepare] => 0
			                                            [_valid] => 1
			                                            [default_value] =>
			                                            [tabs] => all
			                                            [toolbar] => full
			                                            [media_upload] => 1
			                                            [delay] => 0
			                                        )

			                                    [1] => Array
			                                        (
			                                            [ID] => 374
			                                            [key] => field_59842c1472003
			                                            [label] => Repeater sub field 1
			                                            [name] => repeater_sub_field_1
			                                            [prefix] => acf
			                                            [type] => text
			                                            [value] =>
			                                            [menu_order] => 1
			                                            [instructions] =>
			                                            [required] => 0
			                                            [id] =>
			                                            [class] =>
			                                            [conditional_logic] => 0
			                                            [parent] => 373
			                                            [wrapper] => Array
			                                                (
			                                                    [width] =>
			                                                    [class] =>
			                                                    [id] =>
			                                                )

			                                            [_name] => repeater_sub_field_1
			                                            [_prepare] => 0
			                                            [_valid] => 1
			                                            [default_value] =>
			                                            [placeholder] =>
			                                            [prepend] =>
			                                            [append] =>
			                                            [maxlength] =>
			                                        )

			                                )

			                        )

			                    [1] => Array
			                        (
			                            [ID] => 376
			                            [key] => field_59842c6d4dd9c
			                            [label] => Plain subfield
			                            [name] => plain_subfield
			                            [prefix] => acf
			                            [type] => text
			                            [value] =>
			                            [menu_order] => 1
			                            [instructions] =>
			                            [required] => 0
			                            [id] =>
			                            [class] =>
			                            [conditional_logic] => 0
			                            [parent] => 372
			                            [wrapper] => Array
			                                (
			                                    [width] =>
			                                    [class] =>
			                                    [id] =>
			                                )

			                            [_name] => plain_subfield
			                            [_prepare] => 0
			                            [_valid] => 1
			                            [default_value] =>
			                            [placeholder] =>
			                            [prepend] =>
			                            [append] =>
			                            [maxlength] =>
			                        )

			                )

			        )

			)
        	*/

        }

        /**
         * Add the post types that ACF uses for fields to the array of post types
         * that the post logger should not log
         */
        public function remove_acf_from_postlogger() {
        	add_filter('simple_history/post_logger/skip_posttypes', function($skip_posttypes) {
        		array_push(
        			$skip_posttypes,
        			// 'acf-field-group',
        			'acf-field'
        		);

        		return $skip_posttypes;
        	}, 10);
        }

    } // class
} // class exists
