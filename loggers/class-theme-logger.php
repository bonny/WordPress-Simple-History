<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logs WordPress theme edits
 */
class Theme_Logger extends Logger {
	public $slug = 'SimpleThemeLogger';

	/** @var array<string> When switching themes, this will contain info about the theme we are switching from. */
	private ?array $prev_theme_data = null;

	/**
	 * Used to collect information about a theme before it is deleted.
	 * Theme info is stored with css file as the key.
	 *
	 * @var array<string,array<mixed>> Array of theme data.
	 */
	protected $themes_data = array();

	public function get_info() {
		$arr_info = array(
			'name'        => __( 'Theme Logger', 'simple-history' ),
			'description' => __( 'Logs theme edits', 'simple-history' ),
			'capability'  => 'edit_theme_options',
			'messages'    => array(
				'theme_switched'            => __( 'Switched theme to "{theme_name}" from "{prev_theme_name}"', 'simple-history' ),
				'theme_installed'           => __( 'Installed theme "{theme_name}" by {theme_author}', 'simple-history' ),
				'theme_deleted'             => __( 'Deleted theme "{theme_name}"', 'simple-history' ),
				'theme_updated'             => __( 'Updated theme "{theme_name}"', 'simple-history' ),
				'appearance_customized'     => __( 'Customized theme appearance "{setting_id}"', 'simple-history' ),
				'widget_removed'            => __( 'Removed widget "{widget_id_base}" from sidebar "{sidebar_id}"', 'simple-history' ),
				'widget_added'              => __( 'Added widget "{widget_id_base}" to sidebar "{sidebar_id}"', 'simple-history' ),
				'widget_order_changed'      => __( 'Changed widget order "{widget_id_base}" in sidebar "{sidebar_id}"', 'simple-history' ),
				'widget_edited'             => __( 'Changed widget "{widget_id_base}" in sidebar "{sidebar_id}"', 'simple-history' ),
				'custom_background_changed' => __( 'Changed settings for the theme custom background', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Themes & Widgets', 'Theme logger: search', 'simple-history' ),
					'label_all' => _x( 'All theme activity', 'Theme logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Updated themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_updated',
						),
						_x( 'Deleted themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_deleted',
						),
						_x( 'Installed themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_installed',
						),
						_x( 'Switched themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_switched',
						),
						_x( 'Changed appearance of themes', 'Theme logger: search', 'simple-history' ) => array(
							'appearance_customized',
						),
						_x( 'Added widgets', 'Theme logger: search', 'simple-history' ) => array(
							'widget_added',
						),
						_x( 'Removed widgets', 'Theme logger: search', 'simple-history' ) => array(
							'widget_removed',
						),
						_x( 'Changed widgets order', 'Theme logger: search', 'simple-history' ) => array(
							'widget_order_changed',
						),
						_x( 'Edited widgets', 'Theme logger: search', 'simple-history' ) => array(
							'widget_edited',
						),
						_x( 'Background of themes changed', 'Theme logger: search', 'simple-history' ) => array(
							'custom_background_changed',
						),
					),
				), // end search array
			), // end labels
		);

		return $arr_info;
	}

	public function loaded() {
		/**
		 * Fires after the theme is switched.
		 *
		 * @param string   $new_name  Name of the new theme.
		 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
		 */
		add_action( 'switch_theme', array( $this, 'on_switch_theme' ), 10, 2 );
		add_action( 'load-themes.php', array( $this, 'on_page_load_themes' ) );

		add_action( 'customize_save', array( $this, 'on_action_customize_save' ) );

		add_action( 'sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_delete' ) );
		add_action( 'sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_add' ) );
		// add_action("wp_ajax_widgets-order", array( $this, "on_action_sidebar_admin_setup__detect_widget_order_change"), 1 );
		// add_action("sidebar_admin_setup", array( $this, "on_action_sidebar_admin_setup__detect_widget_edit") );
		add_filter( 'widget_update_callback', array( $this, 'on_widget_update_callback' ), 10, 4 );

		add_action( 'load-appearance_page_custom-background', array( $this, 'on_page_load_custom_background' ) );

		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_install' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_update' ), 10, 2 );

		// Log theme deletion.
		add_action( 'delete_theme', array( $this, 'on_action_delete_theme' ), 10, 1 );
		add_action( 'deleted_theme', array( $this, 'on_action_deleted_theme' ), 10, 2 );

		add_filter( 'simple_history/post_logger/skip_posttypes', array( $this, 'skip_customize_changeset_posttype_from_postlogger' ) );
	}

	/**
	 * Don't log changes to post type "customize_changeset".
	 *
	 * @param array<string> $skip_posttypes Array of post types to skip logging for.
	 * @return array<string>
	 */
	public function skip_customize_changeset_posttype_from_postlogger( $skip_posttypes ) {
		$skip_posttypes[] = 'customize_changeset';
		return $skip_posttypes;
	}

	/**
	 * Store information about a theme before the theme is deleted.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @return void
	 */
	public function on_action_delete_theme( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );

		$this->themes_data[ $stylesheet ] = array(
			'name' => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'author' => $theme->get( 'Author' ),
			'description' => $theme->get( 'Description' ),
			'themeuri' => $theme->get( 'ThemeURI' ),
		);
	}

	/**
	 * Log theme deletion.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @param bool   $deleted    Whether the theme deletion was successful.
	 * @return void
	 */
	public function on_action_deleted_theme( $stylesheet, $deleted ) {
		if ( ! $deleted || ! $stylesheet ) {
			return;
		}

		if ( empty( $this->themes_data[ $stylesheet ] ) ) {
			return;
		}

		$theme_data = $this->themes_data[ $stylesheet ];

		$this->info_message(
			'theme_deleted',
			array(
				'theme_slug' => $stylesheet,
				'theme_name' => $theme_data['name'],
				'theme_version' => $theme_data['version'],
				'theme_author' => $theme_data['author'],
				'theme_description' => $theme_data['description'],
			)
		);
	}

	/**
	 * Log theme updated.
	 *
	 * @param \WP_Upgrader $upgrader_instance WP_Upgrader instance.
	 * @param array<mixed> $arr_data          Array of bulk item update data.
	 * @return void
	 */
	public function on_upgrader_process_complete_theme_update( $upgrader_instance = null, $arr_data = null ) {
		/*
		For theme updates $arr_data looks like

		// From core update/bulk
		Array
		(
			[action] => update
			[type] => theme
			[bulk] => 1
			[themes] => Array
				(
					[0] => baskerville
				)

		)

		// From themes.php/single
		Array
		(
			[action] => update
			[theme] => baskerville
			[type] => theme
		)
		*/

		// Both args must be set
		if ( empty( $upgrader_instance ) || empty( $arr_data ) ) {
			return;
		}

		// Check that required data is set.
		if ( empty( $arr_data['type'] ) || empty( $arr_data['action'] ) ) {
			return;
		}

		// Must be type theme and action install
		if ( $arr_data['type'] !== 'theme' || $arr_data['action'] !== 'update' ) {
			return;
		}

		// If single install make an array so it look like bulk and we can use same code
		if ( isset( $arr_data['bulk'] ) && $arr_data['bulk'] && isset( $arr_data['themes'] ) ) {
			$arr_themes = (array) $arr_data['themes'];
		} else {
			$arr_themes = array(
				$arr_data['theme'],
			);
		}

		/*
		ob_start();
		print_r($skin);
		$skin_str = ob_get_clean();
		echo "<pre>";
		print_r($arr_data);
		print_r($skin);
		// */

		// $one_updated_theme is the theme slug
		foreach ( $arr_themes as $one_updated_theme ) {
			$theme_info_object = wp_get_theme( $one_updated_theme );

			if ( ! is_a( $theme_info_object, 'WP_Theme' ) ) {
				continue;
			}

			$theme_name = $theme_info_object->get( 'Name' );
			$theme_version = $theme_info_object->get( 'Version' );

			if ( ! $theme_name || ! $theme_version ) {
				continue;
			}

			$this->info_message(
				'theme_updated',
				array(
					'theme_name' => $theme_name,
					'theme_version' => $theme_version,
				)
			);
		}
	}

	/**
	 * Log theme installation.
	 *
	 * @param \WP_Upgrader $upgrader_instance WP_Upgrader instance.
	 * @param array<mixed> $arr_data Array of bulk item update data.
	 * @return void
	 */
	public function on_upgrader_process_complete_theme_install( $upgrader_instance = null, $arr_data = null ) {
		// Both args must be set.
		if ( empty( $upgrader_instance ) || empty( $arr_data ) ) {
			return;
		}

		// Check that required data is set.
		if ( empty( $arr_data['type'] ) || empty( $arr_data['action'] ) ) {
			return;
		}

		// Must be type 'theme' and action 'install'.
		if ( $arr_data['type'] !== 'theme' || $arr_data['action'] !== 'install' ) {
			return;
		}

		if ( empty( $upgrader_instance->new_theme_data ) ) {
			return;
		}

		// $destination_name is the slug (folder name) of the theme.
		$destination_name = $upgrader_instance->result['destination_name'] ?? '';

		if ( empty( $destination_name ) ) {
			return;
		}

		$theme = wp_get_theme( $destination_name );

		if ( ! $theme->exists() ) {
			return;
		}

		$new_theme_data = $upgrader_instance->new_theme_data;

		$this->info_message(
			'theme_installed',
			array(
				'theme_slug' => $destination_name,
				'theme_name' => $new_theme_data['Name'],
				'theme_version' => $new_theme_data['Version'],
				'theme_author' => $new_theme_data['Author'],
				'theme_description' => $theme->get( 'Description' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function on_page_load_custom_background() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST ) ) {
			return;
		}

		$arr_valid_post_keys = array(
			'reset-background' => 1,
			'remove-background' => 1,
			'background-repeat' => 1,
			'background-position-x' => 1,
			'background-attachment' => 1,
			'background-color' => 1,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$valid_post_key_exists = array_intersect_key( $arr_valid_post_keys, $_POST );

		if ( ! empty( $valid_post_key_exists ) ) {
			$context = array();
			// $context["POST"] = Helpers::json_encode( $_POST );
			$this->info_message(
				'custom_background_changed',
				$context
			);
		}
	}

	/**
	 * @param \WP_Customize_Manager $customize_manager WP_Customize_Manager instance.
	 * @return void
	 */
	public function on_action_customize_save( $customize_manager ) {

		/*
		- Loop through all sections
			- And then through all controls in section
				- Foreach control get it's setting
					- Get each settings prev value
					- Get each settings new value
					- Store changed values
		*/

		/*
		The following sections are built-in:
		------------------------------------
		title_tagline - Site Title & Tagline
		colors - Colors
		header_image - Header Image
		background_image - Background Image
		nav - Navigation
		static_front_page - Static Front Page
		*/

		// Needed to get sections and controls in sorted order
		$customize_manager->prepare_controls();

		$settings = $customize_manager->settings();
		$controls = $customize_manager->controls();

		$customized = json_decode( wp_unslash( $_REQUEST['customized'] ) );

		foreach ( $customized as $setting_id => $posted_values ) {
			foreach ( $settings as $one_setting ) {
				if ( $one_setting->id == $setting_id ) {
					$old_value = $one_setting->value();
					$new_value = $one_setting->post_value();

					if ( $old_value != $new_value ) {
						$context = array(
							'setting_id' => $one_setting->id,
							'setting_old_value' => $old_value,
							'setting_new_value' => $new_value,
						);

						// value is changed
						// find which control it belongs to
						foreach ( $controls as $one_control ) {
							foreach ( $one_control->settings as $section_control_setting ) {
								if ( $section_control_setting->id == $setting_id ) {
									$context['control_id'] = $one_control->id;
									$context['control_label'] = $one_control->label;
									$context['control_type'] = $one_control->type;
								}
							}
						}

						$this->info_message(
							'appearance_customized',
							$context
						);
					}// End if().
				}// End if().
			}// End foreach().
		}// End foreach().
	}

	/**
	 * When a new theme is about to get switched to
	 * we save info about the old one
	 *
	 * Request looks like:
	 *  Array
	 *  (
	 *    [action] => activate
	 *    [stylesheet] => wp-theme-bonny-starter
	 *    [_wpnonce] => ...
	 *  )
	 *
	 * @return void
	 */
	public function on_page_load_themes() {

		if ( ! isset( $_GET['action'] ) || $_GET['action'] != 'activate' ) {
			return;
		}

		// Get current theme / the theme we are switching from
		$current_theme = wp_get_theme();

		if ( ! is_a( $current_theme, 'WP_Theme' ) ) {
			return;
		}

		$this->prev_theme_data = array(
			'name' => $current_theme->name,
			'version' => $current_theme->version,
		);
	}

	/**
	 * @param string   $new_name  Name of the new theme.
	 * @param \WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @return void
	 */
	public function on_switch_theme( $new_name, $new_theme ) {
		$prev_theme_data = $this->prev_theme_data;

		$this->info_message(
			'theme_switched',
			array(
				'theme_name' => $new_name,
				'theme_version' => $new_theme->version,
				'prev_theme_name' => $prev_theme_data['name'],
				'prev_theme_version' => $prev_theme_data['version'],
			)
		);
	}

	public function get_log_row_details_output( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'];
		$output = '';

		// Theme customizer
		if ( 'appearance_customized' == $message_key ) {
			// if ( ! class_exists("WP_Customize_Manager") ) {
			// require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
			// $wp_customize = new WP_Customize_Manager;
			// }
			// $output .= "<pre>" . print_r($context, true);
			if ( isset( $context['setting_old_value'] ) && isset( $context['setting_new_value'] ) ) {
				$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

				// Output section, if saved
				if ( ! empty( $context['section_id'] ) ) {
					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>
						',
						__( 'Section', 'simple-history' ),
						esc_html( $context['section_id'] )
					);
				}

				// Don't output prev and new value if none exist
				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
				if ( empty( $context['setting_old_value'] ) && empty( $context['setting_new_value'] ) ) {
					// Empty, so skip.
				} else {
					// if control is color let's be fancy and output as color
					$control_type = $context['control_type'] ?? '';
					$str_old_value_prepend = '';
					$str_new_value_prepend = '';

					if ( 'color' == $control_type ) {
						$str_old_value_prepend .= sprintf(
							'<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
							esc_attr( ltrim( $context['setting_old_value'], ' #' ) )
						);

						$str_new_value_prepend .= sprintf(
							'<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
							esc_attr( ltrim( $context['setting_new_value'], '#' ) )
						);
					}

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
						__( 'New value', 'simple-history' ),
						esc_html( $context['setting_new_value'] ),
						$str_new_value_prepend
					);

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
						__( 'Old value', 'simple-history' ),
						esc_html( $context['setting_old_value'] ),
						$str_old_value_prepend
					);
				}// End if().

				$output .= '</table>';
			}// End if().
		}// End if().

		return $output;
	}

	/**
	 * Add widget name and sidebar name to output
	 */
	public function get_log_row_plain_text_output( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'];
		$message = $row->message;
		$output = '';

		// Widget changed or added or removed
		// Simple replace widget_id_base and sidebar_id with widget name and sidebar name
		if ( in_array( $message_key, array( 'widget_added', 'widget_edited', 'widget_removed' ) ) ) {
			$widget = $this->get_widget_by_id_base( $context['widget_id_base'] );
			$sidebar = $this->get_sidebar_by_id( $context['sidebar_id'] );

			if ( $widget && $sidebar ) {
				// Translate message first
				$message = $this->messages[ $message_key ]['translated_text'];

				$message = helpers::interpolate(
					$message,
					array(
						'widget_id_base' => $widget->name,
						'sidebar_id' => $sidebar['name'],
					),
					$row
				);

				$output .= $message;
			}
		}

		// Fallback to default/parent output if nothing was added to output
		if ( $output === '' ) {
			$output .= parent::get_log_row_plain_text_output( $row );
		}

		return $output;
	}

	/*
	function on_action_sidebar_admin_setup__detect_widget_edit() {

		if ( isset( $_REQUEST["action"] ) && ( $_REQUEST["action"] == "save-widget" ) && isset( $_POST["sidebar"] ) && isset( $_POST["id_base"] ) ) {

			$widget_id_base = $_POST["id_base"];

			// a key with widget-{$widget_id_base} exists if we are saving
			if ( ! isset( $_POST["widget-{$widget_id_base}"] ) ) {
				return;
			}

			$context = array();

			$widget_save_data = $_POST["widget-{$widget_id_base}"];
			$context["widget_save_data"] = Helpers::json_encode( $widget_save_data );

			// Add widget info
			$context["widget_id_base"] = $widget_id_base;
			$widget = $this->getWidgetByIdBase( $widget_id_base );
			if ($widget) {
				$context["widget_name_translated"] = $widget->name;
			}

			// Add sidebar info
			$sidebar_id = $_POST["sidebar"];
			$context["sidebar_id"] = $sidebar_id;
			$sidebar = $this->getSidebarById( $sidebar_id );
			if ($sidebar) {
				$context["sidebar_name_translated"] = $sidebar["name"];
			}

			$this->info_message(
				"widget_edited",
				$context
			);

		}

	}
		*/

	/**
	 * A widget is changed, i.e. new values are saved
	 *
	 * @param array<mixed> $instance       The current widget instance's settings.
	 * @param array<mixed> $new_instance   Array of new widget settings.
	 * @param array<mixed> $old_instance   Array of old widget settings.
	 * @param \WP_Widget $widget_instance WP_Widget instance.
	 * @return array<mixed> Original instance.
	 */
	public function on_widget_update_callback( $instance, $new_instance, $old_instance, $widget_instance ) {
		// If old_instance is empty then this widget has just been added
		// and we log that as "Added" not "Edited"
		if ( empty( $old_instance ) ) {
			return $instance;
		}

		$widget_id_base = $widget_instance->id_base;

		$context = array();

		// Add widget info.
		$context['widget_id_base'] = $widget_id_base;
		$widget = $this->get_widget_by_id_base( $widget_id_base );
		if ( $widget ) {
			$context['widget_name_translated'] = $widget->name;
		}

		// Add sidebar info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sidebar_id = $_POST['sidebar'] ?? null;
		$context['sidebar_id'] = $sidebar_id;

		$sidebar = $this->get_sidebar_by_id( $sidebar_id );
		if ( is_array( $sidebar ) ) {
			$context['sidebar_name_translated'] = $sidebar['name'];
		}

		// Calculate changes.
		$context['old_instance'] = Helpers::json_encode( $old_instance );
		$context['new_instance'] = Helpers::json_encode( $new_instance );

		$this->info_message(
			'widget_edited',
			$context
		);

		return $instance;
	}

	/**
	 * Widget added.
	 *
	 * @return void
	 */
	public function on_action_sidebar_admin_setup__detect_widget_add() {
		$context = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['add_new'] ) && ! empty( $_POST['add_new'] ) && isset( $_POST['sidebar'] ) && isset( $_POST['id_base'] ) ) {
			// Add widget info
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$widget_id_base = $_POST['id_base'];
			$context['widget_id_base'] = $widget_id_base;
			$widget = $this->get_widget_by_id_base( $widget_id_base );
			if ( $widget ) {
				$context['widget_name_translated'] = $widget->name;
			}

			// Add sidebar info
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sidebar_id = $_POST['sidebar'];
			$context['sidebar_id'] = $sidebar_id;
			$sidebar = $this->get_sidebar_by_id( $sidebar_id );

			if ( is_array( $sidebar ) ) {
				$context['sidebar_name_translated'] = $sidebar['name'];
			}

			$this->info_message(
				'widget_added',
				$context
			);
		}
	}

	/**
	 * Widget deleted.
	 *
	 * @return void
	 */
	public function on_action_sidebar_admin_setup__detect_widget_delete() {
		// Widget was deleted
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete_widget'] ) ) {
			$context = array();

			// Add widget info.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$widget_id_base = $_POST['id_base'];
			$context['widget_id_base'] = $widget_id_base;
			$widget = $this->get_widget_by_id_base( $widget_id_base );
			if ( $widget ) {
				$context['widget_name_translated'] = $widget->name;
			}

			// Add sidebar info
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sidebar_id = $_POST['sidebar'];
			$context['sidebar_id'] = $sidebar_id;

			$sidebar = $this->get_sidebar_by_id( $sidebar_id );
			if ( is_array( $sidebar ) ) {
				$context['sidebar_name_translated'] = $sidebar['name'];
			}

			$this->info_message(
				'widget_removed',
				$context
			);
		}
	}

	/**
	 * Get a sidebar by id.
	 *
	 * @param string $sidebar_id ID of sidebar.
	 * @return array<string,mixed>|false sidebar info or false on failure.
	 */
	public function get_sidebar_by_id( $sidebar_id ) {

		if ( empty( $sidebar_id ) ) {
			return false;
		}

		$sidebars = $GLOBALS['wp_registered_sidebars'] ?? false;

		if ( ! $sidebars ) {
			return false;
		}

		return $sidebars[ $sidebar_id ] ?? false;
	}

	/**
	 * Get an widget by id's id_base
	 *
	 * @param string $widget_id_base
	 * @return \WP_Widget|false wp_widget object or false on failure
	 */
	public function get_widget_by_id_base( $widget_id_base ) {

		$widget_factory = $GLOBALS['wp_widget_factory'] ?? false;

		if ( ! $widget_factory ) {
			return false;
		}

		foreach ( $widget_factory->widgets as $one_widget ) {
			if ( $one_widget->id_base == $widget_id_base ) {
				return $one_widget;
			}
		}

		return false;
	}
}
