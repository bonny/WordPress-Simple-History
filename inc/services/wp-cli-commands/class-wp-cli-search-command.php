<?php

namespace Simple_History\Services;

		WP_CLI::warning(
			'The "event search" command is deprecated and will be removed in a future version. Use "wp simple-history event list --search=<term>" instead. Note: output columns have changed (ID and via are now included).'
		);

		// Translate this command's arguments to their list command equivalents
		// and delegate, so only one search implementation exists.
		$list_assoc_args = [
			'search' => $args[0],
		];

		if ( ! empty( $assoc_args['newer_than'] ) ) {
			$list_assoc_args['date_from'] = $assoc_args['newer_than'];
		}

		if ( ! empty( $assoc_args['older_than'] ) ) {
			$list_assoc_args['date_to'] = $assoc_args['older_than'];
		}

		if ( ! empty( $assoc_args['count'] ) ) {
			$list_assoc_args['count'] = $assoc_args['count'];
		}

		if ( ! empty( $assoc_args['format'] ) ) {
			$list_assoc_args['format'] = $assoc_args['format'];
		}

		if ( ! empty( $assoc_args['network'] ) ) {
			$list_assoc_args['network'] = true;
		}

		( new WP_CLI_List_Command() )->list( [], $list_assoc_args );
	}
}
