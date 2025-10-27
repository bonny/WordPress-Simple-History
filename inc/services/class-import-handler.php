<?php

namespace Simple_History\Services;

use Simple_History\Existing_Data_Importer;
use Simple_History\Services\Service;

/**
 * Handles import form submissions via WordPress admin-post hook.
 *
 * This class is responsible for processing import requests submitted
 * from the Experimental Features page. It follows WordPress best practices
 * by using the admin-post.php routing system to separate business logic
 * from UI rendering.
 */
class Import_Handler extends Service {
	/**
	 * Action name for the admin post hook.
	 */
	const ACTION_NAME = 'simple_history_import_existing_data';

	/**
	 * Register the service.
	 */
	public function loaded() {
		// Hook into admin-post to handle import form submissions.
		add_action( 'admin_post_' . self::ACTION_NAME, [ $this, 'handle' ] );

		// Hook into experimental features page to render the import UI.
		add_action( 'simple_history/experimental_features/render', [ $this, 'render_feature' ], 10 );
	}

	/**
	 * Handle the import request.
	 *
	 * Validates the request, processes the import, and redirects back
	 * to the experimental features page with results as URL parameters.
	 */
	public function handle() {
		// Verify nonce.
		if ( ! isset( $_POST['simple_history_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_history_import_nonce'] ) ), self::ACTION_NAME ) ) {
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

		// Redirect back to the page with results as URL parameters.
		$redirect_url = add_query_arg(
			[
				'page' => Experimental_Features_Page::PAGE_SLUG,
				'import-completed' => '1',
				'posts-imported' => isset( $results['posts_imported'] ) ? intval( $results['posts_imported'] ) : 0,
				'users-imported' => isset( $results['users_imported'] ) ? intval( $results['users_imported'] ) : 0,
				'posts-skipped-imported' => isset( $results['posts_skipped_imported'] ) ? intval( $results['posts_skipped_imported'] ) : 0,
				'posts-skipped-logged' => isset( $results['posts_skipped_logged'] ) ? intval( $results['posts_skipped_logged'] ) : 0,
				'users-skipped-imported' => isset( $results['users_skipped_imported'] ) ? intval( $results['users_skipped_imported'] ) : 0,
				'users-skipped-logged' => isset( $results['users_skipped_logged'] ) ? intval( $results['users_skipped_logged'] ) : 0,
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the data import feature UI.
	 *
	 * Hooked into simple_history/experimental_features/render.
	 */
	public function render_feature() {
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

		?>
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
				<?php wp_nonce_field( self::ACTION_NAME, 'simple_history_import_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_NAME ); ?>">

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
		<?php
	}
}
