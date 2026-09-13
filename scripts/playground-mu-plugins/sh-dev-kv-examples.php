<?php
/**
 * Plugin Name: Simple History Dev — key-value table examples
 * Description: Registers a dev-only logger whose events render the event-details key-value table with deliberately awkward content: short keys, very long keys, unbreakable values, added/removed-only rows, diffs, many rows. Visit any wp-admin URL with ?sh-dev-kv-examples=1 as an administrator to log one event per example and land on the Simple History log. Lives under scripts/, which .distignore excludes, so it never ships.
 *
 * @package SimpleHistoryDev
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.PrefixAllGlobals -- dev-only mu-plugin under scripts/, never shipped; the sh_dev_ prefix is deliberate.

use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Diff_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;

add_filter(
	'simple_history/loggers_to_instantiate',
	function ( $loggers ) {
		if ( ! class_exists( \Simple_History\Loggers\Logger::class ) ) {
			return $loggers;
		}

		/**
		 * Logger that renders whatever rows the context tells it to.
		 *
		 * Context keys:
		 * - example_title  Shown as the event message.
		 * - kv_groups      JSON: list of groups, each { "formatter": "table"|"diff", "rows": [ { "name", "new", "prev" }, ... ] }.
		 */
		if ( ! class_exists( 'SH_Dev_Key_Value_Examples_Logger' ) ) {
			// phpcs:disable Squiz.Classes.ClassDeclaration -- declared inside a class_exists() guard.
			/**
			 * Dev logger whose events exercise the key-value table edge cases.
			 */
			class SH_Dev_Key_Value_Examples_Logger extends \Simple_History\Loggers\Logger {
				// phpcs:enable Squiz.Classes.ClassDeclaration
				/** @var string */
				protected $slug = 'SHDevKeyValueExamplesLogger';

				/**
				 * @return array
				 */
				public function get_info() {
					return [
						'name'        => 'Dev: key-value table examples',
						'description' => 'Development-only logger producing key-value table edge cases.',
						'capability'  => 'manage_options',
						'messages'    => [
							// Message strings must pass through gettext, or the logger never learns the key.
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
							'kv_example' => __( '{example_title}', 'simple-history' ),
						],
					];
				}

				/**
				 * @param object $row Log row.
				 * @return Event_Details_Container|null
				 */
				public function get_log_row_details_output( $row ) {
					$context = $row->context;
					$groups  = json_decode( $context['kv_groups'] ?? '[]', true );

					if ( ! is_array( $groups ) || $groups === [] ) {
						return null;
					}

					$out = [];

					foreach ( $groups as $group_config ) {
						$group = new Event_Details_Group();

						$formatter = ( $group_config['formatter'] ?? 'table' ) === 'diff'
						? new Event_Details_Group_Diff_Table_Formatter()
						: new Event_Details_Group_Table_Formatter();

						$group->set_formatter( $formatter );

						if ( ! empty( $group_config['title'] ) ) {
							$group->set_title( $group_config['title'] );
						}

						foreach ( $group_config['rows'] as $r ) {
							$item = new Event_Details_Item( null, $r['name'] ?? '' );

							if ( array_key_exists( 'new', $r ) && array_key_exists( 'prev', $r ) ) {
								$item->set_values( $r['new'], $r['prev'] );
							} elseif ( array_key_exists( 'new', $r ) ) {
								$item->set_new_value( $r['new'] );
							} elseif ( array_key_exists( 'prev', $r ) ) {
								$item->set_prev_value( $r['prev'] );
							}

							$group->add_item( $item );
						}

						$out[] = $group;
					}

					return new Event_Details_Container( $out, $context );
				}
			}
		}

		$loggers[] = 'SH_Dev_Key_Value_Examples_Logger';

		return $loggers;
	}
);

/**
 * Example definitions: title => list of groups.
 *
 * @return array<string, array>
 */
function sh_dev_kv_examples() {
	$long_url   = 'https://example.com/shop/product-category/very-long-category-name/another-level/yet-another-level/final-product-slug-that-goes-on-and-on-and-on?utm_source=newsletter&utm_medium=email&utm_campaign=spring_sale_2026&utm_content=hero_button&session=abcdefghijklmnopqrstuvwxyz0123456789';
	$long_token = 'sk_live_' . str_repeat( 'a1b2c3d4e5f6g7h8i9j0', 8 );
	$lorem_old  = "The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs. How vexingly quick daft zebras jump!\n\nSecond paragraph that stays the same in both versions so the diff has some unchanged context to render around the change.";
	$lorem_new  = "The quick brown fox leaps over the sleepy dog. Pack my box with six dozen liquor jugs. How vexingly quick daft zebras jump!\n\nSecond paragraph that stays the same in both versions so the diff has some unchanged context to render around the change.\n\nA third paragraph that was added in the new version, making the diff longer than a single line.";

	$table = function ( array $rows, $title = null ) {
		return [
			'formatter' => 'table',
			'title'     => $title,
			'rows'      => $rows,
		];
	};

	$diff = function ( array $rows, $title = null ) {
		return [
			'formatter' => 'diff',
			'title'     => $title,
			'rows'      => $rows,
		];
	};

	return [
		'01 Short keys, short values (profile edit)'       => [
			$table(
				[
					[
						'name' => 'First name',
						'new'  => 'Pär',
						'prev' => 'Par',
					],
					[
						'name' => 'Last name',
						'new'  => 'Thernström',
						'prev' => 'T',
					],
					[
						'name' => 'Nickname',
						'new'  => 'Bonny',
						'prev' => 'par',
					],
					[
						'name' => 'Display name',
						'new'  => 'Pär Thernström',
						'prev' => 'Par T',
					],
				]
			),
		],
		'01b Added-only rows (plugin installed)'           => [
			$table(
				[
					[
						'name' => 'Description',
						'new'  => 'Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.',
					],
					[
						'name' => 'Version',
						'new'  => '5.32.0',
					],
					[
						'name' => 'Author',
						'new'  => 'Pär Thernström',
					],
					[
						'name' => 'URL',
						'new'  => 'https://simple-history.com',
					],
				]
			),
		],
		'02 Single row (redirect edit)'                    => [
			$table(
				[
					[
						'name' => 'Source URL',
						'new'  => '/sourceb',
						'prev' => '/source',
					],
				] 
			),
		],
		'03 Long keys with spaces (WooCommerce settings)'  => [
			$table(
				[
					[
						'name' => 'Currency',
						'new'  => 'SEK',
						'prev' => 'EUR',
					],
					[
						'name' => 'Enable the legacy REST API for external integrations',
						'new'  => 'Yes',
						'prev' => 'No',
					],
					[
						'name' => 'Product reviews: require a verified owner before the review is shown on the product page',
						'new'  => 'Enabled',
						'prev' => 'Disabled',
					],
					[
						'name' => 'Number of decimals',
						'new'  => '2',
						'prev' => '0',
					],
				]
			),
		],
		'04 Very long unbreakable key'                     => [
			$table(
				[
					[
						'name' => 'woocommerce_email_customer_completed_order_additional_content_setting_name',
						'new'  => 'Thanks for shopping with us.',
						'prev' => '',
					],
					[
						'name' => 'Short',
						'new'  => 'value',
						'prev' => 'old',
					],
				]
			),
		],
		'05 Very long unbreakable value (URL)'             => [
			$table(
				[
					[
						'name' => 'Target URL',
						'new'  => $long_url,
						'prev' => 'https://example.com/old',
					],
					[
						'name' => 'Match type',
						'new'  => 'URL and query string',
						'prev' => 'URL only',
					],
				]
			),
		],
		'06 Long unbreakable value without slashes (token)' => [
			$table(
				[
					[
						'name' => 'API key',
						'new'  => $long_token,
						'prev' => 'sk_live_short',
					],
				]
			),
		],
		'07 Added-only and removed-only rows'              => [
			$table(
				[
					[
						'name' => 'Role added',
						'new'  => 'Editor',
					],
					[
						'name' => 'Role removed',
						'prev' => 'Subscriber',
					],
					[
						'name' => 'Website',
						'new'  => 'https://simple-history.com',
						'prev' => '',
					],
					[
						'name' => 'Description',
						'new'  => '',
						'prev' => 'Old bio text that was removed entirely.',
					],
				]
			),
		],
		'08 Long prose values wrapping'                    => [
			$table(
				[
					[
						'name' => 'Description',
						'new'  => 'A fairly long biography paragraph that will need to wrap onto several lines inside the value column so we can see how the key column behaves when the value is tall. It keeps going for a while to make sure of that.',
						'prev' => 'A previous, also fairly long, biography paragraph that also wraps onto several lines so that both the new and the old value are multi-line at the same time.',
					],
					[
						'name' => 'Tagline',
						'new'  => 'Just another WordPress site',
						'prev' => 'Hello world',
					],
				]
			),
		],
		'09 Many rows (15)'                                => [
			$table(
				array_map(
					function ( $i ) {
						return [
							'name' => "Setting $i",
							'new'  => "Value $i",
							'prev' => 'Old ' . $i,
						];
					},
					range( 1, 15 )
				)
			),
		],
		'10 Diff table (post content)'                     => [
			$diff(
				[
					[
						'name' => 'Title',
						'new'  => 'Hello world, again',
						'prev' => 'Hello world',
					],
					[
						'name' => 'Content',
						'new'  => $lorem_new,
						'prev' => $lorem_old,
					],
					[
						'name' => 'Excerpt',
						'new'  => 'Short excerpt here.',
						'prev' => '',
					],
				]
			),
		],
		'11 Diff table with a very long unbreakable line'  => [
			$diff(
				[
					[
						'name' => 'Permalink',
						'new'  => $long_url,
						'prev' => 'https://example.com/old',
					],
				]
			),
		],
		'12 Two groups in one event'                       => [
			$table(
				[
					[
						'name' => 'Status',
						'new'  => 'Published',
						'prev' => 'Draft',
					],
					[
						'name' => 'Author',
						'new'  => 'Pär',
						'prev' => 'admin',
					],
				],
				'Meta'
			),
			$diff(
				[
					[
						'name' => 'Content',
						'new'  => $lorem_new,
						'prev' => $lorem_old,
					],
				],
				'Content'
			),
		],
		'13 Empty and whitespace keys'                     => [
			$table(
				[
					[
						'name' => '',
						'new'  => 'value with empty key',
						'prev' => 'old',
					],
					[
						'name' => ' ',
						'new'  => 'value with blank key',
						'prev' => 'old',
					],
					[
						'name' => 'Normal',
						'new'  => 'ok',
						'prev' => 'was',
					],
				]
			),
		],
		'14 Values with markup and newlines (escaped)'     => [
			$table(
				[
					[
						'name' => 'Custom HTML',
						'new'  => '<script>alert(1)</script><b>bold</b>',
						'prev' => '<i>old</i>',
					],
					[
						'name' => 'Multiline',
						'new'  => "Line one\nLine two\nLine three",
						'prev' => "Line one\nLine 2",
					],
				]
			),
		],
		'16 Single value-only row (settings "changed" marker)' => [
			$table(
				[
					[
						'name' => 'Message Control',
						'new'  => '(changed)',
					],
				] 
			),
		],
		'17 Added-only short rows (template part updated)' => [
			$table(
				[
					[
						'name' => 'Slug',
						'new'  => 'header',
					],
					[
						'name' => 'Area',
						'new'  => 'header',
					],
					[
						'name' => 'Theme',
						'new'  => 'Twenty Twenty-Four',
					],
				]
			),
		],
		'18 Diff table, single row, empty previous value (post created)' => [
			$diff(
				[
					[
						'name' => 'Status',
						'new'  => 'publish',
						'prev' => '',
					],
				] 
			),
		],
		'19 Diff table, single row, empty new value (field cleared)' => [
			$diff(
				[
					[
						'name' => 'Excerpt',
						'new'  => '',
						'prev' => 'An excerpt that was removed.',
					],
				] 
			),
		],
		'15 Numeric and boolean-ish values'                => [
			$table(
				[
					[
						'name' => 'Posts per page',
						'new'  => '10',
						'prev' => '20',
					],
					[
						'name' => 'Comments',
						'new'  => 'Enabled',
						'prev' => 'Disabled',
					],
					[
						'name' => 'Timezone',
						'new'  => 'Europe/Stockholm',
						'prev' => 'UTC+0',
					],
				]
			),
		],
	];
}

add_action(
	'admin_init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['sh-dev-kv-examples'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$simple_history = \Simple_History\Simple_History::get_instance();
		$logger         = $simple_history->get_instantiated_logger_by_slug( 'SHDevKeyValueExamplesLogger' );

		if ( ! $logger ) {
			wp_die( 'SHDevKeyValueExamplesLogger not instantiated.' );
		}

		// Log in reverse so example 01 ends up at the top of the log.
		foreach ( array_reverse( sh_dev_kv_examples(), true ) as $title => $groups ) {
			$logger->info_message(
				'kv_example',
				[
					'example_title' => $title,
					'kv_groups'     => wp_json_encode( $groups ),
				]
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=simple_history_admin_menu_page' ) );
		exit;
	}
);
