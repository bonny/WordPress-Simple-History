<?php

/**
 * Run with Docker Compose:
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run functional:RssFeedCest`
 */
class RssFeedCest {
    public function _before( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug');
    }

	public function test_can_get_see_rss_settings( FunctionalTester $I ) {
        $I->canSee('Simple History has a RSS feed which');
        $I->canSee('Enable RSS feed');
        
        // Don't see this text yet, because feed is not enabled.
        $I->dontSee('You can generate a new address for the RSS feed.');
    }

    public function test_enable_rss_feed( FunctionalTester $I ) {
        $I->canSee('Enable RSS feed');    

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
        $I->canSee('History for wp-tests');
        $I->canSee('Logged in');
        $I->canSeeInCurrentUrl('simple_history_get_rss=1');
        $I->canSeeInCurrentUrl('rss_secret=');
    }
}
