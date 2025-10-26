<?php

namespace Simple_History\Services;

use Simple_History\Existing_Data_Importer;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Service;
use Simple_History\Simple_History;

/**
 * Service that adds an experimental features admin page.
 *
 * This page provides access to experimental features and tools
 * for testing and development purposes.
 */
class Experimental_Features_Page extends Service {
	/**
	 * Slug for the experimental features page.
	 */
	const PAGE_SLUG = 'simple_history_experimental_features';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Only add experimental features page if experimental features are enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'add_experimental_features_page' ], 100 );
		add_action( 'admin_post_simple_history_import_existing_data', [ $this, 'handle_import_request' ] );
	}

	/**
	 * Add experimental features admin page.
	 */
	public function add_experimental_features_page() {
		$admin_page_location = Helpers::get_menu_page_location();

		$experimental_page = ( new Menu_Page() )
			->set_page_title( __( 'Experimental Features - Simple History', 'simple-history' ) )
			->set_menu_slug( self::PAGE_SLUG )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'render_page' ] )
			->set_icon( 'science' )
			->set_order( 5 );

		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$experimental_page
				->set_menu_title( __( 'Experimental', 'simple-history' ) )
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} else {
			$experimental_page
				->set_menu_title( __( 'Simple History - Experimental', 'simple-history' ) )
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		}

		$experimental_page->add();
	}

	/**
	 * Render the experimental features page.
	 */
	public function render_page() {
		// Check if import was just completed.
		$import_completed = isset( $_GET['import_completed'] ) && $_GET['import_completed'] === '1';
		$posts_imported = isset( $_GET['posts_imported'] ) ? intval( $_GET['posts_imported'] ) : 0;
		$users_imported = isset( $_GET['users_imported'] ) ? intval( $_GET['users_imported'] ) : 0;
		$posts_skipped_imported = isset( $_GET['posts_skipped_imported'] ) ? intval( $_GET['posts_skipped_imported'] ) : 0;
		$posts_skipped_logged = isset( $_GET['posts_skipped_logged'] ) ? intval( $_GET['posts_skipped_logged'] ) : 0;
		$users_skipped_imported = isset( $_GET['users_skipped_imported'] ) ? intval( $_GET['users_skipped_imported'] ) : 0;
		$users_skipped_logged = isset( $_GET['users_skipped_logged'] ) ? intval( $_GET['users_skipped_logged'] ) : 0;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Experimental Features', 'simple-history' ); ?></h1>

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

			<div class="card">
				<h2><?php esc_html_e( 'About Experimental Features', 'simple-history' ); ?></h2>

				<p>
					<?php
					esc_html_e(
						'This page provides access to features that are still under development or testing. These features may change or be removed in future versions.',
						'simple-history'
					);
					?>
				</p>

				<p>
					<?php esc_html_e( 'Experimental features should be used with caution on production sites. Always test on a staging environment first.', 'simple-history' ); ?>
				</p>
			</div>

			<div class="card" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Import Existing Data', 'simple-history' ); ?></h2>

				<p>
					<?php
					esc_html_e(
						'Import historical data from your WordPress installation to populate the history log. This will add entries for existing posts, pages, and other content based on their creation and modification dates.',
						'simple-history'
					);
					?>
				</p>

				<p class="description">
					<?php esc_html_e( 'Note: You can run this import multiple times. Items that have already been imported will be automatically skipped to prevent duplicates.', 'simple-history' ); ?>
				</p>

				<details style="margin-bottom: 15px;">
					<summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;"><?php esc_html_e( 'Preview', 'simple-history' ); ?></summary>

					<?php
					// Get preview counts.
					$importer = new Existing_Data_Importer( $this->simple_history );
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
							<?php esc_html_e( 'Actual import counts may vary based on duplicate detection.', 'simple-history' ); ?>
						</p>
					</div>
				</details>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'simple_history_import_existing_data', 'simple_history_import_nonce' ); ?>
					<input type="hidden" name="action" value="simple_history_import_existing_data">

					<details style="margin-bottom: 15px;">
						<summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;"><?php esc_html_e( 'Import Options', 'simple-history' ); ?></summary>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="import_post_types"><?php esc_html_e( 'Post Types to Import', 'simple-history' ); ?></label>
								</th>
								<td>
									<?php
									$post_types = get_post_types( [ 'public' => true ], 'objects' );
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
										<?php esc_html_e( 'Select which public post types to import into the history.', 'simple-history' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="import_users"><?php esc_html_e( 'Import Users', 'simple-history' ); ?></label>
								</th>
								<td>
									<label>
										<input type="checkbox" name="import_users" id="import_users" value="1" checked>
										<?php esc_html_e( 'Import user registration dates', 'simple-history' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Add entries for existing user registrations.', 'simple-history' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="enable_import_limit"><?php esc_html_e( 'Limit Import', 'simple-history' ); ?></label>
								</th>
								<td>
									<label style="display: block; margin-bottom: 10px;">
										<input type="checkbox" name="enable_import_limit" id="enable_import_limit" value="1">
										<?php esc_html_e( 'Enable import limit', 'simple-history' ); ?>
									</label>
									<input type="number" name="import_limit" id="import_limit" value="100" min="1" max="10000" class="small-text" disabled>
									<p class="description">
										<?php esc_html_e( 'Maximum number of items to import per type. Leave unchecked to import all data.', 'simple-history' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</details>

					<?php submit_button( __( 'Import Data', 'simple-history' ), 'primary', 'submit', false ); ?>
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
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the import request.
	 */
	public function handle_import_request() {
		// Verify nonce.
		if ( ! isset( $_POST['simple_history_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_history_import_nonce'] ) ), 'simple_history_import_existing_data' ) ) {
			wp_die( esc_html__( 'Security check failed', 'simple-history' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'simple-history' ) );
		}

		// Get import options from form.
		$import_post_types = isset( $_POST['import_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['import_post_types'] ) ) : [];
		$import_users = isset( $_POST['import_users'] ) && sanitize_text_field( wp_unslash( $_POST['import_users'] ) ) === '1';

		// Check if import limit is enabled.
		$enable_limit = isset( $_POST['enable_import_limit'] ) && sanitize_text_field( wp_unslash( $_POST['enable_import_limit'] ) ) === '1';

		if ( $enable_limit ) {
			$import_limit = isset( $_POST['import_limit'] ) ? intval( $_POST['import_limit'] ) : 100;
			// Validate limit.
			$import_limit = max( 1, min( 10000, $import_limit ) );
		} else {
			// No limit - import all data.
			$import_limit = -1;
		}

		// Create importer instance.
		$importer = new Existing_Data_Importer( $this->simple_history );

		// Run import.
		$results = $importer->import_all(
			[
				'post_types' => $import_post_types,
				'import_users' => $import_users,
				'limit' => $import_limit,
			]
		);

		// Log detailed results for debugging.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Simple History Import] Import completed with results:' );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		error_log( print_r( $results, true ) );

		// Redirect back to the page with results.
		$redirect_url = add_query_arg(
			[
				'page' => self::PAGE_SLUG,
				'import_completed' => '1',
				'posts_imported' => $results['posts_imported'],
				'users_imported' => $results['users_imported'],
				'posts_skipped_imported' => $results['posts_skipped_imported'],
				'posts_skipped_logged' => $results['posts_skipped_logged'],
				'users_skipped_imported' => $results['users_skipped_imported'],
				'users_skipped_logged' => $results['users_skipped_logged'],
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
