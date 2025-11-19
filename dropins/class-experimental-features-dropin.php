<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin Name: Experimental Features
 * Dropin Description: Tell app to enable experimental features.
 */
class Experimental_Features_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		$this->register_settings();

		add_action( 'simple_history/settings_page/general_section_output', [ $this, 'on_general_section_output' ] );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		$settings_general_option_group = $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP;

		register_setting(
			$settings_general_option_group,
			'simple_history_experimental_features_enabled',
			[
				'sanitize_callback' => [ Helpers::class, 'sanitize_checkbox_input' ],
			]
		);
	}

	/**
	 * Add settings field.
	 *
	 * Function fired from action `simple_history/settings_page/general_section_output`.
	 */
	public function on_general_section_output() {
		$settings_section_general_id = $this->simple_history::SETTINGS_SECTION_GENERAL_ID;
		$settings_menu_slug          = $this->simple_history::SETTINGS_MENU_SLUG;

		add_settings_field(
			'simple_history_experimental_features',
			Helpers::get_settings_field_title_output( __( 'Experimental features', 'simple-history' ) ),
			[ $this, 'settings_field' ],
			$settings_menu_slug,
			$settings_section_general_id
		);
	}

	/**
	 * Settings field output.
	 */
	public function settings_field() {
		$detective_mode_enabled = Helpers::experimental_features_is_enabled();

		?>
		<label>
			<input <?php checked( $detective_mode_enabled ); ?> type="checkbox" value="1" name="simple_history_experimental_features_enabled" />
			<?php esc_html_e( 'Enable experimental features', 'simple-history' ); ?>
		</label>
		
		<p class="description">
			<?php
			esc_html_e( 'Try out upcoming features that are still in testing.', 'simple-history' );
			?>
		</p>
		
		<p class="description">
			<?php
			esc_html_e( 'Please note that these features may not work as expected and could change or be removed in future updates.', 'simple-history' );
			?>
		</p>
		<?php
	}
}
