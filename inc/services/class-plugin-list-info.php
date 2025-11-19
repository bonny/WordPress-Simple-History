<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Services\AddOns_Licences;
use Simple_History\Services\Service;

/**
 * Adds a message to the plugin list, reminding the user to add a licence key.
 */
class Plugin_List_Info extends Service {
	/**
	 * @inheritDoc
	 */
	public function loaded() {
		add_action( 'plugin_row_meta', [ $this, 'on_action_plugin_row_meta' ], 10, 2 );
	}

	/**
	 * TODO: Should this be added to Simple History itself, so it can be used by all add-ons?
	 * Add message and link about filling in licence key to plugin row meta.
	 *
	 * Called from filter 'plugin_row_meta'.
	 *
	 * @param array  $links Array of plugin action links.
	 * @param string $file  Plugin base name.
	 */
	public function on_action_plugin_row_meta( $links, $file ) {
		/** @var AddOns_Licences */
		$licences_service = $this->simple_history->get_service( AddOns_Licences::class );
		$addon_plugins    = $licences_service->get_addon_plugins();

		foreach ( $addon_plugins as $addon_plugin ) {
			if ( $file !== $addon_plugin->id ) {
				continue;
			}

			if ( empty( $addon_plugin->get_license_key() ) ) {
				$licences_page_url = Helpers::get_settings_page_sub_tab_url( 'general_settings_subtab_licenses' );

				$links[] = sprintf(
					'<a href="%2$s">%1$s</a>',
					__( 'Add licence key to enable updates', 'simple-history' ),
					esc_url( $licences_page_url )
				);
			}

			break;
		}

		return $links;
	}
}
