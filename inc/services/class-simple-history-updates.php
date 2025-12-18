<?php

namespace Simple_History\Services;

/**
 * Service for handling Simple History update details.
 * Provides version-specific information about new features when Simple History is updated.
 */
class Simple_History_Updates extends Service {

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Hook into the plugin update details filter for Simple History.
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.14.0', [ $this, 'on_plugin_updated_details_5_14_0' ] );
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.15.0', [ $this, 'on_plugin_updated_details_5_15_0' ] );
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.18.0', [ $this, 'on_plugin_updated_details_5_18_0' ] );
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.19.0', [ $this, 'on_plugin_updated_details_5_19_0' ] );
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.21.0', [ $this, 'on_plugin_updated_details_5_21_0' ] );
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.22.0', [ $this, 'on_plugin_updated_details_5_22_0' ] );

		// To test the output of a specific version, you can enable it for any just recently updated plugin that is visible in the GUI.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// add_filter( 'simple_history/pluginlogger/plugin_updated_details/<pluginslug>', [ $this, 'on_plugin_updated_details_5_18_0' ] );
	}

	/**
	 * Format new features as an HTML list.
	 *
	 * @param string $custom_title Optional custom title for the section.
	 * @param array  $features Array of feature descriptions.
	 * @param string $release_link Optional link to release post.
	 * @return string Formatted HTML list.
	 */
	private function format_new_features_list( $custom_title = '', $features = [], $release_link = '' ) {
		// Bail if no features.
		if ( empty( $features ) ) {
			return '';
		}

		$output = '<div class="sh-PluginUpdateDetails">';

		// Use custom title if provided, otherwise use default.
		$title   = empty( $custom_title ) ? __( "What's new in this version", 'simple-history' ) : $custom_title;
		$output .= '<h4 class="sh-PluginUpdateDetails-title">' . esc_html( $title ) . '</h4>';

		$output .= '<ul class="sh-PluginUpdateDetails-features">';

		foreach ( $features as $feature ) {
			$output .= '<li class="sh-PluginUpdateDetails-feature">' . esc_html( $feature ) . '</li>';
		}

		$output .= '</ul>';

		// Add release link if provided.
		if ( ! empty( $release_link ) ) {
			$output .= '<p class="sh-PluginUpdateDetails-releaseLink sh-ExternalLink"><a href="' . esc_url( $release_link ) . '" target="_blank">' .
				__( 'Read full release notes', 'simple-history' ) . '</a></p>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Handle update details for Simple History version 5.14.0.
	 *
	 * @param string $extra_details Extra HTML to output after the changelog link.
	 * @return string Extra HTML to output after the changelog link.
	 */
	public function on_plugin_updated_details_5_14_0( $extra_details ) {
		$title = __( 'What\'s new in this version', 'simple-history' );

		$new_features = [
			'This release adds more filtering options, both in the frontend and in the REST API and WP-CLI. It also contains some bug fixes – and for the adventurous users we have two new experimental features',
		];

		$release_link = 'https://simple-history.com/2025/simple-history-5-14-0-released/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}

	/**
	 * Handle update details for Simple History version 5.15.0.
	 *
	 * @param string $extra_details Extra HTML to output after the changelog link.
	 * @return string Extra HTML to output after the changelog link.
	 */
	public function on_plugin_updated_details_5_15_0( $extra_details ) {
		$title = __( 'New features in this version', 'simple-history' );

		$new_features = [
			'Email reports are now available for all users. Go to the settings page to enable!',
			'New Core Files Integrity Logger that detects modifications to WordPress core files through daily checksum verification',
		];

		$release_link = 'https://simple-history.com/2025/simple-history-5-15-0-released/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}

	/**
	 * Handle update details for Simple History version 5.18.0.
	 *
	 * @param string $extra_details Extra HTML to output after the changelog link.
	 * @return string Extra HTML to output after the changelog link.
	 */
	public function on_plugin_updated_details_5_18_0( $extra_details ) {
		$title = __( 'Highlights in this version', 'simple-history' );

		$new_features = [
			'Context search added to log GUI filters for more precise filtering',
			'Interactive sidebar stats: click on avatars, user names, or chart dates to filter events instantly',
			'User names now shown in most active users list for better visibility',
		];

		$release_link = 'https://simple-history.com/2025/simple-history-5-18-0-released/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}

	/**
	 * Handle update details for Simple History version 5.19.0.
	 *
	 * @param string $extra_details Extra HTML to output after the changelog link.
	 * @return string Extra HTML to output after the changelog link.
	 */
	public function on_plugin_updated_details_5_19_0( $extra_details ) {
		$title = __( 'Highlights in this version', 'simple-history' );

		$new_features = [
			__( 'WordPress 6.9 Notes logging – track when notes are added, edited, or removed', 'simple-history' ),
			__( '"Hide my own events" checkbox – focus on what others are doing', 'simple-history' ),
			__( '"Yesterday" date filter – quick access to recent activity', 'simple-history' ),
			__( 'Premium: Unlimited backfill – import your complete site history, even years back', 'simple-history' ),
		];

		$release_link = 'https://simple-history.com/2025/simple-history-5-19-0-released/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}

	/**
	 * Handle update details for Simple History version 5.21.0.
	 *
	 * @param string $extra_details Extra HTML to output after the changelog link.
	 * @return string Extra HTML to output after the changelog link.
	 */
	public function on_plugin_updated_details_5_21_0( $extra_details ) {
		$title = __( 'Debug smarter, log everywhere', 'simple-history' );

		$new_features = [
			__( 'Surrounding Events – See exactly what happened before and after any event. Perfect for debugging!', 'simple-history' ),
			__( 'Log Forwarding (Beta) – Send events to log files, Syslog servers, or external databases for backup and compliance', 'simple-history' ),
			__( 'Auto-recovery – Database tables now self-heal if missing', 'simple-history' ),
		];

		$release_link = 'https://simple-history.com/2025/simple-history-5-21-0-released/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}

	/**
	 * Handle update details for Simple History version 5.22.0.
	 * Also includes 5.21.0 highlights since those features are significant.
	 *
	 * @param string $extra_details Extra HTML to output after the changelog link.
	 * @return string Extra HTML to output after the changelog link.
	 */
	public function on_plugin_updated_details_5_22_0( $extra_details ) {
		$title = __( 'Debug smarter, log everywhere', 'simple-history' );

		$new_features = [
			__( 'Surrounding Events – See exactly what happened before and after any event. Perfect for debugging!', 'simple-history' ),
			__( 'Log Forwarding (Beta) – Send events to log files, Syslog servers, or external databases for backup and compliance', 'simple-history' ),
			__( 'Auto-recovery – Database tables now self-heal if missing', 'simple-history' ),
		];

		$release_link = 'https://simple-history.com/2025/simple-history-5-22-0-released/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}
}
