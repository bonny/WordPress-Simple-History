<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Loggers\Simple_Logger;
use Simple_History\Loggers\Logger;

/**
 * Class that load loggers.
 */
class Loggers_Loader extends Service {
	/**
	 * Bool if gettext filter function should be active
	 * Should only be active during the load of a logger
	 *
	 * @var bool
	 */
	private bool $do_filter_gettext = false;

	/**
	 * Used by gettext filter to temporarily store current logger.
	 *
	 * @var \Simple_History\Loggers\Logger
	 */
	private $do_filter_gettext_current_logger;

	/** @inheritdoc */
	public function loaded() {
		add_action( 'after_setup_theme', array( $this, 'load_loggers' ) );
	}

	/**
	 * Instantiates built in loggers.
	 */
	public function load_loggers() {
		// Bail if we are not in filter after_setup_theme,
		// i.e. we are probably calling SimpleLogger() early.
		// TODO: Test if this is still needed, after adding autoloading of classes.
		if ( ! doing_action( 'after_setup_theme' ) ) {
			return;
		}

		$arr_loggers_to_instantiate = $this->simple_history->get_core_loggers();

		$instantiated_loggers = $this->simple_history->get_instantiated_loggers();

		/**
		 * Fires after the list of loggers to load are populated.
		 *
		 * Can for example be used by plugin to load their own custom loggers.
		 *
		 * See register_logger() for more info.
		 *
		 * @since 2.1
		 *
		 * @param Simple_History $instance Simple History instance.
		 */
		do_action( 'simple_history/add_custom_logger', $this->simple_history );

		/** @var Logger[] $arr_loggers_to_instantiate */
		$arr_loggers_to_instantiate = array_merge( $arr_loggers_to_instantiate, $this->simple_history->get_external_loggers() );

		/**
		 * Filter the array with class names of loggers to instantiate.
		 *
		 * Array
		 * (
		 *  [0] => SimpleHistory\Loggers\SimpleUserLogger
		 *  [1] => SimpleHistory\Loggers\SimplePostLogger
		 *   ...
		 * )
		 *
		 * @since 2.0
		 *
		 * @param array<Logger> $arr_loggers_to_instantiate Array with class names
		 */
		/** @var Logger[] $arr_loggers_to_instantiate */
		$arr_loggers_to_instantiate = apply_filters(
			'simple_history/loggers_to_instantiate',
			$arr_loggers_to_instantiate
		);

		// Add gettext filters so we can get untranslated messages.
		add_filter( 'gettext', array( $this, 'filter_gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 20, 4 );

		// Instantiate each logger.
		foreach ( $arr_loggers_to_instantiate as $one_logger_class ) {
			$is_valid_logger_subclass = is_subclass_of( $one_logger_class, Logger::class );
			$is_valid_old_simplelogger_subclass = is_subclass_of( $one_logger_class, \SimpleLogger::class );

			if ( ! $is_valid_logger_subclass && ! $is_valid_old_simplelogger_subclass ) {
				continue;
			}

			/** @var Simple_Logger $logger_instance */
			$logger_instance = new $one_logger_class( $this->simple_history );

			// Call loaded() function on logger if logger is enabled.
			if ( $logger_instance->is_enabled() ) {
				$logger_instance->loaded();
			}

			// Tell gettext-filter to add untranslated messages.
			$this->do_filter_gettext = true;
			$this->do_filter_gettext_current_logger = $logger_instance;

			$logger_info = $logger_instance->get_info();

			// Check so no logger has a logger slug with more than 30 chars,
			// because db column is only 30 chars.
			if ( strlen( $logger_instance->get_slug() ) > 30 ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						// translators: 1: logger slug, 2: logger name.
						esc_html( __( 'A logger slug can be max 30 chars long. Slug %1$s of logger %2$s is to long.', 'simple-history' ) ),
						esc_html( $logger_instance->get_slug() ),
						esc_html( $logger_instance->get_info_value_by_key( 'name' ) )
					),
					'3.0'
				);
			}

			// Check that logger has a slug set.
			if ( empty( $logger_instance->get_slug() ) ) {
				_doing_it_wrong(
					__METHOD__,
					esc_html( __( 'A logger is missing a slug.', 'simple-history' ) ),
					'4.0'
				);
			}

			// Check that logger has a name set.
			if ( ! isset( $logger_info['name'] ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						// translators: 1: logger slug.
						esc_html( __( 'Logger %1$s is missing a name.', 'simple-history' ) ),
						esc_html( $logger_instance->get_slug() ),
					),
					'4.0'
				);
			}

			// Un-tell gettext filter.
			$this->do_filter_gettext = false;
			$this->do_filter_gettext_current_logger = null;

			// LoggerInfo contains all messages, both translated an not, by key.
			// Add messages to the loggerInstance.
			$arr_messages_by_message_key = array();

			// Check that required content in messages array exist.
			if ( isset( $logger_info['messages'] ) && is_array( $logger_info['messages'] ) ) {
				foreach ( $logger_info['messages'] as $message_key => $message_translated ) {
					// Find message in array with both translated and non translated strings.
					foreach ( $logger_instance->messages as $one_message_with_translation_info ) {
						if ( $message_translated == $one_message_with_translation_info['translated_text'] ) {
							$arr_messages_by_message_key[ $message_key ] = $one_message_with_translation_info;
							continue;
						}
					}
				}
			}

			$logger_instance->messages = $arr_messages_by_message_key;

			$instantiated_loggers[ $logger_instance->get_slug() ] = array(
				'name' => $logger_instance->get_info_value_by_key( 'name' ),
				'instance' => $logger_instance,
			);
		} // End foreach().

		$this->simple_history->set_instantiated_loggers( $instantiated_loggers );

		// Remove getText filters.
		remove_filter( 'gettext', array( $this, 'filter_gettext' ), 20 );
		remove_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 20 );

		/**
		 * Fired when all loggers are instantiated.
		 *
		 * @deprecated 3.0 Use action `simple_history/loggers/instantiated` instead.
		 *
		 * @since 3.0
		 */
		do_action( 'simple_history/loggers_loaded' );

		/**
		 * Fired when all loggers are instantiated.
		 *
		 * @since 4.0
		 */
		do_action( 'simple_history/loggers/instantiated', $this->simple_history );
	}

	/**
	 * Store both translated and untranslated versions of a text.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $untranslated_text Untranslated text.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 * @return string Translated text.
	 */
	public function filter_gettext( $translated_text, $untranslated_text, $domain ) {
		if ( $this->do_filter_gettext ) {
			$this->do_filter_gettext_current_logger->messages[] = array(
				'untranslated_text' => $untranslated_text,
				'translated_text' => $translated_text,
				'domain' => $domain,
				'context' => null,
			);
		}

		return $translated_text;
	}

	/**
	 * Store both translated and untranslated versions of a text with context.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $untranslated_text Untranslated text.
	 * @param string $context Context information for the translators.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 * @return string Translated text.
	 */
	public function filter_gettext_with_context( $translated_text, $untranslated_text, $context, $domain ) {
		if ( $this->do_filter_gettext ) {
			$this->do_filter_gettext_current_logger->messages[] = array(
				'untranslated_text' => $untranslated_text,
				'translated_text' => $translated_text,
				'domain' => $domain,
				'context' => $context,
			);
		}

		return $translated_text;
	}
}
