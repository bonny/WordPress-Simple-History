<?php
namespace Simple_History\Loggers;

/**
 * Logger for translation related things, like translation updated.
 *
 * @package SimpleHistory
 */
class Translations_Logger extends Logger {
	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = 'SH_Translations_Logger';

	/**
	 * Return info about logger.
	 *
	 * @return array Array with plugin info.
	 */
	public function get_info() {
		$arr_info = array(
			'name'        => _x( 'Translation Logger', 'Logger: Translations', 'simple-history' ),
			'description' => _x( 'Log WordPress translation related things', 'Logger: Translations', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'translations_updated' => _x( 'Updated translations for "{name}" ({language})', 'Logger: Translations', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete' ), 10, 2 );
	}

	/**
	 * Called when a translation is updated.
	 * This is called from the upgrader_process_complete hook.
	 *
	 * @param \WP_Upgrader|\Language_Pack_Upgrader $upgrader The WP_Upgrader instance.
	 * @param array                                $options  Array of bulk item update arguments.
	 */
	public function on_upgrader_process_complete( $upgrader, $options ) {

		// Check that required array keys exists.
		if ( ! isset( $options['type'] ) || ! isset( $options['action'] ) ) {
			return;
		}

		if ( 'translation' !== $options['type'] ) {
			return;
		}

		if ( 'update' !== $options['action'] ) {
			return;
		}

		if ( empty( $options['translations'] ) || ! is_array( $options['translations'] ) ) {
			return;
		}

		$translations = $options['translations'];

		foreach ( $translations as $translation ) {
			$name = '';

			// Check that method exists before usage.
			if ( method_exists( $upgrader, 'get_name_for_update' ) ) {
				$name = $upgrader->get_name_for_update( (object) $translation );
			}

			// Name can be empty, this is the case for for example Polylang Pro.
			// If so then use slug as name, so message won't be empty.
			if ( empty( $name ) && ! empty( $translation['slug'] ) ) {
				$name = $translation['slug'];
			}

			$context = array(
				'name'         => $name,
				'language'     => $translation['language'],
				'translations' => $translation,
				'_occasionsID' => self::class . '/translations_updated',
			);

			$this->info_message(
				'translations_updated',
				$context
			);
		}

		/*
		Array
		(
			[action] => update
			[type] => translation
			[bulk] => 1
			[translations] => Array
				(
					[0] => Array
						(
							[language] => de_DE
							[type] => plugin
							[slug] => akismet
							[version] => 4.0.8
						)

					[1] => Array
						(
							[language] => sv_SE
							[type] => plugin
							[slug] => akismet
							[version] => 4.0.8
						)

					[11] => Array
						(
							[language] => sv_SE
							[type] => theme
							[slug] => twentysixteen
							[version] => 1.5
						)
				)

		)

		This is how WordPress writes update messages:
		Updating translations for Yoast SEO (de_DE)…
		Translation updated successfully.

		Updating translations for Yoast SEO (sv_SE)…
		Translation updated successfully.

		Updating translations for Twenty Fifteen (de_DE)…
		Translation updated successfully.

		$name = $this->upgrader->get_name_for_update( $this->language_update );
		*/
	}
}
