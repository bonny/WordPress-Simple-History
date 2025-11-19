<?php

namespace Simple_History;

/**
 * Class that handles plugin info and plugin updates for add-ons plugins.
 *
 * This class is instantiated once for each add-on plugin.
 */
class Plugin_Updater {
	/**
	 * @var string
	 */
	public $plugin_id;

	/**
	 * @var string
	 */
	public $plugin_slug;

	/**
	 * @var string
	 */
	public $version;

	/**
	 * @var string
	 */
	public $api_url;

	/**
	 * @var string
	 */
	public $cache_key;

	/**
	 * @var string
	 */
	public $cache_key_plugin_info;

	/**
	 * @var boolean
	 *
	 * Default true.
	 *
	 * Only disable this for debugging.
	 */
	public $cache_allowed = true;

	/**
	 * @param string $plugin_id   The ID of the plugin.
	 * @param string $plugin_slug The slug of the plugin.
	 * @param string $version     The current version of the plugin.
	 * @param string $api_url     The API URL to the update server.
	 */
	public function __construct( $plugin_id, $plugin_slug, $version, $api_url ) {
		$this->plugin_id   = $plugin_id;
		$this->plugin_slug = $plugin_slug;
		$this->version     = $version;
		$this->api_url     = $api_url;

		$this->cache_key             = 'simple_history_updater_cache_' . str_replace( '-', '_', $this->plugin_slug );
		$this->cache_key_plugin_info = 'simple_history_updater_info_cache_' . str_replace( '-', '_', $this->plugin_slug );

		add_filter( 'plugins_api', array( $this, 'on_plugins_api_handle_plugin_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'site_transient_update_plugins_update' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
	}

	/**
	 * Get the license key. Normally, your plugin would have a settings page where
	 * you ask for and store a license key. Fetch it here.
	 *
	 * @return string
	 */
	protected function get_license_key() {
		$plus_plugin = new AddOn_Plugin(
			$this->plugin_id,
			$this->plugin_slug,
			$this->version,
		);

		return $plus_plugin->get_license_key();
	}

	/**
	 * Fetch the update info from the remote server running the Lemon Squeezy plugin.
	 *
	 * @return object|stdClass|bool
	 */
	public function request() {
		$lsq_license_key = $this->get_license_key();

		// If no licence key is set, user get no updates.
		if ( ! $lsq_license_key ) {
			return false;
		}

		$remote = get_transient( $this->cache_key );

		if ( false !== $remote && $this->cache_allowed ) {
			if ( 'error' === $remote ) {
				return false;
			}

			return json_decode( $remote );
		}

		// Get the update data from the remote server, i.e. our own server.
		$url = add_query_arg(
			[
				'license_key' => $lsq_license_key,
				'plugin_slug' => $this->plugin_slug,
			],
			$this->api_url . '/update',
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$remote = wp_remote_get(
			$url,
			[
				'timeout' => 3,
			]
		);

		if (
			is_wp_error( $remote )
			|| 200 !== wp_remote_retrieve_response_code( $remote )
			|| empty( wp_remote_retrieve_body( $remote ) )
		) {
			// Cache errors for 10 minutes.
			set_transient( $this->cache_key, 'error', MINUTE_IN_SECONDS * 10 );

			return false;
		}

		$payload = wp_remote_retrieve_body( $remote );

		// Cache response for 1 hour.
		set_transient( $this->cache_key, $payload, HOUR_IN_SECONDS );

		return json_decode( $payload );
	}

	/**
	 * Override the WordPress request to return the correct plugin info.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/plugins_api/
	 *
	 * @param false|object|array $result False if nothing is found, default WP_Error if request failed. An array of data on success.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args  Plugin API arguments.
	 * @return object|bool
	 */
	public function on_plugins_api_handle_plugin_info( $result, $action, $args ) {
		// Bail if this is not about getting plugin information.
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		// Bail if it is not our plugin.
		if ( $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		// Check cache/transient first.
		$remote_json = get_transient( $this->cache_key_plugin_info );
		if ( $remote_json !== false && $this->cache_allowed ) {
			return $remote_json;
		}

		// Here: Get plugin info from simple-history.com.
		// URLs for a plugin will be like:
		// https://simple-history.com/wp-json/simple-history/v1/plugins/simple-history-extended-settings.
		$api_url_base   = 'https://simple-history.com/wp-json/simple-history/v1/plugins/';
		$api_for_plugin = $api_url_base . $this->plugin_slug;

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$plugin_info_response = wp_remote_get( $api_for_plugin );

		// Bail if response was not ok.
		if ( is_wp_error( $plugin_info_response ) || wp_remote_retrieve_response_code( $plugin_info_response ) !== 200 || empty( wp_remote_retrieve_body( $plugin_info_response ) ) ) {
			return $result;
		}

		$remote_json = json_decode( wp_remote_retrieve_body( $plugin_info_response ), false );

		// Bail if json decode error.
		if ( $remote_json === null ) {
			return $result;
		}

		// Some things must be arrays, not objects.
		$remote_json->sections     = (array) $remote_json->sections;
		$remote_json->tags         = (array) $remote_json->tags;
		$remote_json->banners      = (array) $remote_json->banners;
		$remote_json->contributors = (array) $remote_json->contributors;

		// Make all contributors arrays, not objects.
		foreach ( $remote_json->contributors as $contributor_key => $contributor_value ) {
			$remote_json->contributors[ $contributor_key ] = (array) $contributor_value;
		}

		// Cache the result for 10 minutes.
		set_transient( $this->cache_key_plugin_info, $remote_json, MINUTE_IN_SECONDS * 10 );

		return $remote_json;
	}

	/**
	 * Override the WordPress request to check if an update is available.
	 *
	 * @see https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
	 *
	 * @param object $transient The pre-saved, cached data for plugins.
	 * @return object $transient
	 */
	public function site_transient_update_plugins_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$res = (object) array(
			'id'            => $this->plugin_id,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_id,
			'new_version'   => $this->version,
			'url'           => '',
			'package'       => '',
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => '',
			'requires_php'  => '',
			'compatibility' => new \stdClass(),
		);

		$remote = $this->request();

		if (
			// @phpstan-ignore-next-line
			$remote && $remote->success && ! empty( $remote->update )
			&& version_compare( $this->version, $remote->update->version, '<' )
		) {
			// Update is available for plugin.
			$res->new_version = $remote->update->version;
			$res->package     = $remote->update->download_link;

			$transient->response[ $res->plugin ] = $res;
		} else {
			// No update is available for plugin.
			// Adding the "mock" item to the `no_update` property is required
			// for the enable/disable auto-updates links to correctly appear in UI.
			$transient->no_update[ $res->plugin ] = $res;
		}

		return $transient;
	}

	/**
	 * When the update is complete, purge the cache.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/upgrader_process_complete/
	 *
	 * @param \WP_Upgrader $upgrader The WP_Upgrader instance.
	 * @param array        $options Array of bulk item update arguments.
	 * @return void
	 */
	public function purge( $upgrader, $options ) {
		if (
			$this->cache_allowed
			&& 'update' === $options['action']
			&& 'plugin' === $options['type']
			&& ! empty( $options['plugins'] )
		) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( $plugin === $this->plugin_id ) {
					delete_transient( $this->cache_key );
				}
			}
		}
	}
}
