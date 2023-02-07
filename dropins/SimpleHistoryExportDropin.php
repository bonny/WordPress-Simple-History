<?php

/**
 * Dropin Name: Export
 * Dropin Description: Adds a tab with export options
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */
class SimpleHistoryExportDropin {

	/**
	 * Simple History instance.
	 *
	 * @var $sh
	 */
	private $sh;

	/**
	 * Constructor.
	 *
	 * @param instance $sh Simple History instance.
	 */
	public function __construct( $sh ) {

		$this->sh = $sh;

		// Add tab to settings page.
		$sh->registerSettingsTab(
			array(
				'slug' => 'export',
				'name' => _x( 'Export', 'Export dropin: Tab name on settings page', 'simple-history' ),
				'function' => array( $this, 'output' ),
			)
		);

		add_action( 'init', array( $this, 'downloadExport' ) );
	}

	public function downloadExport() {

		global $wpdb;

		if ( isset( $_POST['simple-history-action'] ) && $_POST['simple-history-action'] === 'export-history' ) {
			// Will die if nonce not valid.
			check_admin_referer( __CLASS__ . '-action-export' );

			$export_format = isset( $_POST['format'] ) ? $_POST['format'] : 'json';

			// Disable relative time output in header.
			add_filter( 'simple_history/header_time_ago_max_time', '__return_zero' );
			add_filter( 'simple_history/header_just_now_max_time', '__return_zero' );

			// Don't use "You" if event is initiated by the same user that does the export
			add_filter( 'simple_history/header_initiator_use_you', '__return_false' );

			$query = new SimpleHistoryLogQuery();

			$query_args = array(
				'paged' => 1,
				'posts_per_page' => 3000,
			);

			$events = $query->query( $query_args );

			// $events->total_row_count;
			$pages_count = $events['pages_count'];
			$page_current = $events['page_current'];

			$fp = fopen( 'php://output', 'w' );

			$attachment_header_template = 'Content-Disposition: attachment; filename="%1$s"';

			if ( 'csv' == $export_format ) {
				$filename = 'simple-history-export-' . time() . '.csv';
				header( 'Content-Type: text/plain' );
				header( sprintf( $attachment_header_template, $filename ) );
			} elseif ( 'json' == $export_format ) {
				$filename = 'simple-history-export-' . time() . '.json';
				header( 'Content-Type: application/json' );
				header( sprintf( $attachment_header_template, $filename ) );
			} elseif ( 'html' == $export_format ) {
				$filename = 'simple-history-export-' . time() . '.html';
				header( 'Content-Type: text/html' );
				// header("Content-Disposition: attachment; filename='{$filename}'");
			}

			// Some formats need to output some stuff before the actual loops
			if ( 'json' == $export_format ) {
				$json_row = '[';
				fwrite( $fp, $json_row );
			} elseif ( 'html' == $export_format ) {
				$html = sprintf(
					'
				<!doctype html>
				<meta charset="utf-8">
				<title>Simple History export</title>
				<ul>
				'
				);
				fwrite( $fp, $html );
			}

			// Paginate through all pages and all their rows.
			$row_loop = 0;
			while ( $page_current <= $pages_count + 1 ) {
				// if ($page_current > 1) { break; } # To debug/test
				foreach ( $events['log_rows'] as $one_row ) {
					// if ( $row_loop > 10) { break; } # To debug/test
					set_time_limit( 30 );

					if ( 'csv' == $export_format ) {
						$header_output = strip_tags( html_entity_decode( $this->sh->getLogRowHeaderOutput( $one_row ), ENT_QUOTES, 'UTF-8' ) );
						$header_output = trim( preg_replace( '/\s\s+/', ' ', $header_output ) );

						$message_output = strip_tags( html_entity_decode( $this->sh->getLogRowPlainTextOutput( $one_row ), ENT_QUOTES, 'UTF-8' ) );

						$user_email = empty( $one_row->context['_user_email'] ) ? null : $one_row->context['_user_email'];
						$user_login = empty( $one_row->context['_user_login'] ) ? null : $one_row->context['_user_login'];

						$one_row->level = '=1+1';

						fputcsv(
							$fp,
							array(
								$this->esc_csv_field( $one_row->date ),
								$this->esc_csv_field( $one_row->logger ),
								$this->esc_csv_field( $one_row->level ),
								$this->esc_csv_field( $one_row->initiator ),
								$this->esc_csv_field( $one_row->context_message_key ),
								$this->esc_csv_field( $user_email ),
								$this->esc_csv_field( $user_login ),
								$this->esc_csv_field( $header_output ),
								$this->esc_csv_field( $message_output ),
								$this->esc_csv_field( $one_row->subsequentOccasions ),
							)
						);
					} elseif ( 'json' == $export_format ) {
						// If not first loop then add a comma between all json objects.
						if ( $row_loop == 0 ) {
							$comma = "\n";
						} else {
							$comma = ",\n";
						}

						$json_row = $comma . $this->sh->json_encode( $one_row );
						fwrite( $fp, $json_row );
					} elseif ( 'html' == $export_format ) {
						$html = sprintf(
							'
							<li>
								<div>%1$s</div>
								<div>%2$s</div>
								<div>%3$s</div>
							</li>
							',
							$this->sh->getLogRowHeaderOutput( $one_row ),
							$this->sh->getLogRowPlainTextOutput( $one_row ),
							$this->sh->getLogRowDetailsOutput( $one_row )
						);

						fwrite( $fp, $html );
					}// End if().

					$row_loop++;
				}// End foreach().

				// echo "<br>memory_get_usage:<br>"; print_r(memory_get_usage());
				// echo "<br>memory_get_peak_usage:<br>"; print_r(memory_get_peak_usage());
				// echo "<br>fetch next page";
				flush();

				// Fetch next page
				// @TODO: must take into consideration that new items can be added while we do the fetch
				$page_current++;
				$query_args['paged'] = $page_current;
				$events = $query->query( $query_args );

				// echo "<br>did fetch next page";
				// echo "<br>memory_get_usage:<br>"; print_r(memory_get_usage());
				// echo "<br>memory_get_peak_usage:<br>"; print_r(memory_get_peak_usage());
			}// End while().

			if ( 'json' == $export_format ) {
				$json_row = ']';
				fwrite( $fp, $json_row );
			} elseif ( 'html' == $export_format ) {
				$html = sprintf( '</ul>' );
				fwrite( $fp, $html );
			}

			fclose( $fp );
			flush();

			exit;

			// echo "<br>done";
		}// End if().
	}

	public function output() {
		?>
		<p><?php echo esc_html_x( 'The export function will export the full history.', 'Export dropin: introtext', 'simple-history' ); ?></p>

		<form method="post">

			<h3><?php echo esc_html_x( 'Choose format to export to', 'Export dropin: format', 'simple-history' ); ?></h3>

			<p>
				<label>
					<input type="radio" name="format" value="json" checked>
					<?php echo esc_html_x( 'JSON', 'Export dropin: export format', 'simple-history' ); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="radio" name="format" value="csv">
					<?php echo esc_html_x( 'CSV', 'Export dropin: export format', 'simple-history' ); ?>
				</label>
			</p>

			<p>
				<button type="submit" class="button button-primary">
					<?php echo esc_html_x( 'Download Export File', 'Export dropin: submit button', 'simple-history' ); ?>
				</button>
				<input type="hidden" name="simple-history-action" value="export-history">
			</p>

			<?php
			wp_nonce_field( __CLASS__ . '-action-export' );
			?>

		</form>

		<?php
	}

	/**
	 * Escape a string to be used in a CSV context, by adding an apostrophe if field
	 * begins with '=', '+', '-', or '@'.
	 *
	 * Function taken from the Jetpack Plugin, Copyright Automattic.
	 *
	 * @see https://www.drupal.org/project/webform/issues/3157877#:~:text=At%20present%2C%20the%20best%20defence%20strategy%20we%20are%20aware%20of%20is%20prefixing%20cells%20that%20start%20with%20%E2%80%98%3D%E2%80%99%20%2C%20%27%2B%27%20or%20%27%2D%27%20with%20an%20apostrophe.
	 * @see https://github.com/Automattic/jetpack/blob/d4068d52c35a30edc01b9356a4764132aeb532fd/projects/packages/forms/src/contact-form/class-contact-form-plugin.php#L1854
	 * @param string $field - the CSV field.
	 * @return string
	 */
	public function esc_csv_field( $field ) {
		$active_content_triggers = array( '=', '+', '-', '@' );

		if ( in_array( mb_substr( $field, 0, 1 ), $active_content_triggers, true ) ) {
			$field = "'" . $field;
		}

		return $field;
	}
}
