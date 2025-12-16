<?php
namespace Simple_History\Dropins;

use Simple_History\Existing_Data_Importer;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Admin_Pages;
use Simple_History\Services\Auto_Backfill_Service;
use Simple_History\Services\Import_Handler;

/**
 * Dropin Name: Backfill
 * Dropin Description: Adds a tab with backfill options under the Tools menu to generate history from existing WordPress data
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Import_Dropin extends Dropin {
	/** @var string Slug for the backfill menu tab. */
	const MENU_SLUG = 'simple_history_tools_backfill';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 31 );
	}

	/**
	 * Add backfill subtab under Tools > Tools tab.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		// Determine parent based on location.
		// When location is 'top' or 'bottom', use the Tools main tab object.
		// When inside dashboard/tools, use the Tools menu page slug directly.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$tools_parent = Tools_Menu_Dropin::get_tools_main_tab();
			if ( ! $tools_parent ) {
				$tools_parent = Tools_Menu_Dropin::TOOLS_TAB_SLUG;
			}
		} else {
			$tools_parent = Tools_Menu_Dropin::MENU_SLUG;
		}

		// Build menu title with New badge.
		$menu_title = _x( 'Backfill', 'backfill subtab name', 'simple-history' )
			. ' <span class="sh-Badge sh-Badge--new">' . esc_html__( 'New', 'simple-history' ) . '</span>';

		( new Menu_Page() )
			->set_page_title( _x( 'Backfill History', 'backfill subtab title', 'simple-history' ) )
			->set_menu_title( $menu_title )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'output_backfill_page' ] )
			->set_order( 3 )
			->set_parent( $tools_parent )
			->add();
	}

	/**
	 * Output for the backfill tab on the tools page.
	 *
	 * In the free version, this shows:
	 * - Auto-backfill status information
	 * - Premium upsell for manual backfill functionality
	 *
	 * Premium users get the full backfill GUI via the premium dropin.
	 */
	public function output_backfill_page() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();

		$auto_backfill_status = Auto_Backfill_Service::get_status();
		$retention_days       = Helpers::get_clear_history_interval();

		// Get total backfilled events count.
		$importer                = new Existing_Data_Importer( $this->simple_history );
		$backfilled_events_count = $importer->get_backfilled_events_count();

		// Check if delete was just completed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$delete_completed = isset( $_GET['delete-completed'] ) && $_GET['delete-completed'] === '1';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$events_deleted = isset( $_GET['events-deleted'] ) ? intval( $_GET['events-deleted'] ) : 0;

		// Check if re-run was just scheduled.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rerun_scheduled = isset( $_GET['rerun-scheduled'] ) && $_GET['rerun-scheduled'] === '1';
		?>

		<div class="wrap">
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'Backfill history', 'simple-history' ),
					'sync_arrow_down'
				),
				array(
					'span' => array(
						'class' => array(),
					),
				)
			);
			?>

			<?php
			if ( $delete_completed ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Delete completed!', 'simple-history' ); ?></strong><br>
						<?php
						printf(
							/* translators: %d: Number of events deleted */
							esc_html__( 'Deleted %d backfilled events.', 'simple-history' ),
							(int) $events_deleted
						);
						?>
					</p>
				</div>
				<?php
			}

			if ( $rerun_scheduled ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Auto-backfill scheduled!', 'simple-history' ); ?></strong><br>
						<?php esc_html_e( 'The automatic backfill has been scheduled and will run shortly.', 'simple-history' ); ?>
					</p>
				</div>
				<?php
			}
			?>

			<p>
				<?php
				esc_html_e(
					'Backfill generates history entries from existing WordPress data. This scans your posts, pages, and users to create log entries based on their creation and modification dates.',
					'simple-history'
				);
				?>
			</p>

			<p>
				<?php
				esc_html_e(
					'Note: Backfilled entries contain basic information and are less detailed than events logged in real-time, which capture the full context of each action.',
					'simple-history'
				);
				?>
			</p>

			<p>
				<strong>
				<?php
				printf(
					/* translators: %s: Number of backfilled events */
					esc_html__( 'Currently %s backfilled events in the history log.', 'simple-history' ),
					'<code>' . esc_html( number_format_i18n( $backfilled_events_count ) ) . '</code>'
				);
				?>
				</strong>
			</p>

			<div class="sh-SettingsCard">
				<h3 class="sh-SettingsCard-title"><?php esc_html_e( 'Automatic Backfill', 'simple-history' ); ?></h3>

				<p>
					<?php
					printf(
						/* translators: %d: Number of items limit */
						esc_html__( 'On fresh installations, Simple History automatically backfills history from existing content, limited to %d items per content type.', 'simple-history' ),
						(int) Auto_Backfill_Service::DEFAULT_LIMIT
					);
					?>
				</p>

				<?php
				$is_cron_scheduled     = wp_next_scheduled( Auto_Backfill_Service::CRON_HOOK );
				$has_auto_backfill_run = $auto_backfill_status && ! empty( $auto_backfill_status['completed'] );
				$is_existing_site      = ! $has_auto_backfill_run && ! $is_cron_scheduled;

				if ( $has_auto_backfill_run ) {
					// Date is stored in GMT, append UTC so strtotime interprets it correctly.
					$completed_timestamp = strtotime( $auto_backfill_status['completed_at'] . ' UTC' );
					$completed_date      = wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						$completed_timestamp
					);
					?>
				<div class="sh-StatusBox sh-StatusBox--success">
					<p>
						<strong>
						<?php
						printf(
							/* translators: %s: Date and time */
							esc_html__( 'Auto-backfill completed on %s', 'simple-history' ),
							esc_html( $completed_date )
						);
						?>
						</strong>
					</p>
					<ul>
						<li>
							<?php
							printf(
								/* translators: %d: Number of events */
								esc_html__( 'Post events created: %d', 'simple-history' ),
								(int) ( $auto_backfill_status['post_events_created'] ?? 0 )
							);
							?>
						</li>
						<li>
								<?php
								printf(
								/* translators: %d: Number of events */
									esc_html__( 'User events created: %d', 'simple-history' ),
									(int) ( $auto_backfill_status['user_events_created'] ?? 0 )
								);
								?>
						</li>
					</ul>

					<p>
							<?php esc_html_e( 'Need older content? Use Manual Backfill to import beyond these limits.', 'simple-history' ); ?>
					</p>
				</div>
					<?php
				} elseif ( $is_cron_scheduled ) {
					?>
				<div class="sh-StatusBox sh-StatusBox--warning">
					<p>
						<?php esc_html_e( 'Auto-backfill is scheduled and will run shortly.', 'simple-history' ); ?>
					</p>
				</div>
					<?php
				} elseif ( $is_existing_site ) {
					?>
				<div class="sh-StatusBox sh-StatusBox--info">
					<p>
						<?php esc_html_e( 'Auto-backfill did not run because Simple History was installed before this feature was added. Use Manual Backfill below to import existing content.', 'simple-history' ); ?>
					</p>
				</div>
					<?php
				}

				?>
			</div>

			<div class="sh-SettingsCard">
				<h3 class="sh-SettingsCard-title"><?php esc_html_e( 'Manual Backfill', 'simple-history' ); ?></h3>

				<p>
					<?php
					esc_html_e(
						'Manual backfill lets you generate history entries from all public post types (posts, pages, and custom post types), attachments, and users.',
						'simple-history'
					);
					?>
				</p>
				
				<p>
					<?php
					esc_html_e(
						'Unlike the automatic backfill, there are no limits on date range or number of items.',
						'simple-history'
					);
					?>
				</p>

				<?php
				/**
				 * Action hook for adding manual backfill controls.
				 *
				 * Premium add-on hooks here to add its backfill GUI.
				 *
				 * @param Import_Dropin $dropin The import dropin instance.
				 * @param int $retention_days The retention days setting.
				 */
				do_action( 'simple_history/backfill/manual_backfill_section', $this, $retention_days );

				/**
				 * Filter whether to show the premium teaser for manual backfill.
				 *
				 * Premium add-on returns false to hide this teaser since it provides the full GUI.
				 *
				 * @param bool $show_teaser Whether to show the premium teaser. Default true.
				 */
				$show_premium_teaser = apply_filters( 'simple_history/backfill/show_premium_teaser', true );

				if ( $show_premium_teaser ) {
					// Get approximate count of items that could be backfilled.
					$preview_importer  = new Existing_Data_Importer( $this->simple_history );
					$preview_counts    = $preview_importer->get_preview_counts();
					$total_items_count = array_sum( $preview_counts['post_types'] ) + $preview_counts['users'];

					echo wp_kses_post(
						Helpers::get_premium_feature_teaser(
							sprintf(
								/* translators: %s: Number of items */
								__( 'Import %s Missing Events', 'simple-history' ),
								number_format_i18n( $total_items_count )
							),
							[
								__( 'Import anytime, not just on first install', 'simple-history' ),
								__( 'Choose content types: posts, pages, users, attachments', 'simple-history' ),
								__( 'No item limits – recover your complete history', 'simple-history' ),
							],
							'premium_backfill_tools',
							__( 'Import Full History', 'simple-history' )
						)
					);
				}
				?>
			</div>

			<?php
			$this->output_backfill_page_dev_tools( $retention_days );
			?>

		</div>
		<?php
	}

	/**
	 * Output developer tools section for the backfill page.
	 *
	 * Only visible when dev mode is enabled. Provides tools for:
	 * - Deleting all backfilled data
	 * - Re-running the automatic backfill
	 *
	 * @param int $retention_days The retention days setting.
	 */
	public function output_backfill_page_dev_tools( $retention_days ) {
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		$importer                = new Existing_Data_Importer( $this->simple_history );
		$backfilled_events_count = $importer->get_backfilled_events_count();
		?>

		<div class="sh-DevToolsBox">
			<h3 class="sh-DevToolsBox-heading">
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'Developer Tools', 'simple-history' ); ?>
			</h3>
			<p class="description sh-DevToolsBox-description">
				<?php esc_html_e( 'These tools are only visible when dev mode is enabled. Use them to test and debug the backfill functionality.', 'simple-history' ); ?>
			</p>

			<h4 class="sh-DevToolsBox-section">
				<?php esc_html_e( 'Delete backfilled data', 'simple-history' ); ?>
			</h4>

			<p>
				<?php
				esc_html_e(
					'Remove all backfilled entries from the history log. This is useful for testing or if you want to re-run the backfill with different settings.',
					'simple-history'
				);
				?>
			</p>

			<p>
				<?php
				printf(
					/* translators: %s: number of backfilled events */
					esc_html__( 'Currently there are %s backfilled events in the log.', 'simple-history' ),
					'<strong>' . esc_html( number_format_i18n( $backfilled_events_count ) ) . '</strong>'
				);
				?>
			</p>

			<p class="description">
				<?php
				printf(
					/* translators: %s: context key name */
					esc_html__( 'Backfilled events are identified by the %s context key that is added when events are created during backfill.', 'simple-history' ),
					'<code>' . esc_html( Existing_Data_Importer::BACKFILLED_CONTEXT_KEY ) . '</code>'
				);
				?>
			</p>

			<?php
			if ( $backfilled_events_count > 0 ) {
				?>
				<p class="description sh-DevToolsBox-warning">
					<strong><?php esc_html_e( 'Warning:', 'simple-history' ); ?></strong>
					<?php esc_html_e( 'This action cannot be undone. Only backfilled entries will be deleted. Naturally logged events will not be affected.', 'simple-history' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete all backfilled entries? This action cannot be undone.', 'simple-history' ) ); ?>');">
					<?php wp_nonce_field( Import_Handler::DELETE_ACTION_NAME, 'simple_history_delete_nonce' ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( Import_Handler::DELETE_ACTION_NAME ); ?>">

					<?php submit_button( __( 'Delete Backfilled Data', 'simple-history' ), 'delete', 'submit', false ); ?>
				</form>
				<?php
			}
			?>

			<h4 class="sh-DevToolsBox-section sh-DevToolsBox-section--bordered">
				<?php esc_html_e( 'Re-run automatic backfill', 'simple-history' ); ?>
			</h4>

			<p>
				<?php
				esc_html_e(
					'Reset the auto-backfill status and schedule it to run again. This is useful for testing or after deleting backfilled data.',
					'simple-history'
				);
				?>
			</p>

			<?php
			// Get preview of what would be imported.
			// Use the same post types as Auto_Backfill_Service.
			$preview_post_types   = get_post_types( [ 'public' => true ], 'names' );
			$preview_post_types[] = 'attachment';

			/**
			 * Filter the post types to preview for auto-backfill.
			 *
			 * @param array $preview_post_types Post types to preview.
			 */
			$preview_post_types = apply_filters( 'simple_history/auto_backfill/post_types', $preview_post_types );

			$preview = $importer->get_auto_backfill_preview(
				[
					'post_types'    => $preview_post_types,
					'include_users' => true,
					'limit'         => Auto_Backfill_Service::DEFAULT_LIMIT,
					'days_back'     => $retention_days,
				]
			);
			?>

			<details class="sh-PreviewDetails">
				<summary>
					<?php
					printf(
						/* translators: %d: Number of events that would be created */
						esc_html__( 'Preview: %d events would be created', 'simple-history' ),
						(int) $preview['total']
					);
					?>
				</summary>
				<p class="description sh-PreviewDetails-description">
					<?php
					printf(
						/* translators: 1: Number of days, 2: Limit per type */
						esc_html__( 'Date range: last %1$d days. Limit: %2$d per type.', 'simple-history' ),
						(int) $preview['days_back'],
						(int) $preview['limit_per_type']
					);
					?>
				</p>
				<table class="widefat striped sh-tableAuto">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'simple-history' ); ?></th>
							<th class="sh-textRight"><?php esc_html_e( 'Available', 'simple-history' ); ?></th>
							<th class="sh-textRight"><?php esc_html_e( 'Already logged', 'simple-history' ); ?></th>
							<th class="sh-textRight"><?php esc_html_e( 'Events to create', 'simple-history' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $preview['post_types'] as $type_name => $type_data ) {
							?>
							<tr>
								<td><?php echo esc_html( $type_data['label'] ); ?></td>
								<td class="sh-textRight"><?php echo esc_html( number_format_i18n( $type_data['available'] ) ); ?></td>
								<td class="sh-textRight"><?php echo esc_html( number_format_i18n( $type_data['already_logged'] ) ); ?></td>
								<td class="sh-textRight"><strong><?php echo esc_html( number_format_i18n( $type_data['would_import'] ) ); ?></strong></td>
							</tr>
							<?php
						}

						if ( is_array( $preview['users'] ) ) {
							?>
							<tr>
								<td><?php esc_html_e( 'Users', 'simple-history' ); ?></td>
								<td class="sh-textRight"><?php echo esc_html( number_format_i18n( $preview['users']['available'] ) ); ?></td>
								<td class="sh-textRight"><?php echo esc_html( number_format_i18n( $preview['users']['already_logged'] ) ); ?></td>
								<td class="sh-textRight"><strong><?php echo esc_html( number_format_i18n( $preview['users']['would_import'] ) ); ?></strong></td>
							</tr>
							<?php
						}
						?>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Total', 'simple-history' ); ?></th>
							<th colspan="2"></th>
							<th class="sh-textRight"><strong><?php echo esc_html( number_format_i18n( $preview['total'] ) ); ?></strong></th>
						</tr>
					</tfoot>
				</table>
			</details>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( Import_Handler::RERUN_ACTION_NAME, 'simple_history_rerun_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Import_Handler::RERUN_ACTION_NAME ); ?>">

				<?php submit_button( __( 'Re-run Auto Backfill', 'simple-history' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
