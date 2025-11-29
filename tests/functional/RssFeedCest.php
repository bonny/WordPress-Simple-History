<?php

/**
 * Run with Docker Compose:
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run functional:RssFeedCest`
 */
class RssFeedCest {
    public function _before( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('admin.php?page=simple_history_settings_page');
    }

	public function test_can_get_see_rss_settings( FunctionalTester $I ) {
        $I->canSee('Monitor your site activity in real-time with feeds.');
        $I->canSee('Enable feed');
        
        // Don't see this text yet, because feed is not enabled.
        $I->dontSee('You can generate a new address for the RSS feed.');
    }

    public function test_enable_rss_feed( FunctionalTester $I ) {
        $I->canSee('Enable feed');

        $I->amGoingTo('Enable RSS feed');
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        $I->expect('To see settings saved message');
        $I->canSee('Settings saved.');
        $I->canSee('Address');
        $I->canSee('Regenerate');
        $I->canSee('You can generate a new address for the RSS feed.');

        /**
         * @var string $feed_address For example "http://wordpress/?simple_history_get_rss=1&rss_secret=zeiaozijawhqywksoh".
         */
        $feed_address = $I->grabAttributeFrom('#simple_history_rss_feed_address', 'href');
        $I->assertStringStartsWith('http://', $feed_address, 'Feed address should start with "http://"');

        $I->amGoingTo('Visit the RSS feed and check for some texts');
        $I->amOnUrl($feed_address);
        $I->seeInSource('<title>History for wp-tests</title>');
        $I->seeInSource('<title>Logged in</title>');
        $I->canSeeInCurrentUrl('simple_history_get_rss=1');
        $I->canSeeInCurrentUrl('rss_secret=');

        // Test passing query args to the feed.
        $feed_address = $feed_address . "&loggers=SimplePostLogger,SimpleUserLogger";
        $I->amOnUrl($feed_address);
        $I->seeInSource('<title>History for wp-tests</title>');
        $I->seeInSource('<title>Logged in</title>');

        // Remove user logger, logged in should not be in the feed any more.
        $feed_address_posts_only = str_replace('loggers=SimplePostLogger,SimpleUserLogger', 'loggers=SimplePostLogger', $feed_address);
        $I->amOnUrl($feed_address_posts_only);
        $I->seeInSource('<title>History for wp-tests</title>');
        $I->dontSeeInSource('<title>Logged in</title>');

        // Get base feed address for testing other parameters.
        // Use the original feed_address from line 37, removing any existing query parameters after rss_secret.
        $base_feed_url_parts = explode('&loggers=', $feed_address);
        $base_feed_address = $base_feed_url_parts[0];

        // Test messages parameter - filter by specific message type.
        // Format is LoggerSlug:MessageKey.
        $feed_address_messages = $base_feed_address . "&messages=SimpleUserLogger:user_logged_in";
        $I->amOnUrl($feed_address_messages);
        $I->seeInSource('<title>History for wp-tests</title>');
        $I->seeInSource('<title>Logged in</title>');

        // Test loglevels parameter - info level should include login events.
        $feed_address_loglevel = $base_feed_address . "&loglevels=info";
        $I->amOnUrl($feed_address_loglevel);
        $I->seeInSource('<title>History for wp-tests</title>');
        $I->seeInSource('<title>Logged in</title>');
    }

    public function test_rss_feed_pagination( FunctionalTester $I ) {
        $I->amGoingTo('Test RSS feed pagination with posts_per_page and paged parameters');

        // Enable RSS feed.
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        // Get feed address.
        $feed_address = $I->grabAttributeFrom('#simple_history_rss_feed_address', 'href');

        // Test posts_per_page parameter limits results.
        // Note: We can't easily verify exact count in browser test, but we can verify the parameter is accepted.
        $feed_with_limit = $feed_address . "&posts_per_page=5";
        $I->amOnUrl($feed_with_limit);
        $I->seeInSource('<rss version="2.0"');
        $I->seeInSource('<channel>');
        $I->canSeeInCurrentUrl('posts_per_page=5');

        // Test paged parameter for pagination.
        $feed_page_2 = $feed_address . "&posts_per_page=5&paged=2";
        $I->amOnUrl($feed_page_2);
        $I->seeInSource('<rss version="2.0"');
        $I->canSeeInCurrentUrl('paged=2');
    }

    public function test_rss_feed_with_invalid_parameters( FunctionalTester $I ) {
        $I->amGoingTo('Test RSS feed handles invalid parameters gracefully');

        // Enable RSS feed.
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        // Get feed address.
        $feed_address = $I->grabAttributeFrom('#simple_history_rss_feed_address', 'href');

        // Test with invalid logger name - should still return valid XML.
        $feed_invalid_logger = $feed_address . "&loggers=NonExistentLogger";
        $I->amOnUrl($feed_invalid_logger);
        $I->seeInSource('<rss version="2.0"');
        $I->seeInSource('<channel>');

        // Test with invalid loglevel - should still return valid XML.
        $feed_invalid_level = $feed_address . "&loglevels=invalid_level";
        $I->amOnUrl($feed_invalid_level);
        $I->seeInSource('<rss version="2.0"');
        $I->seeInSource('<channel>');

        // Test with malformed parameters - should be sanitized.
        $feed_malformed = $feed_address . "&loggers=<script>alert(1)</script>";
        $I->amOnUrl($feed_malformed);
        $I->seeInSource('<rss version="2.0"');
        $I->dontSeeInSource('<script>');
    }

    public function test_rss_feed_multiple_filter_combinations( FunctionalTester $I ) {
        $I->amGoingTo('Test combining multiple RSS feed filters together');

        // Enable RSS feed.
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        // Get feed address.
        $feed_address = $I->grabAttributeFrom('#simple_history_rss_feed_address', 'href');

        // Test combining loggers + loglevels.
        $feed_combined = $feed_address . "&loggers=SimpleUserLogger,SimplePostLogger&loglevels=info,warning";
        $I->amOnUrl($feed_combined);
        $I->seeInSource('<rss version="2.0"');
        $I->canSeeInCurrentUrl('loggers=');
        $I->canSeeInCurrentUrl('loglevels=');

        // Test combining loggers + messages + posts_per_page.
        $feed_complex = $feed_address . "&loggers=SimpleUserLogger&messages=SimpleUserLogger:user_logged_in&posts_per_page=10";
        $I->amOnUrl($feed_complex);
        $I->seeInSource('<rss version="2.0"');
        $I->seeInSource('<title>Logged in</title>');
    }

    public function test_rss_feed_without_rss_secret( FunctionalTester $I ) {
        $I->amGoingTo('Test RSS feed requires valid RSS secret');

        // Enable RSS feed.
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        // Try to access RSS feed without secret.
        $I->amOnPage('/?simple_history_get_rss=1');
        $I->dontSeeInSource('<rss version="2.0"');

        // Try with wrong secret.
        $I->amOnPage('/?simple_history_get_rss=1&rss_secret=wrong_secret');
        $I->seeInSource('<rss version="2.0"');
        $I->seeInSource('Wrong RSS secret');
    }
}
