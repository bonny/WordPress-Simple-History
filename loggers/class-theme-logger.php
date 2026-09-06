<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Item_Table_Row_RAW_Formatter;
use Simple_History\Helpers;

/**
 * Logs WordPress theme edits
 */
class Theme_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleThemeLogger';

	/**
	 * Used to collect information about a theme before it is deleted.
	 * Theme info is stored with css file as the key.
	 *
	 * @var array<string,array<mixed>> Array of theme data.
	 */
	protected $themes_data = [];

	/**
	 * Used to store results from upgrader_install_package_result for theme updates.
	 * Used to detect rollback scenarios (WordPress 6.3+ feature).
	 *
	 * Structure: [
	 *   'theme-slug' => [
	 *     'result' => array|WP_Error,
	 *     'rollback_will_occur' => bool,
	 *     'rollback_info' => [
	 *       'backup_slug' => string,
	 *       'backup_dir' => string,
	 *       'error_code' => string,
	 *       'error_message' => string,
	 *     ]
	 *   ]
	 * ]
	 *
	 * @var array<string,array<string,mixed>> Array with theme slug as key.
	 */
	protected $package_results = [];

	/**
	 * Return logger info
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => __( 'Theme Logger', 'simple-history' ),
			'description' => __( 'Logs theme edits', 'simple-history' ),
			'capability'  => 'edit_theme_options',
			'messages'    => array(
				'theme_switched'            => __( 'Switched theme to "{theme_name}" from "{prev_theme_name}"', 'simple-history' ),
				'theme_installed'           => __( 'Installed theme "{theme_name}" by {theme_author}', 'simple-history' ),
				'theme_deleted'             => __( 'Deleted theme "{theme_name}"', 'simple-history' ),
				'theme_updated'             => __( 'Updated theme "{theme_name}" to version {theme_version} from {theme_prev_version}', 'simple-history' ),
				'theme_update_failed'       => __( 'Failed to update theme "{theme_name}"', 'simple-history' ),
				'theme_downgraded'          => __( 'Downgraded theme "{theme_name}" to version {theme_version} from {theme_prev_version}', 'simple-history' ),
				'theme_reinstalled'         => __( 'Reinstalled theme "{theme_name}" version {theme_version}', 'simple-history' ),
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
							'theme_downgraded',
							'theme_reinstalled',
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
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		/**
		 * Fires after the theme is switched.
		 *
		 * @param string   $new_name  Name of the new theme.
		 * @param \WP_Theme $new_theme WP_Theme instance of the new theme.
		 * @param \WP_Theme $old_theme WP_Theme instance of the old theme.
		 */
		add_action( 'switch_theme', array( $this, 'on_switch_theme' ), 10, 3 );

		add_action( 'customize_save', array( $this, 'on_action_customize_save' ) );

		add_action( 'sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_delete' ) );
		add_action( 'sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_add' ) );

		// Detect widget add/remove outside wp-admin (WP-CLI, REST API).
		// Admin path uses sidebar_admin_setup which reads $_POST.
		// This hook fires after the option is written and provides both old and new values.
		add_action( 'update_option_sidebars_widgets', array( $this, 'on_update_option_sidebars_widgets_non_admin' ), 10, 3 );
		add_filter( 'widget_update_callback', array( $this, 'on_widget_update_callback' ), 10, 4 );

		add_action( 'load-appearance_page_custom-background', array( $this, 'on_page_load_custom_background' ) );

		add_filter( 'upgrader_install_package_result', array( $this, 'on_upgrader_install_package_result' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_install' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_update' ), 10, 2 );

		// Runs before the theme files are replaced, which is our only chance to
		// see what version a theme had before it was updated.
		add_filter( 'upgrader_pre_install', array( $this, 'save_versions_before_update' ), 10, 2 );

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
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'author'      => $theme->get( 'Author' ),
			'description' => $theme->get( 'Description' ),
			'themeuri'    => $theme->get( 'ThemeURI' ),
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
				'theme_slug'        => $stylesheet,
				'theme_name'        => $theme_data['name'],
				'theme_version'     => $theme_data['version'],
				'theme_author'      => $theme_data['author'],
				'theme_description' => $theme_data['description'],
			)
		);
	}

	/**
	 * Fired during theme update process.
	 * Used to detect rollback scenarios (WordPress 6.3+ feature).
	 *
	 * @param array|\WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array           $hook_extra Extra arguments passed to hooked filters.
	 * @return array|\WP_Error
	 */
	public function on_upgrader_install_package_result( $result, $hook_extra ) {
		// Only handle theme updates.
		if ( ! isset( $hook_extra['theme'] ) ) {
			return $result;
		}

		$theme_slug = $hook_extra['theme'];

		// Store result for later use in on_upgrader_process_complete_theme_update().
		$this->package_results[ $theme_slug ] = [
			'result' => $result,
		];

		// Detect if rollback will occur (WordPress 6.3+ feature).
		// Rollback happens when:
		// 1. This is an update (temp_backup exists in hook_extra).
		// 2. The update failed (result is WP_Error).
		$is_update = isset( $hook_extra['temp_backup'] );
		$has_error = is_wp_error( $result );

		if ( $is_update && $has_error ) {
			$this->package_results[ $theme_slug ]['rollback_will_occur'] = true;
			$this->package_results[ $theme_slug ]['rollback_info']       = [
				'backup_slug'   => $hook_extra['temp_backup']['slug'] ?? '',
				'backup_dir'    => $hook_extra['temp_backup']['dir'] ?? '',
				'error_code'    => $result->get_error_code(),
				'error_message' => $result->get_error_message(),
			];
		}

		return $result;
	}

	/**
	 * Saves the version of all installed themes to an option, before they are updated.
	 * The option is not autoloaded and is overwritten on every subsequent update,
	 * so it is intentionally not deleted afterwards (same approach as the
	 * plugin logger's equivalent `save_versions_before_update()`).
	 * Fired from filter `upgrader_pre_install`.
	 *
	 * @param bool|\WP_Error $bool_or_error Default null.
	 * @param array          $hook_extra Default null.
	 * @return bool|\WP_Error Unmodified $bool_or_error, passed through.
	 */
	public function save_versions_before_update( $bool_or_error = null, $hook_extra = null ) {
		$themes_before_update = [];

		foreach ( wp_get_themes() as $theme_slug => $theme_object ) {
			$themes_before_update[ $theme_slug ] = $theme_object->get( 'Version' );
		}

		update_option(
			$this->get_slug() . '_theme_info_before_update',
			Helpers::json_encode( $themes_before_update ),
			false
		);

		return $bool_or_error;
	}

	/**
	 * Log theme updated.
	 *
	 * @param \WP_Upgrader $upgrader_instance WP_Upgrader instance.
	 * @param array<mixed> $arr_data          Array of bulk item update data.
	 * @return void
	 */
	public function on_upgrader_process_complete_theme_update( $upgrader_instance = null, $arr_data = null ) {
		// phpcs:disable Squiz.PHP.CommentedOutCode.Found -- Documentation showing data structure.

		/*
		 * For theme updates $arr_data looks like

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
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found

		// Both args must be set.
		if ( empty( $upgrader_instance ) || empty( $arr_data ) ) {
			return;
		}

		// Check that required data is set.
		if ( empty( $arr_data['type'] ) || empty( $arr_data['action'] ) ) {
			return;
		}

		// Must be type theme and action install.
		if ( $arr_data['type'] !== 'theme' || $arr_data['action'] !== 'update' ) {
			return;
		}

		// If single install make an array so it look like bulk and we can use same code.
		if ( isset( $arr_data['bulk'] ) && $arr_data['bulk'] && isset( $arr_data['themes'] ) ) {
			$arr_themes = (array) $arr_data['themes'];
		} else {
			$arr_themes = array(
				$arr_data['theme'],
			);
		}

		// Get the versions themes had before the update, saved in save_versions_before_update().
		$themes_before_update = json_decode( get_option( $this->get_slug() . '_theme_info_before_update', false ), true );

		// $one_updated_theme is the theme slug
		foreach ( $arr_themes as $one_updated_theme ) {
			$theme_info_object = wp_get_theme( $one_updated_theme );

			if ( ! is_a( $theme_info_object, 'WP_Theme' ) ) {
				continue;
			}

			$theme_name    = $theme_info_object->get( 'Name' );
			$theme_version = $theme_info_object->get( 'Version' );

			if ( ! $theme_name ) {
				continue;
			}

			// Get the previous version, if we have it stored.
			$theme_prev_version = null;
			if ( is_array( $themes_before_update ) && isset( $themes_before_update[ $one_updated_theme ] ) ) {
				$theme_prev_version = $themes_before_update[ $one_updated_theme ];
			}

			// Check if update failed (result is WP_Error).
			$package_result = $this->package_results[ $one_updated_theme ] ?? null;
			$has_error      = $package_result && is_wp_error( $package_result['result'] );

			if ( $has_error ) {
				// Theme update failed.
				$context = [
					'theme_slug' => $one_updated_theme,
					'theme_name' => $theme_name,
				];

				if ( $theme_prev_version !== null ) {
					$context['theme_prev_version'] = $theme_prev_version;
				}

				// Add rollback context if rollback will occur.
				$context = $this->add_rollback_context( $context, $one_updated_theme );

				$this->warning_message(
					'theme_update_failed',
					$context
				);
			} else {
				// Theme update succeeded.
				if ( ! $theme_version ) {
					continue;
				}

				$context = [
					'theme_name'    => $theme_name,
					'theme_version' => $theme_version,
				];

				if ( $theme_prev_version !== null ) {
					$context['theme_prev_version'] = $theme_prev_version;
				}

				$this->info_message(
					'theme_updated',
					$context
				);
			}
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

		// Uploading a zip of a theme that is already installed ("Replace installed
		// with uploaded") also arrives here, as an install with overwrite. The
		// version snapshot taken on upgrader_pre_install tells the two apart: a
		// theme present before was replaced, and the two versions say whether
		// that was an update, a downgrade or a reinstall of the same version.
		$themes_before_update = json_decode( get_option( $this->get_slug() . '_theme_info_before_update', false ), true );

		if ( is_array( $themes_before_update ) && isset( $themes_before_update[ $destination_name ] ) ) {
			$context = array(
				'theme_name'    => $new_theme_data['Name'],
				'theme_version' => $new_theme_data['Version'],
			);

			$prev_version = (string) $themes_before_update[ $destination_name ];

			if ( $prev_version !== '' ) {
				$context['theme_prev_version'] = $prev_version;
			}

			$this->info_message(
				Plugin_Logger::get_replace_message_key( 'theme', $prev_version, (string) $new_theme_data['Version'] ),
				$context
			);

			return;
		}

		$this->info_message(
			'theme_installed',
			array(
				'theme_slug'        => $destination_name,
				'theme_name'        => $new_theme_data['Name'],
				'theme_version'     => $new_theme_data['Version'],
				'theme_author'      => $new_theme_data['Author'],
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
			'reset-background'      => 1,
			'remove-background'     => 1,
			'background-repeat'     => 1,
			'background-position-x' => 1,
			'background-attachment' => 1,
			'background-color'      => 1,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$valid_post_key_exists = array_intersect_key( $arr_valid_post_keys, $_POST );

		if ( empty( $valid_post_key_exists ) ) {
			return;
		}

		$context = array();

		$this->info_message(
			'custom_background_changed',
			$context
		);
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

		// Needed to get sections and controls in sorted order.
		$customize_manager->prepare_controls();

		$settings = $customize_manager->settings();
		$controls = $customize_manager->controls();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Recommended
		$customized = json_decode( sanitize_text_field( wp_unslash( $_REQUEST['customized'] ) ) );

		foreach ( $customized as $setting_id => $posted_values ) {
			foreach ( $settings as $one_setting ) {
				if ( $one_setting->id !== $setting_id ) {
					continue;
				}

				$old_value = $one_setting->value();
				$new_value = $one_setting->post_value();

				// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Loose comparison intentional to avoid false diffs when types differ.
				if ( $old_value == $new_value ) {
					continue;
				}

				$context = array(
					'setting_id'        => $one_setting->id,
					'setting_old_value' => $old_value,
					'setting_new_value' => $new_value,
				);

				// value is changed
				// find which control it belongs to.
				foreach ( $controls as $one_control ) {
					foreach ( $one_control->settings as $section_control_setting ) {
						if ( $section_control_setting->id !== $setting_id ) {
							continue;
						}

						$context['control_id']    = $one_control->id;
						$context['control_label'] = $one_control->label;
						$context['control_type']  = $one_control->type;
					}
				}

				$this->info_message(
					'appearance_customized',
					$context
				);
			}
		}
	}

	/**
	 * @param string    $new_name  Name of the new theme.
	 * @param \WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @param \WP_Theme $old_theme WP_Theme instance of the old theme.
	 * @return void
	 */
	public function on_switch_theme( $new_name, $new_theme, $old_theme ) {

		$this->info_message(
			'theme_switched',
			array(
				'theme_name'         => $new_name,
				'theme_version'      => $new_theme->version,
				'prev_theme_name'    => $old_theme->name ?? null,
				'prev_theme_version' => $old_theme->version ?? null,
			)
		);
	}

	/**
	 * Get detailed output for a row.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];

		if ( $message_key !== 'appearance_customized' ) {
			return '';
		}

		if ( ! isset( $context['setting_old_value'] ) || ! isset( $context['setting_new_value'] ) ) {
			return '';
		}

		if ( empty( $context['setting_old_value'] ) && empty( $context['setting_new_value'] ) ) {
			return '';
		}

		$group = new Event_Details_Group();

		// Section row.
		if ( ! empty( $context['section_id'] ) ) {
			$group->add_item(
				( new Event_Details_Item( 'section_id', __( 'Section', 'simple-history' ) ) )
			);
		}

		// Color controls need RAW formatter for the color swatches.
		$control_type = $context['control_type'] ?? '';

		if ( $control_type === 'color' ) {
			$new_swatch = $this->get_color_swatch_html( $context['setting_new_value'] );
			$old_swatch = $this->get_color_swatch_html( $context['setting_old_value'] );

			$group->add_item(
				( new Event_Details_Item( null, __( 'New value', 'simple-history' ) ) )
					->set_formatter(
						( new Event_Details_Item_Table_Row_RAW_Formatter() )
							->set_html_output( $new_swatch )
					)
			);
			$group->add_item(
				( new Event_Details_Item( null, __( 'Old value', 'simple-history' ) ) )
					->set_formatter(
						( new Event_Details_Item_Table_Row_RAW_Formatter() )
							->set_html_output( $old_swatch )
					)
			);
		} else {
			$group->add_item(
				( new Event_Details_Item( null, __( 'Value', 'simple-history' ) ) )
					->set_values( $context['setting_new_value'], $context['setting_old_value'] )
			);
		}

		return $group;
	}

	/**
	 * Build the HTML for a colour swatch, falling back to the plain value.
	 *
	 * The value is validated with sanitize_hex_color() rather than escaped, because it
	 * ends up inside a style attribute. esc_attr() leaves `;`, `:`, `(` and `/` intact —
	 * harmless in an ordinary attribute, but enough to append extra CSS declarations
	 * here. `control_type` is the *control* type, so a theme is free to bind a colour
	 * control to a setting whose sanitize_callback lets any string through.
	 *
	 * @since 5.28.0
	 * @param string $value Colour value from the customizer setting.
	 * @return string Swatch HTML, or the escaped value when it is not a valid hex colour.
	 */
	private function get_color_swatch_html( $value ) {
		$hex = sanitize_hex_color( '#' . ltrim( trim( (string) $value ), '#' ) );

		if ( $hex === null ) {
			return esc_html( $value );
		}

		return sprintf(
			'<span style="background-color: %1$s; width: 1em; display: inline-block;">&nbsp;</span> %2$s',
			esc_attr( $hex ),
			esc_html( $value )
		);
	}

	/**
	 * Add widget name and sidebar name to output
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_plain_text_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];
		$message     = $row->message;
		$output      = '';

		// Widget changed or added or removed
		// Simple replace widget_id_base and sidebar_id with widget name and sidebar name.
		if ( in_array( $message_key, array( 'widget_added', 'widget_edited', 'widget_removed' ), true ) ) {
			$widget  = $this->get_widget_by_id_base( $context['widget_id_base'] );
			$sidebar = $this->get_sidebar_by_id( $context['sidebar_id'] );

			if ( $widget && $sidebar ) {
				// Translate message first.
				$translated_message = $this->get_translated_message( $message_key );

				if ( $translated_message !== null ) {
					// Returning from this override skips the parent's esc_html(), so
					// escape the interpolated values here instead.
					$message = helpers::interpolate(
						$translated_message,
						$this->esc_html_context_keys(
							array(
								'widget_id_base' => $widget->name,
								'sidebar_id'     => $sidebar['name'],
							),
							[ 'widget_id_base', 'sidebar_id' ]
						),
						$row
					);

					$output .= $message;
				}
			}
		}

		// Theme updated, but the version the theme had before the update is unknown.
		// Happens for events logged before we started saving it, and for themes whose
		// own updater never fires `upgrader_pre_install`. Without this the unresolved
		// placeholder would render literally, as "from {theme_prev_version}".
		if ( $message_key === 'theme_updated' && ! isset( $context['theme_prev_version'] ) ) {
			$context = $this->esc_html_context_keys( $context, [ 'theme_name', 'theme_version' ] );

			$output .= Helpers::interpolate(
				__( 'Updated theme "{theme_name}" to version {theme_version}', 'simple-history' ),
				$context,
				$row
			);
		}

		// Fallback to default/parent output if nothing was added to output.
		if ( $output === '' ) {
			$output .= parent::get_log_row_plain_text_output( $row );
		}

		return $output;
	}

	/**
	 * A widget is changed, i.e. new values are saved
	 *
	 * @param array<mixed> $instance       The current widget instance's settings.
	 * @param array<mixed> $new_instance   Array of new widget settings.
	 * @param array<mixed> $old_instance   Array of old widget settings.
	 * @param \WP_Widget   $widget_instance WP_Widget instance.
	 * @return array<mixed> Original instance.
	 */
	public function on_widget_update_callback( $instance, $new_instance, $old_instance, $widget_instance ) {
		// If old_instance is empty then this widget has just been added
		// and we log that as "Added" not "Edited".
		if ( empty( $old_instance ) ) {
			return $instance;
		}

		$widget_id_base = $widget_instance->id_base;

		$context = array();

		// Add widget info.
		$context['widget_id_base'] = $widget_id_base;
		$widget                    = $this->get_widget_by_id_base( $widget_id_base );
		if ( $widget ) {
			$context['widget_name_translated'] = $widget->name;
		}

		// Add sidebar info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sidebar_id            = sanitize_text_field( wp_unslash( $_POST['sidebar'] ?? '' ) );
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
		if ( ! isset( $_POST['add_new'] ) || empty( $_POST['add_new'] ) || ! isset( $_POST['sidebar'] ) || ! isset( $_POST['id_base'] ) ) {
			return;
		}

		// Add widget info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$widget_id_base            = sanitize_text_field( wp_unslash( $_POST['id_base'] ) );
		$context['widget_id_base'] = $widget_id_base;
		$widget                    = $this->get_widget_by_id_base( $widget_id_base );
		if ( $widget ) {
			$context['widget_name_translated'] = $widget->name;
		}

		// Add sidebar info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sidebar_id            = sanitize_text_field( wp_unslash( $_POST['sidebar'] ) );
		$context['sidebar_id'] = $sidebar_id;
		$sidebar               = $this->get_sidebar_by_id( $sidebar_id );

		if ( is_array( $sidebar ) ) {
			$context['sidebar_name_translated'] = $sidebar['name'];
		}

		$this->info_message(
			'widget_added',
			$context
		);
	}

	/**
	 * Widget deleted.
	 *
	 * @return void
	 */
	public function on_action_sidebar_admin_setup__detect_widget_delete() {
		// Widget was deleted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['delete_widget'] ) ) {
			return;
		}

		$context = array();

		// Add widget info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$widget_id_base            = sanitize_text_field( wp_unslash( $_POST['id_base'] ) );
		$context['widget_id_base'] = $widget_id_base;
		$widget                    = $this->get_widget_by_id_base( $widget_id_base );
		if ( $widget ) {
			$context['widget_name_translated'] = $widget->name;
		}

		// Add sidebar info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$sidebar_id            = sanitize_text_field( wp_unslash( $_POST['sidebar'] ) );
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
	 * @param string $widget_id_base ID base of widget.
	 * @return \WP_Widget|false wp_widget object or false on failure
	 */
	public function get_widget_by_id_base( $widget_id_base ) {

		$widget_factory = $GLOBALS['wp_widget_factory'] ?? false;

		if ( ! $widget_factory ) {
			return false;
		}

		foreach ( $widget_factory->widgets as $one_widget ) {
			if ( $one_widget->id_base === $widget_id_base ) {
				return $one_widget;
			}
		}

		return false;
	}

	/**
	 * Detect widget additions and removals made outside wp-admin (WP-CLI, REST).
	 *
	 * The admin path uses sidebar_admin_setup hooks that read $_POST directly.
	 * This hook fires after the option is written with both old and new values,
	 * so we can diff them to log the same events for non-admin contexts.
	 *
	 * @param mixed  $old_value Previous sidebars_widgets value.
	 * @param mixed  $new_value New sidebars_widgets value.
	 * @param string $option    Option name (always 'sidebars_widgets').
	 * @return void
	 */
	public function on_update_option_sidebars_widgets_non_admin( $old_value, $new_value, $option ) {
		// Admin uses sidebar_admin_setup + $_POST for widget changes; skip to avoid double-logging.
		if ( is_admin() ) {
			return;
		}

		if ( $old_value === $new_value ) {
			return;
		}

		$old_value = (array) $old_value;
		$new_value = (array) $new_value;

		$all_sidebar_ids = array_unique( array_merge( array_keys( $old_value ), array_keys( $new_value ) ) );

		foreach ( $all_sidebar_ids as $sidebar_id ) {
			// Skip WordPress internal array_version key.
			if ( $sidebar_id === 'array_version' ) {
				continue;
			}

			$old_widgets = (array) ( $old_value[ $sidebar_id ] ?? array() );
			$new_widgets = (array) ( $new_value[ $sidebar_id ] ?? array() );

			// Reorders within the same sidebar (same widget IDs, different positions)
			// produce empty diffs and are intentionally not logged.
			$changes = array(
				'widget_added'   => array_diff( $new_widgets, $old_widgets ),
				'widget_removed' => array_diff( $old_widgets, $new_widgets ),
			);

			foreach ( $changes as $message_key => $widget_ids ) {
				foreach ( $widget_ids as $widget_id ) {
					$this->log_widget_change( $message_key, $widget_id, $sidebar_id );
				}
			}
		}
	}

	/**
	 * Log a single widget add/remove event.
	 *
	 * @param string $message_key 'widget_added' or 'widget_removed'.
	 * @param string $widget_id   Full widget id (e.g. "text-99").
	 * @param string $sidebar_id  Sidebar id.
	 */
	private function log_widget_change( $message_key, $widget_id, $sidebar_id ) {
		$widget_id_base = _get_widget_id_base( $widget_id );

		$context = array(
			'widget_id_base' => $widget_id_base,
			'sidebar_id'     => $sidebar_id,
		);

		$widget = $this->get_widget_by_id_base( $widget_id_base );

		if ( $widget ) {
			$context['widget_name_translated'] = $widget->name;
		}

		$this->info_message( $message_key, $context );
	}

	/**
	 * Add rollback context to event if rollback will occur.
	 *
	 * @param array  $context Context array.
	 * @param string $theme_slug Theme slug.
	 * @return array Modified context array.
	 */
	private function add_rollback_context( $context, $theme_slug ) {
		// Check if rollback will occur (WordPress 6.3+ feature).
		$package_result = $this->package_results[ $theme_slug ] ?? null;
		if ( $package_result && ! empty( $package_result['rollback_will_occur'] ) ) {
			$context['rollback_will_occur'] = true;
			if ( ! empty( $package_result['rollback_info'] ) ) {
				$context['rollback_backup_slug']   = $package_result['rollback_info']['backup_slug'];
				$context['rollback_error_code']    = $package_result['rollback_info']['error_code'];
				$context['rollback_error_message'] = $package_result['rollback_info']['error_message'];
			}
		}

		return $context;
	}
}
