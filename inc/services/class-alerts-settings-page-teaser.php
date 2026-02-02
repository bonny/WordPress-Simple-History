<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;

/**
 * Settings page teaser for Alerts feature.
 *
 * Shows a preview of the premium Alerts settings when the premium add-on is not active.
 * The preview mirrors the actual premium UI with two tabs (Destinations, Alert Rules)
 * but all content is non-interactive (using the inert attribute).
 *
 * When premium is active, this teaser is replaced by the real Alerts settings page.
 *
 * @since 5.0.0
 */
class Alerts_Settings_Page_Teaser extends Service {
	/**
	 * Menu slug for the alerts settings page.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'general_settings_subtab_alerts';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Add menu page - the check for premium happens in the callback
		// because premium module may not have loaded yet at this point.
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
	}

	/**
	 * Add alerts settings tab as a subtab to main settings tab.
	 */
	public function add_settings_menu_tab() {
		// Skip if premium is active (premium has its own alerts settings page).
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exist (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		// Build menu title with New badge to encourage exploration.
		$menu_title = __( 'Alerts', 'simple-history' )
			. ' <span class="sh-Badge sh-Badge--new">' . esc_html__( 'New', 'simple-history' ) . '</span>';

		( new Menu_Page() )
			->set_page_title( __( 'Alerts', 'simple-history' ) )
			->set_menu_title( $menu_title )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'render_settings_page' ] )
			->set_order( 35 ) // After general (default) but before log forwarding (40).
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Render the alerts settings page teaser.
	 *
	 * Shows a preview of the premium alerts settings with functional tabs
	 * but non-interactive content (using the inert attribute).
	 */
	public function render_settings_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$current_tab = isset( $_GET['alerts_tab'] ) ? sanitize_key( $_GET['alerts_tab'] ) : 'destinations';

		?>
		<div class="wrap sh-Page-content sh-AlertsTeaser-wrap">
			<?php $this->render_tabs( $current_tab ); ?>
			<?php $this->render_section_intro( $current_tab ); ?>
			<?php $this->render_preview_banner(); ?>

			<div class="sh-AlertsTeaser" inert aria-label="<?php esc_attr_e( 'Premium feature preview - not interactive', 'simple-history' ); ?>">
				<?php
				if ( $current_tab === 'destinations' ) {
					$this->render_destinations_preview();
				} elseif ( $current_tab === 'presets' ) {
					$this->render_presets_preview();
				} elseif ( $current_tab === 'custom-rules' ) {
					$this->render_custom_rules_preview();
				} else {
					// Default to destinations for unknown tabs.
					$this->render_destinations_preview();
				}
				?>
			</div>

			<?php $this->render_upgrade_cta(); ?>
		</div>
		<?php
	}

	/**
	 * Render the section intro card.
	 *
	 * Shows outside the inert wrapper so it remains fully visible.
	 * Uses the same structure as Helpers::add_settings_section() for consistency.
	 *
	 * @param string $current_tab The currently active tab.
	 */
	private function render_section_intro( string $current_tab ) {
		if ( $current_tab === 'destinations' ) {
			$icon        = 'schedule_send';
			$title       = __( 'Destinations', 'simple-history' );
			$description = __( 'Configure where your alerts will be sent. Add multiple destinations and reuse them across different alert rules.', 'simple-history' );
		} elseif ( $current_tab === 'presets' ) {
			$icon        = 'bolt';
			$title       = __( 'Presets', 'simple-history' );
			$description = __( 'Get started quickly with pre-configured alert rules for common scenarios. Select destinations to enable each preset.', 'simple-history' );
		} else {
			$icon        = 'filter_list';
			$title       = __( 'Custom Rules', 'simple-history' );
			$description = __( 'Create custom alert rules with specific conditions for advanced filtering.', 'simple-history' );
		}
		?>
		<div class="sh-SettingsPage-settingsSection-wrap">
			<h2>
				<span class="sh-SettingsPage-settingsSection-title">
					<span class="sh-SettingsPage-settingsSection-icon sh-Icon--<?php echo esc_attr( $icon ); ?>"></span>
					<?php echo esc_html( $title ); ?>
				</span>
			</h2>
			<div class="sh-SettingsSectionIntroduction">
				<p><?php echo esc_html( $description ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the preview mode banner.
	 *
	 * Uses benefit-focused copy to encourage upgrades while indicating this is a preview.
	 */
	private function render_preview_banner() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'alerts_preview_banner' );
		?>
		<div class="sh-AlertsTeaser-banner">
			<span class="sh-AlertsTeaser-banner-icon dashicons dashicons-bell" aria-hidden="true"></span>
			<div class="sh-AlertsTeaser-banner-content">
				<span class="sh-AlertsTeaser-banner-title">
					<?php esc_html_e( 'Get notified when it matters', 'simple-history' ); ?>
					<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				</span>
				<span><?php esc_html_e( 'Receive instant alerts via Email, Slack, Discord, or Telegram when critical events happen on your site.', 'simple-history' ); ?></span>
				<a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Upgrade to Premium', 'simple-history' ); ?> →</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the tab navigation.
	 *
	 * @param string $current_tab The currently active tab.
	 */
	private function render_tabs( string $current_tab ) {
		$base_url = Helpers::get_settings_page_sub_tab_url( self::MENU_SLUG );
		?>
		<nav class="nav-tab-wrapper sh-AlertsTabs sh-AlertsTabs--teaser">
			<a href="<?php echo esc_url( add_query_arg( 'alerts_tab', 'destinations', $base_url ) ); ?>"
				class="nav-tab <?php echo 'destinations' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Destinations', 'simple-history' ); ?>
				<span class="sh-AlertsTabs-count">(2)</span>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'alerts_tab', 'presets', $base_url ) ); ?>"
				class="nav-tab <?php echo 'presets' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Presets', 'simple-history' ); ?>
				<span class="sh-AlertsTabs-count">(1)</span>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'alerts_tab', 'custom-rules', $base_url ) ); ?>"
				class="nav-tab <?php echo 'custom-rules' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Custom Rules', 'simple-history' ); ?>
				<span class="sh-AlertsTabs-count">(3)</span>
			</a>
			<span class="sh-Badge sh-Badge--premium sh-AlertsTabs-badge">
				<?php esc_html_e( 'Premium', 'simple-history' ); ?>
			</span>
		</nav>
		<?php
	}

	/**
	 * Render the destinations tab preview.
	 *
	 * Shows all 4 destination types with sample data for Email and Slack,
	 * and empty states for Discord and Telegram.
	 */
	private function render_destinations_preview() {
		$destination_types   = $this->get_destination_type_definitions();
		$sample_destinations = $this->get_sample_destinations();
		?>
		<div class="sh-Destinations">
			<?php foreach ( $destination_types as $type => $type_info ) { ?>
				<div class="sh-SettingsCard sh-DestinationType">
					<div class="sh-CardHeader">
						<span class="dashicons dashicons-<?php echo esc_attr( $type_info['icon'] ); ?>"></span>
						<div class="sh-CardHeader-content">
							<strong><?php echo esc_html( $type_info['label'] ); ?></strong>
							<span class="sh-CardHeader-description"><?php echo esc_html( $type_info['description'] ); ?></span>
						</div>
					</div>

					<div class="sh-DestinationType-list">
						<?php if ( isset( $sample_destinations[ $type ] ) && ! empty( $sample_destinations[ $type ] ) ) { ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'simple-history' ); ?></th>
										<th scope="col" class="manage-column column-details"><?php echo esc_html( $type_info['details_label'] ); ?></th>
										<th scope="col" class="manage-column column-usedby"><?php esc_html_e( 'Used by', 'simple-history' ); ?></th>
										<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'simple-history' ); ?></th>
									</tr>
								</thead>
								<tbody id="the-list">
									<?php foreach ( $sample_destinations[ $type ] as $sample ) { ?>
										<tr>
											<td class="name column-name has-row-actions column-primary" data-colname="<?php esc_attr_e( 'Name', 'simple-history' ); ?>">
												<strong><a class="row-title" href="#"><?php echo esc_html( $sample['name'] ); ?></a></strong>
												<div class="row-actions">
													<span class="test">
														<a href="#"><?php esc_html_e( 'Test', 'simple-history' ); ?></a> |
													</span>
													<span class="edit">
														<a href="#"><?php esc_html_e( 'Edit', 'simple-history' ); ?></a> |
													</span>
													<span class="delete">
														<a href="#" class="submitdelete"><?php esc_html_e( 'Delete', 'simple-history' ); ?></a>
													</span>
												</div>
												<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'simple-history' ); ?></span></button>
											</td>
											<td class="details column-details" data-colname="<?php echo esc_attr( $type_info['details_label'] ); ?>"><?php echo esc_html( $sample['details'] ); ?></td>
											<td class="usedby column-usedby" data-colname="<?php esc_attr_e( 'Used by', 'simple-history' ); ?>">
												<?php
												printf(
													/* translators: %d: number of alert rules */
													esc_html( _n( '%d rule', '%d rules', $sample['rules_count'], 'simple-history' ) ),
													absint( $sample['rules_count'] )
												);
												?>
											</td>
											<td class="status column-status" data-colname="<?php esc_attr_e( 'Status', 'simple-history' ); ?>">
												<span class="sh-DestinationStatus sh-DestinationStatus--ok">
													<span class="sh-StatusIcon sh-StatusIcon--ok">✓</span>
													<?php echo esc_html( $sample['status'] ); ?>
												</span>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						<?php } else { ?>
							<p class="sh-DestinationType-empty">
								<?php esc_html_e( 'No destinations configured.', 'simple-history' ); ?>
							</p>
						<?php } ?>
					</div>

					<div class="sh-DestinationType-actions">
						<button type="button" class="button button-secondary sh-Button--withIcon" disabled>
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php
							printf(
								/* translators: %s: destination type name */
								esc_html__( 'Add %s', 'simple-history' ),
								esc_html( $type_info['label'] )
							);
							?>
						</button>
					</div>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Render the presets tab preview.
	 *
	 * Shows preset cards with sample destination selections.
	 */
	private function render_presets_preview() {
		$presets             = $this->get_preset_definitions();
		$event_labels        = $this->get_event_labels();
		$destination_types   = $this->get_destination_type_definitions();
		$sample_destinations = $this->get_sample_destinations();
		?>
		<div class="sh-AlertPresets">
			<div class="sh-PresetCards">
				<?php foreach ( $presets as $preset_id => $preset ) { ?>
					<?php
					$preset_events = $preset['events'] ?? [];
					$event_count   = count( $preset_events );
					// Only show destinations selected for Security preset.
					$is_security = $preset_id === 'security';
					?>
					<div class="sh-SettingsCard sh-PresetCard">
						<div class="sh-CardHeader">
							<span class="dashicons dashicons-<?php echo esc_attr( $preset['icon'] ); ?>"></span>
							<div class="sh-CardHeader-content">
								<strong><?php echo esc_html( $preset['name'] ); ?></strong>
								<span class="sh-CardHeader-description"><?php echo esc_html( $preset['description'] ); ?></span>
								<details class="sh-PresetEvents">
									<summary class="sh-PresetEvents-toggle">
										<?php
										printf(
											/* translators: %d: number of event types */
											esc_html( _n( 'Monitors %d event type', 'Monitors %d event types', $event_count, 'simple-history' ) ),
											absint( $event_count )
										);
										?>
									</summary>
									<ul class="sh-PresetEvents-list">
										<?php foreach ( $preset_events as $event_key ) { ?>
											<li><?php echo esc_html( $event_labels[ $event_key ] ?? $event_key ); ?></li>
										<?php } ?>
									</ul>
								</details>
							</div>
						</div>

						<div class="sh-PresetCard-destinations">
							<span class="sh-PresetCard-destinationsLabel"><?php esc_html_e( 'Forward events to:', 'simple-history' ); ?></span>
							<div class="sh-PresetCard-destinationGroups">
								<?php foreach ( $destination_types as $type => $type_meta ) { ?>
									<div class="sh-PresetCard-destinationGroup">
										<span class="sh-PresetCard-destinationGroupLabel">
											<span class="dashicons dashicons-<?php echo esc_attr( $type_meta['icon'] ); ?>"></span>
											<?php echo esc_html( $type_meta['label'] ); ?>
										</span>
										<?php
										$type_samples = $sample_destinations[ $type ] ?? [];
										if ( empty( $type_samples ) ) {
											?>
											<p class="sh-PresetCard-destinationEmpty">
												<a href="#"><?php echo esc_html( $type_meta['empty_label'] ); ?> →</a>
											</p>
										<?php } else { ?>
											<div class="sh-PresetCard-destinationGroupItems">
												<?php foreach ( $type_samples as $sample ) { ?>
													<?php
													// Check the box for Email destinations on Security preset.
													$is_checked = $is_security;
													?>
													<label class="sh-PresetCard-destinationItem">
														<input type="checkbox" <?php checked( $is_checked ); ?> disabled />
														<span class="sh-PresetCard-destinationName">
															<?php echo esc_html( $sample['name'] ); ?>
															<?php if ( $type === 'email' && isset( $sample['recipient_count'] ) ) { ?>
																<span class="sh-PresetCard-emailRecipients">
																	<span class="dashicons dashicons-email-alt"></span><?php echo absint( $sample['recipient_count'] ); ?>
																</span>
															<?php } ?>
														</span>
														<?php if ( $is_checked ) { ?>
															<span class="sh-StatusIcon sh-StatusIcon--ok sh-PresetCard-destinationStatus">✓</span>
														<?php } ?>
													</label>
												<?php } ?>
											</div>
											<p class="sh-PresetCard-destinationManage">
												<a href="#"><?php echo esc_html( $type_meta['manage_label'] ); ?> →</a>
											</p>
										<?php } ?>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the custom rules tab preview.
	 *
	 * Shows sample custom rules in a table.
	 */
	private function render_custom_rules_preview() {
		$sample_custom_rules = $this->get_sample_custom_rules();
		?>
		<div class="sh-CustomRules">
			<?php if ( ! empty( $sample_custom_rules ) ) { ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'simple-history' ); ?></th>
							<th scope="col" class="manage-column column-when"><?php esc_html_e( 'When', 'simple-history' ); ?></th>
							<th scope="col" class="manage-column column-destinations"><?php esc_html_e( 'Destinations', 'simple-history' ); ?></th>
						</tr>
					</thead>
					<tbody id="the-list">
						<?php foreach ( $sample_custom_rules as $rule ) { ?>
							<tr>
								<td class="name column-name has-row-actions column-primary" data-colname="<?php esc_attr_e( 'Name', 'simple-history' ); ?>">
									<strong>
										<a class="row-title" href="#"><?php echo esc_html( $rule['name'] ); ?></a>
										<?php if ( empty( $rule['enabled'] ) ) { ?>
											<span class="post-state"> — <?php esc_html_e( 'Disabled', 'simple-history' ); ?></span>
										<?php } ?>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="#"><?php esc_html_e( 'Edit', 'simple-history' ); ?></a> |
										</span>
										<span class="delete">
											<a href="#" class="submitdelete"><?php esc_html_e( 'Delete', 'simple-history' ); ?></a>
										</span>
									</div>
									<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'simple-history' ); ?></span></button>
								</td>
								<td class="when column-when" data-colname="<?php esc_attr_e( 'When', 'simple-history' ); ?>">
									<?php if ( ! empty( $rule['conditions'] ) ) { ?>
										<?php foreach ( $rule['conditions'] as $condition ) { ?>
											<div>
												<strong><?php echo esc_html( $condition['field'] ); ?></strong> <?php echo esc_html( $condition['operator'] ); ?> <?php echo esc_html( $condition['value'] ); ?>
											</div>
										<?php } ?>
									<?php } else { ?>
										<em><?php esc_html_e( 'No conditions', 'simple-history' ); ?></em>
									<?php } ?>
								</td>
								<td class="destinations column-destinations" data-colname="<?php esc_attr_e( 'Destinations', 'simple-history' ); ?>">
									<?php if ( ! empty( $rule['destinations'] ) ) { ?>
										<?php foreach ( $rule['destinations'] as $dest_group ) { ?>
											<div>
												<strong><?php echo esc_html( $dest_group['type'] ); ?>:</strong> <?php echo esc_html( $dest_group['names'] ); ?>
											</div>
										<?php } ?>
									<?php } else { ?>
										<em><?php esc_html_e( 'None', 'simple-history' ); ?></em>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			<?php } else { ?>
				<p class="sh-CustomRules-empty">
					<?php esc_html_e( 'No custom rules configured yet.', 'simple-history' ); ?>
				</p>
			<?php } ?>

			<div class="sh-CustomRules-actions">
				<button type="button" class="button button-secondary" disabled>
					<?php esc_html_e( '+ Add Custom Rule', 'simple-history' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the upgrade CTA.
	 */
	private function render_upgrade_cta() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'alerts_settings_teaser' );
		?>
		<div class="sh-AlertsTeaser-cta">
			<div class="sh-AlertsTeaser-cta-content">
				<h3>
					<?php esc_html_e( 'Unlock Alerts & Notifications', 'simple-history' ); ?>
					<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				</h3>
				<p>
					<?php esc_html_e( 'Get instant notifications via Email, Slack, Discord, or Telegram when important events happen.', 'simple-history' ); ?>
				</p>
				<a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Unlock Alerts', 'simple-history' ); ?> →</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get destination type definitions.
	 *
	 * Matches the structure from premium module for consistency.
	 *
	 * @return array<string, array{label: string, icon: string, description: string, details_label: string, empty_label: string, manage_label: string}>
	 */
	private function get_destination_type_definitions(): array {
		return [
			'email'    => [
				'label'         => __( 'Email', 'simple-history' ),
				'icon'          => 'email',
				'description'   => __( 'Configure email recipients for alerts', 'simple-history' ),
				'details_label' => __( 'Recipients', 'simple-history' ),
				'empty_label'   => __( 'Add an email recipient', 'simple-history' ),
				'manage_label'  => __( 'Manage email', 'simple-history' ),
			],
			'slack'    => [
				'label'         => __( 'Slack', 'simple-history' ),
				'icon'          => 'format-chat',
				'description'   => __( 'Configure Slack webhooks for alerts', 'simple-history' ),
				'details_label' => __( 'Webhook URL', 'simple-history' ),
				'empty_label'   => __( 'Add a Slack channel', 'simple-history' ),
				'manage_label'  => __( 'Manage Slack', 'simple-history' ),
			],
			'discord'  => [
				'label'         => __( 'Discord', 'simple-history' ),
				'icon'          => 'games',
				'description'   => __( 'Configure Discord webhooks for alerts', 'simple-history' ),
				'details_label' => __( 'Webhook URL', 'simple-history' ),
				'empty_label'   => __( 'Connect Discord in minutes', 'simple-history' ),
				'manage_label'  => __( 'Manage Discord', 'simple-history' ),
			],
			'telegram' => [
				'label'         => __( 'Telegram', 'simple-history' ),
				'icon'          => 'admin-site-alt3',
				'description'   => __( 'Configure Telegram bot for alerts', 'simple-history' ),
				'details_label' => __( 'Chat ID', 'simple-history' ),
				'empty_label'   => __( 'Fast alerts on any device', 'simple-history' ),
				'manage_label'  => __( 'Manage Telegram', 'simple-history' ),
			],
		];
	}

	/**
	 * Get sample destinations for preview.
	 *
	 * Shows Email and Slack with sample data to demonstrate the feature.
	 * Discord and Telegram are left empty to show the empty state.
	 *
	 * @return array<string, array<array{name: string, details: string, rules_count: int, status: string, recipient_count?: int}>>
	 */
	private function get_sample_destinations(): array {
		return [
			'email' => [
				[
					'name'            => __( 'Admin Team', 'simple-history' ),
					'details'         => 'admin@example.com, security@example.com',
					'rules_count'     => 2,
					'status'          => __( 'OK (3 hours ago)', 'simple-history' ),
					'recipient_count' => 2,
				],
			],
			'slack' => [
				[
					'name'        => __( '#security-alerts', 'simple-history' ),
					'details'     => 'hooks.slack.com/services/T00.../B00.../xxxx',
					'rules_count' => 1,
					'status'      => __( 'OK (1 day ago)', 'simple-history' ),
				],
			],
			// Discord and Telegram intentionally left empty to show the add flow.
		];
	}

	/**
	 * Get preset definitions.
	 *
	 * Matches the structure from premium module.
	 *
	 * @return array<string, array{name: string, icon: string, description: string, events: string[]}>
	 */
	private function get_preset_definitions(): array {
		return [
			'security' => [
				'name'        => __( 'Security Alerts', 'simple-history' ),
				'icon'        => 'shield',
				'description' => __( 'New, edited & deleted users, role changes, password resets, theme & plugin file edits', 'simple-history' ),
				'events'      => [
					'user_created',
					'user_deleted',
					'user_updated_profile',
					'user_role_updated',
					'user_role_added',
					'user_role_removed',
					'user_requested_password_reset_link',
					'user_password_reseted',
					'user_application_password_created',
					'user_application_password_revoked',
					'user_session_destroy_everywhere',
					'theme_file_edited',
					'plugin_file_edited',
				],
			],
			'content'  => [
				'name'        => __( 'Content Changes', 'simple-history' ),
				'icon'        => 'edit',
				'description' => __( 'New, edited & deleted posts and pages, media changes', 'simple-history' ),
				'events'      => [
					'post_created',
					'post_updated',
					'post_deleted',
					'post_trashed',
					'post_restored',
					'attachment_created',
					'attachment_updated',
					'attachment_deleted',
				],
			],
			'plugins'  => [
				'name'        => __( 'WordPress & Plugin Updates', 'simple-history' ),
				'icon'        => 'update',
				'description' => __( 'Core, plugin & theme installs, updates, activations', 'simple-history' ),
				'events'      => [
					'core_updated',
					'core_auto_updated',
					'core_update_failed',
					'plugin_activated',
					'plugin_deactivated',
					'plugin_installed',
					'plugin_installed_failed',
					'plugin_deleted',
					'plugin_updated',
					'plugin_update_failed',
					'plugin_bulk_updated',
					'plugin_bulk_updated_failed',
					'theme_switched',
					'theme_installed',
					'theme_deleted',
					'theme_updated',
					'theme_update_failed',
				],
			],
		];
	}

	/**
	 * Get human-readable labels for event message keys.
	 *
	 * @return array<string, string> Associative array of message_key => label.
	 */
	private function get_event_labels(): array {
		return [
			// Security: User account changes.
			'user_created'                       => __( 'User created', 'simple-history' ),
			'user_deleted'                       => __( 'User deleted', 'simple-history' ),
			'user_updated_profile'               => __( 'User profile edited', 'simple-history' ),
			'user_role_updated'                  => __( 'User role changed', 'simple-history' ),
			'user_role_added'                    => __( 'User role added', 'simple-history' ),
			'user_role_removed'                  => __( 'User role removed', 'simple-history' ),

			// Security: Authentication.
			'user_requested_password_reset_link' => __( 'Password reset requested', 'simple-history' ),
			'user_password_reseted'              => __( 'Password reset completed', 'simple-history' ),
			'user_application_password_created'  => __( 'Application password created', 'simple-history' ),
			'user_application_password_revoked'  => __( 'Application password revoked', 'simple-history' ),
			'user_session_destroy_everywhere'    => __( 'All sessions destroyed', 'simple-history' ),

			// Security: File modifications.
			'theme_file_edited'                  => __( 'Theme file edited', 'simple-history' ),
			'plugin_file_edited'                 => __( 'Plugin file edited', 'simple-history' ),

			// Content: Posts and pages.
			'post_created'                       => __( 'Post created', 'simple-history' ),
			'post_updated'                       => __( 'Post updated', 'simple-history' ),
			'post_deleted'                       => __( 'Post deleted', 'simple-history' ),
			'post_trashed'                       => __( 'Post trashed', 'simple-history' ),
			'post_restored'                      => __( 'Post restored', 'simple-history' ),

			// Content: Media.
			'attachment_created'                 => __( 'Media uploaded', 'simple-history' ),
			'attachment_updated'                 => __( 'Media updated', 'simple-history' ),
			'attachment_deleted'                 => __( 'Media deleted', 'simple-history' ),

			// Updates: WordPress core.
			'core_updated'                       => __( 'WordPress updated', 'simple-history' ),
			'core_auto_updated'                  => __( 'WordPress auto-updated', 'simple-history' ),
			'core_update_failed'                 => __( 'WordPress update failed', 'simple-history' ),

			// Updates: Plugins.
			'plugin_activated'                   => __( 'Plugin activated', 'simple-history' ),
			'plugin_deactivated'                 => __( 'Plugin deactivated', 'simple-history' ),
			'plugin_installed'                   => __( 'Plugin installed', 'simple-history' ),
			'plugin_installed_failed'            => __( 'Plugin install failed', 'simple-history' ),
			'plugin_deleted'                     => __( 'Plugin deleted', 'simple-history' ),
			'plugin_updated'                     => __( 'Plugin updated', 'simple-history' ),
			'plugin_update_failed'               => __( 'Plugin update failed', 'simple-history' ),
			'plugin_bulk_updated'                => __( 'Plugins bulk updated', 'simple-history' ),
			'plugin_bulk_updated_failed'         => __( 'Plugins bulk update failed', 'simple-history' ),

			// Updates: Themes.
			'theme_switched'                     => __( 'Theme switched', 'simple-history' ),
			'theme_installed'                    => __( 'Theme installed', 'simple-history' ),
			'theme_deleted'                      => __( 'Theme deleted', 'simple-history' ),
			'theme_updated'                      => __( 'Theme updated', 'simple-history' ),
			'theme_update_failed'                => __( 'Theme update failed', 'simple-history' ),
		];
	}

	/**
	 * Get sample custom rules for preview.
	 *
	 * Shows example custom rules to demonstrate the feature.
	 * Uses realistic field names and operators that match Alert_Field_Registry.
	 *
	 * @return array<array{name: string, enabled: bool, conditions: array<array{field: string, operator: string, value: string}>, destinations: array<array{type: string, names: string}>}>
	 */
	private function get_sample_custom_rules(): array {
		return [
			[
				'name'         => __( 'Failed Login Attempts', 'simple-history' ),
				'enabled'      => true,
				'conditions'   => [
					[
						'field'    => __( 'Message Type', 'simple-history' ),
						'operator' => __( 'is', 'simple-history' ),
						'value'    => __( 'Failed login', 'simple-history' ),
					],
				],
				'destinations' => [
					[
						'type'  => __( 'Email', 'simple-history' ),
						'names' => __( 'Admin Team', 'simple-history' ),
					],
					[
						'type'  => __( 'Slack', 'simple-history' ),
						'names' => __( '#security-alerts', 'simple-history' ),
					],
				],
			],
			[
				'name'         => __( 'Plugin Installs', 'simple-history' ),
				'enabled'      => true,
				'conditions'   => [
					[
						'field'    => __( 'Message Type', 'simple-history' ),
						'operator' => __( 'is', 'simple-history' ),
						'value'    => __( 'Plugin installed', 'simple-history' ),
					],
				],
				'destinations' => [
					[
						'type'  => __( 'Email', 'simple-history' ),
						'names' => __( 'Admin Team', 'simple-history' ),
					],
				],
			],
			[
				'name'         => __( 'User Role Changes', 'simple-history' ),
				'enabled'      => false,
				'conditions'   => [
					[
						'field'    => __( 'New Role', 'simple-history' ),
						'operator' => __( 'is', 'simple-history' ),
						'value'    => __( 'Administrator', 'simple-history' ),
					],
				],
				'destinations' => [],
			],
		];
	}
}
