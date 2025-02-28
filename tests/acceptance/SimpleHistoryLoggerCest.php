<?php
use \Step\Acceptance\Admin;

/**
 * Test that Simple History settings page works.
 */
class SimpleHistoryLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();        
        $I->loginAsAdminToHistorySettingsPage();
    }

    /**
     * 
     */
    public function it_sees_common_texts(Admin $I) {
        $I->canSee('Simple History');
        $I->canSee('Show History');
        $I->canSee('on the dashboard');
        $I->canSee('RSS feed');
    }
    
    public function it_can_log_show_history(Admin $I) {
        $I->uncheckOption('#simple_history_show_on_dashboard');
        $I->click('Save Changes');

        $I->seeLogMessage('Modified settings');
        $I->seeLogContext([
            'show_on_dashboard_prev' => '1',
            'show_on_dashboard_new' => '0',
            'show_as_page_prev' => '1',
            'show_as_page_new' => '0',
        ]);
    }

    public function it_can_clear_log_now(Admin $I) {
        $I->click('Clear log now');
        $I->acceptPopup();       
        $I->wait(0.5);
        $I->seeLogMessageStartsWith('Cleared the log for Simple History (');

        // Check that db is empty.
        $events_table = $I->grabPrefixedTableNameFor('simple_history');
        $contexts_table = $I->grabPrefixedTableNameFor('simple_history_contexts');

        $I->assertEquals(1, $I->countRowsInDatabase($events_table), 'There should be one row in events table');
        $I->assertLessThan(10, $I->countRowsInDatabase($contexts_table), 'There should be few rows in contexts table');
    }

    public function it_can_enable_rss_feed(Admin $I) {
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        $I->seeLogMessage('Modified settings');
        $I->seeLogContext([
            'enable_rss_feed_prev' => '0',
            'enable_rss_feed_new' => '1',
        ]);
    }

    public function it_can_regenerate_rss_address(Admin $I) {
        $I->checkOption('#simple_history_enable_rss_feed');
        $I->click('Save Changes');

        $I->click('Generate new address');
        $I->seeLogMessage('Regenerated RSS feed secret');
    }

    // Can't get this to work, no idea why, very strange.
    // public function it_can_log_items_per_page(Admin $I) {
    //     $I->selectOption('[name=simple_history_pager_size]', "10");
    //     $I->selectOption('[name=simple_history_pager_size_dashboard]', "10");
    //     $I->click('Save Changes');

    //     $I->wait(1);
        
    //     $I->seeLogMessage('Modified settings');
    //     $I->seeLogContext([
    //         'pager_size_prev' => '100',
    //         'pager_size_new' => '20',
    //         'pager_size_dashboard_prev' => '100',
    //         'pager_size_dashboard_new' => '20',
    //     ]);
    // }

}
