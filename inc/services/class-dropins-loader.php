<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;

/**
 * Class that load dropins.
 */
class Dropins_Loader extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'after_setup_theme', array( $this, 'load_dropins' ) );
	}

	/**
	 * Loads and instantiates built in dropins.
	 */
	public function load_dropins() {
		$dropins_to_instantiate = $this->simple_history->get_core_dropins();
		$instantiated_dropins   = $this->simple_history->get_instantiated_dropins();

		/**
		 * Fires after the list of dropins to load are populated.
		 * Can for example be used by dropins can to add their own custom loggers.
		 *
		 * See register_dropin() for more info.
		 *
		 * @since 2.3.2
		 *
		 * @param Simple_History $instance Simple History instance.
		 */
		do_action( 'simple_history/add_custom_dropin', $this->simple_history );

		$dropins_to_instantiate = array_merge( $dropins_to_instantiate, $this->simple_history->get_external_dropins() );

		/**
		 * Filter the array with dropin classnames to instantiate.
		 *
		 * @since 3.0
		 *
		 * @param array $dropins_to_instantiate Array with dropin class names.
		 */
		$dropins_to_instantiate = apply_filters( 'simple_history/dropins_to_instantiate', $dropins_to_instantiate );

		// $one_dropin_class is full namespaced class, i.e. 'SimpleHistory\Dropins\SimpleHistoryRSSDropin'.
		foreach ( $dropins_to_instantiate as $one_dropin_class ) {
			$instantiate_dropin = true;

			// Check that dropin exists.
			if ( ! class_exists( $one_dropin_class ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						esc_html(
							// translators: 1: dropin class name.
							__( 'A dropin was not found. Classname was "%1$s".', 'simple-history' )
						),
						esc_html( $one_dropin_class ),
					),
					'4.0'
				);

				continue;
			}

			$dropin_short_name = ( new \ReflectionClass( $one_dropin_class ) )->getShortName();

			/**
			 * Filter to completely skip instantiate a dropin.
			 *
			 * Complete filter name will be something like
			 * `simple_history/dropin/instantiate_SimpleHistoryRSSDropin`
			 *
			 * @example Do not instantiate dropin SimpleHistoryRSSDropin.
			 *
			 * ```php
			 * add_filter( 'simple_history/dropin/instantiate_SimpleHistoryRSSDropin', '__return_false' );
			 * ```
			 *
			 * @since 4.0
			 *
			 * @param bool $instantiate_dropin if to load the dropin. return false to not load it.
			 */
			$instantiate_dropin = apply_filters( "simple_history/dropin/instantiate_{$dropin_short_name}", $instantiate_dropin );

			/**
			 * Filter to completely skip loading of a dropin.
			 *
			 * @since 4.0
			 *
			 * @param bool $instantiate_dropin if to load the dropin. return false to not load it.
			 * @param string $dropin_short_name slug of dropin, i.e. "SimpleHistoryRSSDropin"
			 * @param string $one_dropin_class fully qualified name of class, i.e. "SimpleHistory\Dropins\SimpleHistoryRSSDropin"
			 */
			$instantiate_dropin = apply_filters( 'simple_history/dropin/instantiate', $instantiate_dropin, $dropin_short_name, $one_dropin_class );

			// Bail if dropin should not be instantiated.
			if ( ! $instantiate_dropin ) {
				continue;
			}

			// New dropins must extend Simple_History\Dropins\Dropin,
			// but old dropins are not extending anything,
			// so that's why we do not check type of class, like we do
			// with plugins.
			$dropin_instance = new $one_dropin_class( $this->simple_history );

			if ( method_exists( $dropin_instance, 'loaded' ) ) {
				$dropin_instance->loaded();
			}

			$instantiated_dropins[ $dropin_short_name ] = array(
				'name'     => $dropin_short_name,
				'instance' => $dropin_instance,
			);
		}

		$this->simple_history->set_instantiated_dropins( $instantiated_dropins );

		/**
		 * Fires after all dropins are instantiated.
		 * @since 3.0
		 *
		 * @param Simple_History $instance Simple History instance.
		 */
		do_action( 'simple_history/dropins/instantiated', $this->simple_history );
	}
}
