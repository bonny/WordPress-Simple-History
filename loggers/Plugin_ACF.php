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

        	// This is the action that Simple History and the post logger uses to log
       		add_action( 'transition_post_status', array($this, 'on_transition_post_status'), 5, 3);

			// Get prev version of acf field group
			// This is called before transition_post_status
			add_filter('wp_insert_post_data', array($this, 'on_wp_insert_post_data'), 10, 2);

        	add_filter('simple_history/post_logger/post_updated/context', array($this, 'on_post_updated_context'), 10, 2);

			add_filter('simple_history/post_logger/post_updated/diff_table_output', array($this, 'on_diff_table_output'), 10, 2 );
        }

        /**
         * Called from PostLogger and its diff table output using filter 'simple_history/post_logger/post_updated/diff_table_output'
         * @param string $diff_table_output
         * @param array $context
         * @return string
         */
        public function on_diff_table_output($diff_table_output, $context) {
        	// Field group fields to check for and output if found
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

        	// Check for deleted fields
        	if (isset($context['acf_deleted_fields_0_key'])) {
        		// 1 or more deleted fields exist in context
        		$loopnum = 0;
        		$strDeletedFields = '';

        		while (isset($context["acf_deleted_fields_{$loopnum}_key"])) {
					$strDeletedFields .= sprintf(
						'%1$s (%3$s), ',
						esc_html($context["acf_deleted_fields_{$loopnum}_label"]),
						esc_html($context["acf_deleted_fields_{$loopnum}_name"]),
						esc_html($context["acf_deleted_fields_{$loopnum}_type"])
					);

					$loopnum++;
        		}

        		$strDeletedFields = trim($strDeletedFields, ', ');

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>',
					_nx('Deleted field', 'Deleted fields', $loopnum, 'Logger: ACF', 'simple-history'), // 1
					$strDeletedFields
				);
        	} // if deleted fields

        	// Check for added fields
        	if (isset($context['acf_added_fields_0_key'])) {
        		// 1 or more deleted fields exist in context
        		$loopnum = 0;
        		$strAddedFields = '';

        		while (isset($context["acf_added_fields_{$loopnum}_key"])) {
					$strAddedFields .= sprintf(
						'%1$s (%3$s), ',
						esc_html($context["acf_added_fields_{$loopnum}_label"]),
						esc_html($context["acf_added_fields_{$loopnum}_name"]),
						esc_html($context["acf_added_fields_{$loopnum}_type"])
					);

					$loopnum++;
        		}

        		$strAddedFields = trim($strAddedFields, ', ');

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>',
					_nx('Added field', 'Added fields', $loopnum, 'Logger: ACF', 'simple-history'), // 1
					$strAddedFields
				);
        	} // if deleted fields

        	// Check for modified fields
        	if (isset($context['acf_modified_fields_0_ID_prev'])) {
        		// 1 or more modifiedfields exist in context
        		$loopnum = 0;
        		$strModifiedFields = '';
        		$arrAddedFieldsKeysToCheck = array(
        			'name' => array(
        				'name' => 'Name: ',
        			),
        			'parent' => array(
        				'name' => 'Parent: '
        			),
        			'key' => array(
        				'name' => 'Key: ',
        			),
        			'label' => array(
        				'name' => 'Label: ',
        			),
        			'type' => array(
        				'name' => 'Type: ',
        			),
        		);

        		while (isset($context["acf_modified_fields_{$loopnum}_name_prev"])) {
        			// One modified field, with one or more changed things
        			$strOneModifiedField = '';

        			// Add the field name manually, if it is not among the changed field,
        			// or we don't know what field the other changed values belongs to.
        			if (empty($context["acf_modified_fields_{$loopnum}_name_new"])) {
						$strOneModifiedField .= sprintf(
							_x('Name: %1$s', 'Logger: ACF', 'simple-history'), // 1
							esc_html($context["acf_modified_fields_{$loopnum}_name_prev"]) // 2
						);
					}

					// Check for other keys changed for this field
					foreach ($arrAddedFieldsKeysToCheck as $oneAddedFieldKeyToCheck => $oneAddedFieldKeyToCheckVals) {
						$newAndOldValsExists = isset($context["acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_new"]) && isset($context["acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_new"]);
		        		if ($newAndOldValsExists) {
							$strOneModifiedField .= sprintf(
								'
									%4$s
									%3$s
									<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>
									<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>
								',
								esc_html($context["acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_new"]), // 1
								esc_html($context["acf_modified_fields_{$loopnum}_{$oneAddedFieldKeyToCheck}_prev"]), // 2
								esc_html($oneAddedFieldKeyToCheckVals['name']), // 3
								empty($strOneModifiedField) ? '' : '<br>' // 4 new line
							);
		        		}
					}

	        		$strOneModifiedField = trim($strOneModifiedField, ", \n\r\t");

					if ($strOneModifiedField) {
						$strModifiedFields .= sprintf(
							'<tr>
								<td>%1$s</td>
								<td>%2$s</td>
							</tr>',
							_x('Modified field', 'Logger: ACF', 'simple-history'), // 1
							$strOneModifiedField
						);
					}

					$loopnum++;
        		}

        		/*if ($strModifiedFields) {
					$strModifiedFields = sprintf(
						'<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>',
						_nx('Modified field', 'Modified fields', $loopnum, 'Logger: ACF', 'simple-history'), // 1
						$strModifiedFields
					) . $strModifiedFields;
				}*/

				$diff_table_output .= $strModifiedFields;
        	} // if deleted fields

        	return $diff_table_output;
        }

       	public function on_post_updated_context($context, $post) {
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
        		if (isset($fieldGroup['old'][$key]) && isset($fieldGroup['new'][$key])) {
        			$acf_data_diff = $this->add_diff($acf_data_diff, $key, (string) $fieldGroup['old'][$key], (string) $fieldGroup['new'][$key]);
        		}
        	}

        	foreach ( $acf_data_diff as $diff_key => $diff_values ) {
				$context["acf_prev_{$diff_key}"] = $diff_values["old"];
				$context["acf_new_{$diff_key}"] = $diff_values["new"];
        	}

        	// Add checked or uncheckd hide on screen-items to context
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

        	// Add removed fields to context
        	if (!empty($this->oldAndNewFieldGroupsAndFields['deletedFields']) && is_array($this->oldAndNewFieldGroupsAndFields['deletedFields'])) {
        		$loopnum = 0;
        		foreach ($this->oldAndNewFieldGroupsAndFields['deletedFields'] as $oneDeletedField) {
        			$context["acf_deleted_fields_{$loopnum}_key"] = $oneDeletedField['key'];
        			$context["acf_deleted_fields_{$loopnum}_name"] = $oneDeletedField['name'];
        			$context["acf_deleted_fields_{$loopnum}_label"] = $oneDeletedField['label'];
        			$context["acf_deleted_fields_{$loopnum}_type"] = $oneDeletedField['type'];
        			$loopnum++;
        		}
        	}

        	// Add added fields to context
        	if (!empty($this->oldAndNewFieldGroupsAndFields['addedFields']) && is_array($this->oldAndNewFieldGroupsAndFields['addedFields'])) {
        		$loopnum = 0;

        		foreach ($this->oldAndNewFieldGroupsAndFields['addedFields'] as $oneAddedField) {
        			// Id not available here, wold be nice to have
        			// $context["acf_added_fields_{$loopnum}_ID"] = $oneAddedField['ID'];
        			$context["acf_added_fields_{$loopnum}_key"] = $oneAddedField['key'];
        			$context["acf_added_fields_{$loopnum}_name"] = $oneAddedField['name'];
        			$context["acf_added_fields_{$loopnum}_label"] = $oneAddedField['label'];
        			$context["acf_added_fields_{$loopnum}_type"] = $oneAddedField['type'];
        			$loopnum++;
        		}
        	}

        	// Add modified fields to context
        	#dd('on_post_updated_context', $context, $this->oldAndNewFieldGroupsAndFields);
        	if (!empty($this->oldAndNewFieldGroupsAndFields['modifiedFields']['old']) && !empty($this->oldAndNewFieldGroupsAndFields['modifiedFields']['new'])) {
        		$modifiedFields = $this->oldAndNewFieldGroupsAndFields['modifiedFields'];

        		#dd($modifiedFields);

        		$arrAddedFieldsKeysToAdd = array(
        			'parent',
        			'key',
        			'label',
        			'name',
        			'type',
        		);

        		$loopnum = 0;

        		foreach ($modifiedFields['old'] as $modifiedFieldId => $modifiedFieldValues) {
        			// Both old and new values mest exist
        			if (empty($modifiedFields['new'][$modifiedFieldId])) {
        				continue;
        			}

        			// Always add the ID and the name
					$context["acf_modified_fields_{$loopnum}_ID_prev"] = $modifiedFields['old'][$modifiedFieldId]['ID'];
					$context["acf_modified_fields_{$loopnum}_name_prev"] = $modifiedFields['old'][$modifiedFieldId]['name'];

        			foreach ($arrAddedFieldsKeysToAdd as $oneKeyToAdd) {
        				#dd($modifiedFields);
        				// Only add to context if modified
        				if ($modifiedFields['new'][$modifiedFieldId][$oneKeyToAdd] != $modifiedFields['old'][$modifiedFieldId][$oneKeyToAdd]) {
		        			$context["acf_modified_fields_{$loopnum}_{$oneKeyToAdd}_prev"] = $modifiedFields['old'][$modifiedFieldId][$oneKeyToAdd];
		        			$context["acf_modified_fields_{$loopnum}_{$oneKeyToAdd}_new"] = $modifiedFields['new'][$modifiedFieldId][$oneKeyToAdd];
	        			}
        			}

	        		$loopnum++;
        		}
        	}

        	return $context;

        	#dd($acf_data_diff);
        	// 'modifiedFields'
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
