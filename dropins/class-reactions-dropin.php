<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin Name: Reactions
 * Dropin Description: Settings field to enable or disable event reactions.
 */
class Reactions_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		$this->register_settings();

		add_action( 'simple_history/settings_page/general_section_output', [ $this, 'on_general_section_output' ] );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			$this->simple_history::SETTINGS_GENERAL_OPTION_GROUP,
			'simple_history_reactions_enabled',
			[
				'type'              => 'string',
				'default'           => '1',
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
		add_settings_field(
			'simple_history_reactions',
			Helpers::get_settings_field_title_output( __( 'Reactions', 'simple-history' ) ),
			[ $this, 'settings_field' ],
			$this->simple_history::SETTINGS_MENU_SLUG,
			$this->simple_history::SETTINGS_SECTION_GENERAL_ID
		);
	}

	/**
	 * Settings field output.
	 */
	public function settings_field() {
		$enabled = Helpers::reactions_are_enabled();

		?>
		<label>
			<input <?php checked( $enabled ); ?> type="checkbox" value="1" name="simple_history_reactions_enabled" />
			<?php esc_html_e( 'Enable reactions', 'simple-history' ); ?>
		</label>

		<p class="description">
			<?php esc_html_e( 'Let people react to events with a 👍. The button only shows on hover, so it stays out of the way.', 'simple-history' ); ?>
		</p>

		<p class="description">
			<?php esc_html_e( 'Want more ways to react? ❤️ 🎉 🚀 and more come with Premium.', 'simple-history' ); ?>
		</p>
		<?php
	}
}
