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
        $I->canSee('Every Monday, get a summary of:');
    }

    public function test_can_see_email_report_settings_fields( FunctionalTester $I ) {
        // Check for Enable field
        $I->canSee('Enable');
        $I->canSee('Enable weekly digest');
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

    public function test_html_preview_accessible( FunctionalTester $I ) {
        $I->amGoingTo('Test that HTML preview is accessible and shows expected content');
        
        // Get the preview link URL
        $preview_link = $I->grabAttributeFrom('a.button-link', 'href');
        
        // Visit the preview URL
        $I->amOnUrl($preview_link);
        
        // Check that we see email preview content
        $I->seeInSource('<!DOCTYPE html');
        $I->seeInSource('Website activity summary');
        
        // Check for main sections in the email template
        $I->seeInSource('Total events');
        $I->seeInSource('Event count by day');
        $I->seeInSource('Posts and Pages');
        
        // Verify it's marked as preview.
        // This is shown in the email <title>.
        $I->seeInSource('(preview)');
        
        // Check for site name (wp-tests)
        $I->seeInSource('wp-tests');
    }

    public function test_send_test_email_button_functionality( FunctionalTester $I ) {
        $I->amGoingTo('Test that the send test email button is present and properly configured');
        
        // Get the current user email (we know admin user exists)
        $current_user_email = $I->grabFromDatabase('wp_users', 'user_email', ['user_login' => 'admin']);
        
        // Check that the test email button exists and shows current user email
        $I->seeElement('#simple-history-email-test');
        $I->canSee("Send test email to {$current_user_email}");
        
        // Verify the button has the correct attributes
        $button_type = $I->grabAttributeFrom('#simple-history-email-test', 'type');
        $I->assertEquals('button', $button_type);
        
        $button_class = $I->grabAttributeFrom('#simple-history-email-test', 'class');
        $I->assertStringContainsString('button', $button_class);
        $I->assertStringContainsString('button-link', $button_class);
        
        // Check that the JavaScript for handling the button click is present
        $I->seeInSource('simple-history-email-test');
        $I->seeInSource('wp.apiFetch');
        $I->seeInSource('simple-history/v1/email-report/preview/email');
        
        // Check that nonce and API fetch setup is present
        $I->seeInSource('nonceMiddleware');
        $I->seeInSource('wp.apiFetch.createNonceMiddleware');
    }

    public function test_preview_with_date_range( FunctionalTester $I ) {
        $I->amGoingTo('Test that preview shows data for the last 7 days');
        
        // Visit the preview page
        $preview_link = $I->grabAttributeFrom('a.button-link', 'href');
        $I->amOnUrl($preview_link);
        
        // Check that date range is shown (should be last 7 days)
        $I->seeInSource('–'); // Date range separator
        
        // Verify statistics sections are present even if empty
        $I->seeInSource('Posts created');
        $I->seeInSource('Posts and Pages');
        $I->seeInSource('Event count by day');
        $I->seeInSource('Total events');
    }

    public function test_preview_link_requires_authentication( FunctionalTester $I ) {
        $I->amGoingTo('Test that preview link requires authentication');
        
        // Get the preview link
        $preview_link = $I->grabAttributeFrom('a.button-link', 'href');
        
        // Log out
        $I->amOnPage('/wp-login.php?action=logout');
        $I->click('log out');
        
        // Try to access preview without authentication
        $I->amOnUrl($preview_link);
        
        // Should see error or be redirected
        // The REST API should return a 401 or 403 error for unauthenticated users
        $I->dontSeeInSource('Website activity summary');
        $I->seeResponseCodeIsClientError();
    }

    public function test_send_email_rest_api_endpoint( FunctionalTester $I ) {
        $I->amGoingTo('Test the send email REST API endpoint responds correctly');
        
        // Get the current user email
        $current_user_email = $I->grabFromDatabase('wp_users', 'user_email', ['user_login' => 'admin']);
        
        // Get nonce from the preview link (best practice for this test setup)
        $preview_link = $I->grabAttributeFrom('a.button-link', 'href');
        preg_match('/[?&]_wpnonce=([^&]+)/', $preview_link, $matches);
        $nonce = isset($matches[1]) ? $matches[1] : '';
        
        // Test the REST API endpoint via browser (functional testing approach)
        $email_endpoint = "/index.php?rest_route=/simple-history/v1/email-report/preview/email&_wpnonce={$nonce}";
        $I->amOnPage($email_endpoint);
        
        // Verify we get a JSON response
        $response_content = $I->grabPageSource();
        $I->assertStringStartsWith('{', trim($response_content), 'Response should be JSON');
        $response_data = json_decode($response_content, true);
        $I->assertIsArray($response_data, 'Response should be valid JSON');
        
        // Check response format - handles both success and expected failure scenarios
        if (isset($response_data['success'])) {
            // Custom success/error format from Simple History
            if ($response_data['success']) {
                $I->assertStringContainsString('Test email sent successfully', $response_data['message']);
                $I->assertStringContainsString($current_user_email, $response_data['message']);
            } else {
                $I->assertStringContainsString('Failed to send test email', $response_data['message']);
            }
        } elseif (isset($response_data['code'])) {
            // WordPress error format (expected when email sending fails in test environment)
            $I->assertEquals('email_send_failed', $response_data['code']);
            $I->assertEquals('Failed to send test email.', $response_data['message']);
            $I->assertEquals(500, $response_data['data']['status']);
        } else {
            $I->fail('Response should be either success format or WordPress error format');
        }
    }
}