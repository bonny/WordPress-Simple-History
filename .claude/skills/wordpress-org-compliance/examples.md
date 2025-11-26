# WordPress.org Compliance Code Examples

This file contains comprehensive code examples showing correct and incorrect patterns for WordPress.org compliance.

## What Is NOT Allowed

### ❌ Trialware Patterns

**Trial Period (Forbidden)**

```php
// ❌ WRONG - Trial period
if ( $days_since_install > 30 ) {
    return 'Trial expired. Please purchase.';
}

// ❌ WRONG - Time-limited features
$install_date = get_option( 'sh_install_date' );
$days_used = ( time() - $install_date ) / DAY_IN_SECONDS;

if ( $days_used > 14 ) {
    wp_die( 'Free trial has expired. Please upgrade to continue using Simple History.' );
}
```

**Usage Limits (Forbidden)**

```php
// ❌ WRONG - Event count limits
$events_logged = $this->get_events_count();
if ( $events_logged > 1000 ) {
    return 'Free tier limit reached. Upgrade to continue.';
}

// ❌ WRONG - Export quotas
$exports_this_month = get_user_meta( $user_id, 'sh_exports_count', true );
if ( $exports_this_month >= 5 ) {
    return 'Monthly export limit reached. Upgrade to Premium for unlimited exports.';
}
```

**Disabled Features Requiring License (Forbidden)**

```php
// ❌ WRONG - Feature doesn't work without license
function export_events() {
    if ( ! $this->has_valid_license() ) {
        return; // Feature completely disabled
    }

    // Export code here
}

// ❌ WRONG - License check blocking functionality
function send_email_reports() {
    $license_key = get_option( 'sh_license_key' );

    if ( empty( $license_key ) || ! $this->validate_license( $license_key ) ) {
        return false;
    }

    // Email code here
}
```

---

### ❌ Locked Features

**Feature Completely Disabled (Forbidden)**

```php
// ❌ WRONG - Feature completely disabled
function export_events() {
    if ( ! $this->is_premium() ) {
        echo '<div class="notice notice-error">';
        echo '<p>This feature requires Premium.</p>';
        echo '</div>';
        return;
    }
    // Export code here
}

// ❌ WRONG - UI shows but doesn't work
function render_export_button() {
    if ( ! $this->is_premium() ) {
        ?>
        <button disabled class="button">
            Export (Premium Only)
        </button>
        <?php
        return;
    }

    // Working export button
}
```

---

### ❌ Feature Restrictions

**Artificial Limitations (Forbidden)**

```php
// ❌ WRONG - Limited functionality in free version
function get_event_history() {
    $limit = $this->is_premium() ? 10000 : 100;
    return $this->get_events( $limit );
}

// ❌ WRONG - Crippled features
function export_format() {
    if ( ! $this->is_premium() ) {
        return 'csv'; // Only CSV in free, JSON/XML in premium
    }

    return $_POST['format'] ?? 'csv';
}

// ❌ WRONG - Reduced quality/functionality
function get_event_details() {
    if ( $this->is_premium() ) {
        return $this->get_full_details(); // Full details
    }

    return $this->get_basic_details(); // Intentionally limited
}
```

---

## What IS Allowed

### ✅ Upselling & Advertising

**Informational Teaser (Allowed)**

```php
// ✅ CORRECT - Informational teaser, doesn't block functionality
function render_premium_teaser() {
    ?>
    <div class="sh-premium-teaser">
        <h3>🎉 Want More Features?</h3>
        <p>Simple History Premium includes:</p>
        <ul>
            <li>Extended event retention (keep logs for years, not months)</li>
            <li>Advanced filtering options (filter by date range, user, event type)</li>
            <li>Email notifications for critical events</li>
            <li>Slack integration for real-time alerts</li>
            <li>Export to JSON, XML, and PDF formats</li>
        </ul>
        <a href="https://simple-history.com/premium/" class="button button-primary">
            Learn More About Premium
        </a>
    </div>
    <?php
}

// ✅ CORRECT - Teaser in sidebar doesn't block free features
function render_settings_page() {
    ?>
    <div class="wrap">
        <div class="sh-settings-main">
            <!-- Free features work fully here -->
            <?php $this->render_free_settings(); ?>
        </div>

        <div class="sh-settings-sidebar">
            <!-- Non-intrusive premium info -->
            <?php $this->render_premium_teaser(); ?>
        </div>
    </div>
    <?php
}
```

---

### ✅ Separate Premium Plugin

**Feature Detection (Allowed)**

```php
// ✅ CORRECT - Check if premium add-on is installed
function has_premium_addon() {
    return class_exists( 'SimpleHistoryPremium' );
}

function show_premium_features() {
    if ( $this->has_premium_addon() ) {
        // Premium addon provides these features
        do_action( 'sh_premium_features' );
    } else {
        // Show teaser/info about premium addon (not blocking)
        $this->render_premium_teaser();
    }
}

// ✅ CORRECT - Allow premium to extend functionality
function get_export_formats() {
    $formats = [ 'csv' => 'CSV' ]; // Free version has CSV

    // Premium can add more formats via filter
    return apply_filters( 'sh_export_formats', $formats );
}
```

**Hooks for Extension (Allowed)**

```php
// ✅ CORRECT - Provide hooks for premium to extend
function render_event_filters() {
    ?>
    <div class="sh-filters">
        <!-- Free filters work completely -->
        <select name="event_type">
            <option value="">All Types</option>
            <option value="post">Posts</option>
            <option value="user">Users</option>
            <option value="plugin">Plugins</option>
        </select>

        <!-- Allow premium to add more filters -->
        <?php do_action( 'sh_after_basic_filters' ); ?>
    </div>
    <?php
}

// In premium plugin (separate)
add_action( 'sh_after_basic_filters', function() {
    ?>
    <input type="date" name="date_from" placeholder="From date">
    <input type="date" name="date_to" placeholder="To date">
    <select name="user_id">
        <option>Filter by user...</option>
        <?php // User options ?>
    </select>
    <?php
});
```

---

### ✅ SaaS Integrations

**External Service Integration (Allowed)**

```php
// ✅ CORRECT - External paid service integration
function connect_to_monitoring_service() {
    $api_key = get_option( 'sh_monitoring_api_key' );

    if ( empty( $api_key ) ) {
        ?>
        <div class="sh-integration-setup">
            <h3>Real-Time Monitoring</h3>
            <p>Connect to our monitoring service for real-time alerts and advanced analytics.</p>
            <ul>
                <li>Instant email/SMS notifications</li>
                <li>Dashboard analytics</li>
                <li>Historical reporting</li>
            </ul>
            <a href="https://monitoring-service.com/signup" class="button">
                Sign Up (30-day free trial)
            </a>
        </div>
        <?php
        return;
    }

    // Service integration here (external service provides the functionality)
    $this->send_to_monitoring_service( $api_key );
}
```

---

## Recommended Patterns

### ✅ Free Plugin: Full Functionality

**Export Feature - Free Version**

```php
// ✅ CORRECT - Free version works completely
function export_events() {
    // Free version exports to CSV (fully functional)
    $events = $this->get_events();
    $csv_data = $this->convert_to_csv( $events );

    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename="events.csv"' );
    echo $csv_data;
    exit;
}

function render_export_section() {
    ?>
    <div class="sh-export">
        <h3>Export Events</h3>
        <p>Download your event history as a CSV file.</p>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <input type="hidden" name="action" value="sh_export_events">
            <?php wp_nonce_field( 'sh_export' ); ?>
            <button type="submit" class="button button-primary">
                Export as CSV
            </button>
        </form>

        <?php if ( ! $this->has_premium_addon() ) : ?>
            <p class="sh-premium-note">
                <small>
                    💡 <strong>Premium tip:</strong> Export to JSON, XML, or PDF formats.
                    <a href="https://simple-history.com/premium/">Learn more</a>
                </small>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
```

**Filtering Feature - Free Version**

```php
// ✅ CORRECT - Free version has working filters
function render_event_filters() {
    ?>
    <div class="sh-filters">
        <h3>Filter Events</h3>

        <!-- Free features: fully working -->
        <label>
            Event Type:
            <select name="event_type">
                <option value="">All Types</option>
                <option value="post">Posts & Pages</option>
                <option value="user">Users</option>
                <option value="plugin">Plugins</option>
                <option value="theme">Themes</option>
            </select>
        </label>

        <label>
            Search:
            <input type="text" name="search" placeholder="Search events...">
        </label>

        <button type="submit" class="button">Apply Filters</button>

        <?php if ( ! $this->has_premium_addon() ) : ?>
            <!-- Teaser for premium (doesn't block free features) -->
            <div class="sh-premium-teaser-inline">
                <small>
                    ✨ <strong>Premium</strong>: Filter by custom date ranges, specific users,
                    IP addresses, and more. <a href="#">Learn more</a>
                </small>
            </div>
        <?php else : ?>
            <!-- Premium features (from addon) -->
            <?php do_action( 'sh_premium_filters' ); ?>
        <?php endif; ?>
    </div>
    <?php
}
```

---

### ✅ Comparison: Free vs Premium

**Good Upselling Example**

```php
// ✅ CORRECT - Subtle, helpful, informational
function show_export_options() {
    ?>
    <div class="sh-export">
        <h3>Export Events</h3>

        <!-- Free version: fully functional -->
        <form method="post">
            <button type="submit" name="format" value="csv" class="button button-primary">
                📄 Export as CSV
            </button>
        </form>

        <!-- Info about premium (not blocking) -->
        <?php if ( ! $this->has_premium_addon() ) : ?>
            <div class="sh-premium-comparison">
                <h4>More Export Options in Premium</h4>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Format</th>
                            <th>Free</th>
                            <th>Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>CSV</td>
                            <td>✅ Yes</td>
                            <td>✅ Yes</td>
                        </tr>
                        <tr>
                            <td>JSON</td>
                            <td>➖</td>
                            <td>✅ Yes</td>
                        </tr>
                        <tr>
                            <td>XML</td>
                            <td>➖</td>
                            <td>✅ Yes</td>
                        </tr>
                        <tr>
                            <td>PDF</td>
                            <td>➖</td>
                            <td>✅ Yes</td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <a href="https://simple-history.com/premium/" class="button">
                        Upgrade to Premium
                    </a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
```

**Bad Upselling Example**

```php
// ❌ WRONG - Too pushy, blocks functionality
function show_export_options() {
    if ( ! $this->is_premium() ) {
        ?>
        <div class="sh-export-locked">
            <h3>⚠️ Export Disabled</h3>
            <p><strong>Export is a premium feature.</strong></p>
            <p>Free users cannot export events. Please upgrade to use this feature.</p>
            <button disabled class="button">Export (Premium Only)</button>
            <a href="https://simple-history.com/premium/" class="button button-primary">
                Upgrade Now
            </a>
        </div>
        <?php
        return;
    }

    // Export code (never reached by free users)
}
```

---

## Feature Detection vs Feature Restriction

### ✅ Feature Detection (Allowed)

```php
// ✅ CORRECT - Detect if premium addon adds functionality
if ( $this->has_premium_addon() ) {
    // Premium addon adds extra functionality
    $this->show_advanced_filters();
} else {
    // Free version shows standard filters (fully working)
    $this->show_standard_filters();
}

// ✅ CORRECT - Allow premium to enhance, not unlock
$retention_days = 60; // Free: 60 days (fully functional)

if ( $this->has_premium_addon() ) {
    $retention_days = apply_filters( 'sh_retention_days', 365 ); // Premium: can extend
}
```

### ❌ Feature Restriction (Forbidden)

```php
// ❌ WRONG - Artificially limit free version
if ( ! $this->is_premium() ) {
    $retention_days = 7;  // Artificially limited
    $max_events = 100;     // Artificially limited
    $export_enabled = false; // Artificially disabled
} else {
    $retention_days = 365;
    $max_events = unlimited;
    $export_enabled = true;
}
```

---

## Summary of Patterns

### ✅ Allowed
- Feature detection (`has_premium_addon()`)
- Informational teasers (non-intrusive)
- Separate premium plugin extending free
- Hooks and filters for extension
- Comparison tables showing differences
- External SaaS integrations (if service provides functionality)

### ❌ Forbidden
- License key validation for local features
- Trial periods or expiration dates
- Usage limits or quotas
- Features disabled without license
- Artificially crippled functionality
- Nag screens blocking workflow
