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
		add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/5.14.0', [ $this, 'on_plugin_updated_details_5_14_0' ], 10, 1 );
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
		$title = empty( $custom_title ) ? __( "What's new in this version", 'simple-history' ) : $custom_title;
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
			'Fix collapse of search filters not working',
			'Add footer to dashboard page',
			'Add search filter for initiators',
		];

		$release_link = 'https://simple-history.com/releases/5-14-0/';

		return $this->format_new_features_list( $title, $new_features, $release_link );
	}
}
