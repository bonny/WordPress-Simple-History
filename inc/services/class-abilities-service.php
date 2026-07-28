<?php

namespace Simple_History\Services;

/**
 * Register Simple History abilities with the WordPress Abilities API.
 *
 * The Abilities API lets AI agents and automation tools discover what a site
 * can do. It landed in WordPress 6.9; Simple History supports 6.3+, so
 * registration is conditional and silently does nothing on older versions.
 *
 * Every ability here is read-only. Simple History deliberately registers no
 * write or destructive abilities: the value of an audit log is that it is
 * tamper-evident, and an agent that can purge the log destroys that.
 *
 * Abilities delegate to existing REST routes through rest_do_request() rather
 * than querying directly. Simple History's per-logger visibility filtering
 * happens inside Log_Query, not in the permission callback, so delegating is
 * what keeps abilities from over-exposing events.
 */
class Abilities_Service extends Service {
	/**
	 * Category slug that Simple History abilities are grouped under.
	 *
	 * @var string
	 */
	private const CATEGORY = 'simple-history';

	/** @inheritDoc */
	public function loaded() {
		// Abilities API is WordPress 6.9+. Bail quietly on older versions.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register every Simple History ability.
	 */
	public function register_abilities() {
		$this->register_category();

		// Abilities are added by later tasks.
	}

	/**
	 * Register the category Simple History abilities are grouped under.
	 */
	private function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Simple History', 'simple-history' ),
				'description' => __( 'Read the site activity log.', 'simple-history' ),
			]
		);
	}
}
