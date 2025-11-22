<?php
namespace Simple_History\Dropins;

use Simple_History\Existing_Data_Importer;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Admin_Pages;
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

		( new Menu_Page() )
			->set_page_title( _x( 'Backfill History', 'backfill subtab title', 'simple-history' ) )
			->set_menu_title( _x( 'Backfill', 'backfill subtab name', 'simple-history' ) )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'output_backfill_page' ] )
			->set_order( 3 )
			->set_parent( $tools_parent )
			->add();
	}

	/**
	 * Output for the backfill tab on the tools page.
	 */
	public function output_backfill_page() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();

		// Check if import was just completed and read results from URL parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$import_completed = isset( $_GET['import-completed'] ) && $_GET['import-completed'] === '1';

		// Read import results from URL parameters (already validated as integers in redirect).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$posts_imported = isset( $_GET['posts-imported'] ) ? intval( $_GET['posts-imported'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$users_imported = isset( $_GET['users-imported'] ) ? intval( $_GET['users-imported'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$posts_skipped_imported = isset( $_GET['posts-skipped-imported'] ) ? intval( $_GET['posts-skipped-imported'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$posts_skipped_logged = isset( $_GET['posts-skipped-logged'] ) ? intval( $_GET['posts-skipped-logged'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$users_skipped_imported = isset( $_GET['users-skipped-imported'] ) ? intval( $_GET['users-skipped-imported'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$users_skipped_logged = isset( $_GET['users-skipped-logged'] ) ? intval( $_GET['users-skipped-logged'] ) : 0;

		// Check if delete was just completed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$delete_completed = isset( $_GET['delete-completed'] ) && $_GET['delete-completed'] === '1';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$events_deleted = isset( $_GET['events-deleted'] ) ? intval( $_GET['events-deleted'] ) : 0;
		?>

		<div class="wrap">
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'Backfill history', 'simple-history' ),
					'history'
				),
				array(
					'span' => array(
						'class' => array(),
					),
				)
			);
			?>

			<?php if ( $delete_completed ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Delete completed!', 'simple-history' ); ?></strong><br>
						<?php
						printf(
							/* translators: %d: Number of events deleted */
							esc_html__( 'Deleted %d imported events.', 'simple-history' ),
							(int) $events_deleted
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $import_completed ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Import completed!', 'simple-history' ); ?></strong><br>
						<?php
						$has_skips = $posts_skipped_imported > 0 || $posts_skipped_logged > 0 || $users_skipped_imported > 0 || $users_skipped_logged > 0;

						// Build message with imported counts.
						$message = sprintf(
							/* translators: 1: Number of posts imported, 2: Number of users imported */
							esc_html__( 'Imported %1$d posts and %2$d users', 'simple-history' ),
							(int) $posts_imported,
							(int) $users_imported
						);

						// Add skip details if any.
						if ( $has_skips ) {
							$skip_parts = [];

							if ( $posts_skipped_imported > 0 ) {
								$skip_parts[] = sprintf(
									/* translators: %d: Number of posts */
									esc_html__( '%d posts already imported', 'simple-history' ),
									(int) $posts_skipped_imported
								);
							}

							if ( $posts_skipped_logged > 0 ) {
								$skip_parts[] = sprintf(
									/* translators: %d: Number of posts */
									esc_html__( '%d posts already in history', 'simple-history' ),
									(int) $posts_skipped_logged
								);
							}

							if ( $users_skipped_imported > 0 ) {
								$skip_parts[] = sprintf(
									/* translators: %d: Number of users */
									esc_html__( '%d users already imported', 'simple-history' ),
									(int) $users_skipped_imported
								);
							}

							if ( $users_skipped_logged > 0 ) {
								$skip_parts[] = sprintf(
									/* translators: %d: Number of users */
									esc_html__( '%d users already in history', 'simple-history' ),
									(int) $users_skipped_logged
								);
							}

							$message .= ' (' . esc_html__( 'skipped: ', 'simple-history' ) . implode( ', ', $skip_parts ) . ')';
						}

						$message .= '.';
						echo esc_html( $message );
						?>
					</p>
				</div>
			<?php endif; ?>

			<p>
				<?php
				esc_html_e(
					'Generate history entries from existing WordPress data. This scans your posts, pages, and users to create log entries based on their creation and modification dates.',
					'simple-history'
				);
				?>
			</p>

			<p class="description">
				<?php esc_html_e( 'Note: You can run backfill multiple times. Items that have already been processed will be automatically skipped to prevent duplicates.', 'simple-history' ); ?>
			</p>

			<details style="margin-bottom: 15px;">
				<summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;"><?php esc_html_e( 'Preview', 'simple-history' ); ?></summary>

				<?php
				// Get preview counts.
				$importer       = new Existing_Data_Importer( $this->simple_history );
				$preview_counts = $importer->get_preview_counts();
				?>

				<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0;">
					<ul style="margin: 0;">
						<?php
						foreach ( $preview_counts['post_types'] as $post_type => $count ) {
							?>
							<li>
								<?php
								$post_type_object = get_post_type_object( $post_type );
								printf(
									/* translators: 1: Post type name, 2: Count */
									esc_html__( '%1$s: ~%2$s items', 'simple-history' ),
									esc_html( $post_type_object->labels->name ),
									esc_html( number_format_i18n( $count ) )
								);
								?>
							</li>
							<?php
						}
						?>
						<li>
							<?php
							printf(
								/* translators: %s: Count */
								esc_html__( 'Users: ~%s registrations', 'simple-history' ),
								esc_html( number_format_i18n( $preview_counts['users'] ) )
							);
							?>
						</li>
					</ul>
					<p class="description" style="margin: 10px 0 0 0;">
						<?php esc_html_e( 'Actual counts may vary based on duplicate detection.', 'simple-history' ); ?>
					</p>
				</div>
			</details>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( Import_Handler::ACTION_NAME, 'simple_history_import_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Import_Handler::ACTION_NAME ); ?>">

				<details style="margin-bottom: 15px;">
					<summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;"><?php esc_html_e( 'Options', 'simple-history' ); ?></summary>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="import_post_types"><?php esc_html_e( 'Post Types', 'simple-history' ); ?></label>
							</th>
							<td>
								<?php
								$post_types = get_post_types( [ 'public' => true ], 'objects' );

								// Add attachment post type (not public by default, but has historical data we can backfill).
								$attachment_post_type = get_post_type_object( 'attachment' );
								if ( $attachment_post_type ) {
									$post_types['attachment'] = $attachment_post_type;
								}

								foreach ( $post_types as $post_type ) {
									?>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="import_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" checked>
										<?php echo esc_html( $post_type->labels->name ); ?>
									</label>
									<?php
								}
								?>
								<p class="description">
									<?php esc_html_e( 'Select which post types to include in the backfill.', 'simple-history' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="import_users"><?php esc_html_e( 'Users', 'simple-history' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="import_users" id="import_users" value="1" checked>
									<?php esc_html_e( 'Include user registration dates', 'simple-history' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Create entries for existing user registrations.', 'simple-history' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="enable_import_limit"><?php esc_html_e( 'Limit', 'simple-history' ); ?></label>
							</th>
							<td>
								<label style="display: block; margin-bottom: 10px;">
									<input type="checkbox" name="enable_import_limit" id="enable_import_limit" value="1">
									<?php esc_html_e( 'Limit number of entries', 'simple-history' ); ?>
								</label>
								<input type="number" name="import_limit" id="import_limit" value="100" min="1" max="10000" class="small-text" disabled>
								<p class="description">
									<?php esc_html_e( 'Maximum number of items to process per type. Leave unchecked to process all data.', 'simple-history' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</details>

				<?php submit_button( __( 'Run Backfill', 'simple-history' ), 'primary', 'submit', false ); ?>
			</form>

			<script>
				(function() {
					const enableLimitCheckbox = document.getElementById('enable_import_limit');
					const limitInput = document.getElementById('import_limit');

					if (enableLimitCheckbox && limitInput) {
						enableLimitCheckbox.addEventListener('change', function() {
							limitInput.disabled = !this.checked;
						});
					}
				})();
			</script>

			<h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dcdcde;"><?php esc_html_e( 'Delete backfilled data', 'simple-history' ); ?></h3>

			<p>
				<?php
				esc_html_e(
					'Remove all backfilled entries from the history log. This is useful for testing or if you want to re-run the backfill with different settings.',
					'simple-history'
				);
				?>
			</p>

			<p class="description" style="color: #d63638;">
				<strong><?php esc_html_e( 'Warning:', 'simple-history' ); ?></strong>
				<?php esc_html_e( 'This action cannot be undone. Only backfilled entries will be deleted. Naturally logged events will not be affected.', 'simple-history' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete all backfilled entries? This action cannot be undone.', 'simple-history' ) ); ?>');">
				<?php wp_nonce_field( Import_Handler::DELETE_ACTION_NAME, 'simple_history_delete_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Import_Handler::DELETE_ACTION_NAME ); ?>">

				<?php submit_button( __( 'Delete Backfilled Data', 'simple-history' ), 'delete', 'submit', false ); ?>
			</form>

		</div>
		<?php
	}
}
