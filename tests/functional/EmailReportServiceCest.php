<?php

/**
 * Functional tests for Email Report Service admin interface.
 * 
 * Run with Docker Compose:
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run functional:EmailReportServiceCest`
 */
class EmailReportServiceCest {
    public function _before( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('admin.php?page=simple_history_settings_page');
    }

    public function test_can_see_email_report_settings_section( FunctionalTester $I ) {
        $I->canSee('Email Reports');
        $I->canSee('Configure automatic email reports with website statistics. Reports are sent every Monday morning.');
    }

    public function test_can_see_email_report_settings_fields( FunctionalTester $I ) {
        // Check for Enable field
        $I->canSee('Enable');
        $I->canSee('Enable email reports');
        $I->seeElement('input[name="simple_history_email_report_enabled"]');
        
        // Check for Recipients field
        $I->canSee('Recipients');
        $I->seeElement('#simple_history_email_report_recipients');
        $I->canSee('Enter one email address per line.');
        
        // Check for Preview field
        $I->canSee('Preview');
        $I->canSee('Show email preview');
        // Check preview link exists (URL might be encoded)
        $I->seeElement('a.button-link');
        
        // Check for test email button
        $I->canSee('Send test email to');
        $I->seeElement('#simple-history-email-test');
    }

    public function test_enable_email_reports( FunctionalTester $I ) {
        $I->amGoingTo('Enable email reports');
        
        // Enable the checkbox
        $I->checkOption('input[name="simple_history_email_report_enabled"]');
        
        // Add recipient email addresses
        $I->fillField('#simple_history_email_report_recipients', "test1@example.com\ntest2@example.com");
        
        // Save settings
        $I->click('Save Changes');
        
        $I->expect('To see settings saved message');
        $I->canSee('Settings saved.');
        
        // Verify checkbox is still checked after save
        $I->seeCheckboxIsChecked('input[name="simple_history_email_report_enabled"]');
        
        // Verify recipients are saved
        $I->seeInField('#simple_history_email_report_recipients', "test1@example.com\ntest2@example.com");
    }

    public function test_disable_email_reports( FunctionalTester $I ) {
        // First enable it
        $I->checkOption('input[name="simple_history_email_report_enabled"]');
        $I->click('Save Changes');
        $I->canSee('Settings saved.');
        
        $I->amGoingTo('Disable email reports');
        
        // Uncheck the checkbox
        $I->uncheckOption('input[name="simple_history_email_report_enabled"]');
        
        // Save settings
        $I->click('Save Changes');
        
        $I->expect('To see settings saved message');
        $I->canSee('Settings saved.');
        
        // Verify checkbox is unchecked after save
        $I->dontSeeCheckboxIsChecked('input[name="simple_history_email_report_enabled"]');
    }

    public function test_recipients_field_validation( FunctionalTester $I ) {
        $I->amGoingTo('Test recipients field with various input formats');
        
        // Test with multiple emails separated by newlines
        $I->fillField('#simple_history_email_report_recipients', "admin@example.com\nuser@test.org\nsupport@company.co.uk");
        $I->click('Save Changes');
        $I->canSee('Settings saved.');
        $I->seeInField('#simple_history_email_report_recipients', "admin@example.com\nuser@test.org\nsupport@company.co.uk");
        
        // Test with emails separated by commas (should be normalized to newlines)
        $I->fillField('#simple_history_email_report_recipients', "first@example.com, second@example.com, third@example.com");
        $I->click('Save Changes');
        $I->canSee('Settings saved.');
        // After sanitization, emails should be on separate lines
        $I->seeInField('#simple_history_email_report_recipients', "first@example.com\nsecond@example.com\nthird@example.com");
        
        // Test with invalid emails mixed with valid ones (invalid should be filtered out)
        $I->fillField('#simple_history_email_report_recipients', "valid@example.com\ninvalid-email\nanother@valid.com");
        $I->click('Save Changes');
        $I->canSee('Settings saved.');
        // Only valid emails should remain
        $I->seeInField('#simple_history_email_report_recipients', "valid@example.com\nanother@valid.com");
    }

    public function test_preview_email_link_exists( FunctionalTester $I ) {
        $I->amGoingTo('Check that preview email link exists and has correct structure');
        
        // Check for preview link text
        $I->canSee('Show email preview');
        
        // Get the link and check its properties
        $preview_link = $I->grabAttributeFrom('a.button-link', 'href');
        
        // Check URL contains expected parts (URL-encoded or not)
        // The URL can be either format:
        // - http://wordpress/index.php?rest_route=%2Fsimple-history%2Fv1%2Femail-report%2Fpreview%2Fhtml
        // - or with slashes not encoded
        $I->assertStringContainsString('simple-history', $preview_link);
        $I->assertStringContainsString('preview', $preview_link);
        $I->assertStringContainsString('html', $preview_link);
        $I->assertStringContainsString('_wpnonce=', $preview_link);
        
        // Check that it opens in new tab
        $target = $I->grabAttributeFrom('a.button-link', 'target');
        $I->assertEquals('_blank', $target);
    }

    public function test_empty_recipients_field( FunctionalTester $I ) {
        $I->amGoingTo('Test saving with empty recipients field');
        
        // Enable email reports but leave recipients empty
        $I->checkOption('input[name="simple_history_email_report_enabled"]');
        $I->fillField('#simple_history_email_report_recipients', '');
        
        // Save settings
        $I->click('Save Changes');
        
        $I->expect('Settings to be saved even with empty recipients');
        $I->canSee('Settings saved.');
        
        // Verify checkbox is still checked
        $I->seeCheckboxIsChecked('input[name="simple_history_email_report_enabled"]');
        
        // Verify recipients field is empty
        $I->seeInField('#simple_history_email_report_recipients', '');
    }

    public function test_recipients_field_placeholder( FunctionalTester $I ) {
        $I->amGoingTo('Check recipients field placeholder text');
        
        $placeholder = $I->grabAttributeFrom('#simple_history_email_report_recipients', 'placeholder');
        $I->assertStringContainsString('email@example.com', $placeholder);
        $I->assertStringContainsString('another@example.com', $placeholder);
    }
}