# WordPress.org Compliance Testing

This file provides comprehensive testing procedures and review questions to ensure WordPress.org compliance.

## Manual Testing Procedure

### Step 1: Deactivate Premium Plugin

```bash
# Via WP-CLI
wp plugin deactivate simple-history-premium

# Via WordPress admin
# Go to Plugins → Deactivate "Simple History Premium"
```

**Why**: Test that free version works independently.

---

### Step 2: Test Every Feature

Go through each feature systematically and verify it works completely:

#### Event Logging
- [ ] Events are being logged
- [ ] Event details are visible
- [ ] Event history is retained
- [ ] No "premium required" messages appear

#### Event Viewing
- [ ] Event list displays correctly
- [ ] Pagination works
- [ ] Event details expand/collapse
- [ ] Search functionality works

#### Filtering
- [ ] Basic filters work (event type, date, user)
- [ ] Filter combinations work
- [ ] "Reset filters" works
- [ ] No disabled filter options

#### Export
- [ ] Export button is clickable
- [ ] Export generates file
- [ ] File contains data
- [ ] No error messages about premium

#### Settings
- [ ] All settings pages accessible
- [ ] Settings can be changed
- [ ] Settings save correctly
- [ ] No locked/disabled settings

---

### Step 3: Check for Locked Features

Look for these red flags:

❌ **Disabled Buttons**
```html
<button disabled>Feature Name (Premium Only)</button>
```

❌ **License Key Requirements**
```php
if ( empty( $license_key ) ) {
    return; // Feature doesn't work
}
```

❌ **Usage Limits**
```php
if ( $usage > $limit ) {
    echo 'Limit reached. Upgrade to continue.';
}
```

❌ **Trial Expiration**
```php
if ( $days > 30 ) {
    echo 'Trial expired.';
}
```

---

### Step 4: Check for Pushy Upsells

Evaluate upselling messages:

#### ✅ Acceptable
- Small info boxes in sidebar
- "Learn more" links
- Feature comparison tables
- Occasional upgrade suggestions

#### ❌ Unacceptable
- Full-screen popups on every page
- Blocking overlays
- Constant nag notices
- Disabled UI elements with "upgrade" messages

---

### Step 5: Verify UI Elements Work

Test that all UI elements are functional:

- [ ] All buttons work (not disabled)
- [ ] All links go somewhere useful
- [ ] All forms submit successfully
- [ ] No placeholder/dummy content
- [ ] No "coming soon" features

---

## Code Review Checklist

### Automated Checks

Search codebase for potential violations:

```bash
# Search for license validation
grep -r "license.*validate" --include="*.php"
grep -r "has_valid_license" --include="*.php"

# Search for trial/expiration logic
grep -r "trial.*expire" --include="*.php"
grep -r "days.*since.*install" --include="*.php"

# Search for usage limits
grep -r "limit.*reached" --include="*.php"
grep -r "quota.*exceeded" --include="*.php"

# Search for disabled features
grep -r "disabled.*premium" --include="*.php"
grep -r "requires.*premium" --include="*.php"
```

---

### Manual Code Review

#### 1. License Key Checks

❌ **Forbidden Pattern**:
```php
function export_events() {
    if ( ! $this->validate_license() ) {
        return false; // Blocks functionality
    }
    // Export code
}
```

✅ **Allowed Pattern**:
```php
function export_events() {
    // Works without license check
    $this->do_export();
}
```

#### 2. Feature Gates

❌ **Forbidden Pattern**:
```php
if ( ! $this->is_premium() ) {
    echo 'Premium required';
    return;
}
```

✅ **Allowed Pattern**:
```php
// Free version works
$this->show_basic_export();

// Premium can enhance
if ( $this->has_premium_addon() ) {
    do_action( 'sh_premium_export_options' );
}
```

#### 3. Artificial Limits

❌ **Forbidden Pattern**:
```php
$limit = $this->is_premium() ? 10000 : 100;
```

✅ **Allowed Pattern**:
```php
$limit = 10000; // Free works fully

// Premium can extend via filter
$limit = apply_filters( 'sh_event_limit', $limit );
```

---

## Code Review Questions

For each feature you implement, ask:

### 1. Does it work completely without premium?

```php
// Ask: Can free users use this feature fully?
function render_event_filters() {
    // ✅ Good: Free users can filter events
    // ❌ Bad: Filters disabled without premium
}
```

### 2. Am I checking for a license key?

```php
// Ask: Why am I checking license?
if ( ! $this->has_valid_license() ) {
    // ❌ Bad: Blocking local functionality
    // ✅ OK: Connecting to external paid service
}
```

### 3. Would a free user be frustrated?

```php
// Ask: Does this create a bad experience?
<button disabled>Export (Premium Only)</button>
// ❌ Bad: Showing disabled features is frustrating
```

### 4. Is the upselling helpful or annoying?

```php
// Ask: Does this help or hinder the user?
if ( ! $this->is_premium() ) {
    echo '<div class="error">Upgrade now!</div>'; // ❌ Annoying
    echo '<p><small>Tip: Premium includes JSON export.</small>'; // ✅ Helpful
}
```

### 5. Does the free version provide real value?

```php
// Ask: Would I be happy using this for free?
// ✅ Good: Yes, it logs and displays events
// ❌ Bad: Only logs 10 events before requiring upgrade
```

---

## Compliance Test Plan

### Before Submission to WordPress.org

Run through this complete checklist:

#### Features Test
- [ ] All core features work without premium
- [ ] No license keys required for functionality
- [ ] No trial periods or time limits
- [ ] No usage quotas or artificial limits
- [ ] Export/import works fully
- [ ] Settings are accessible and functional

#### UI/UX Test
- [ ] No disabled buttons for local features
- [ ] No full-screen nag screens
- [ ] No blocking popups
- [ ] Upselling is subtle and informational
- [ ] Free version UI is complete and polished

#### Code Review
- [ ] No `has_valid_license()` checks for local features
- [ ] No `is_premium()` gates blocking functionality
- [ ] No trial expiration logic
- [ ] No usage counting/limiting
- [ ] Premium detection only for feature enhancement

#### Documentation Review
- [ ] readme.txt doesn't mislead about free features
- [ ] Screenshots show free version capabilities
- [ ] FAQ explains free vs premium clearly

---

## Common Violations and How to Fix

### Violation 1: Disabled Export Button

❌ **Current Code**:
```php
<?php if ( ! $this->is_premium() ) : ?>
    <button disabled>Export (Premium Only)</button>
<?php else : ?>
    <button>Export</button>
<?php endif; ?>
```

✅ **Fixed Code**:
```php
<!-- Free version: CSV export works -->
<button onclick="exportCSV()">Export as CSV</button>

<?php if ( ! $this->has_premium_addon() ) : ?>
    <p class="description">
        💡 Premium: Also export to JSON, XML, and PDF.
        <a href="#">Learn more</a>
    </p>
<?php endif; ?>
```

---

### Violation 2: Event Retention Limit

❌ **Current Code**:
```php
$retention_days = $this->is_premium() ? 365 : 7;
```

✅ **Fixed Code**:
```php
// Free version: reasonable retention (60 days)
$retention_days = 60;

// Premium can extend
if ( $this->has_premium_addon() ) {
    $retention_days = apply_filters( 'sh_retention_days', 365 );
}
```

---

### Violation 3: License Check for Email Reports

❌ **Current Code**:
```php
function send_email_reports() {
    if ( ! $this->validate_license() ) {
        return false;
    }

    // Send email
}
```

✅ **Fixed Code** (Option A: Remove feature from free):
```php
// Move to premium plugin entirely
// Don't include in free version at all
```

✅ **Fixed Code** (Option B: Make it work in free):
```php
function send_email_reports() {
    // Works in free version
    $this->send_basic_email_report();

    // Premium can enhance
    if ( $this->has_premium_addon() ) {
        do_action( 'sh_premium_email_report' );
    }
}
```

---

### Violation 4: Nag Screen on Every Page

❌ **Current Code**:
```php
add_action( 'admin_notices', function() {
    if ( ! $this->is_premium() ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <h2>⚠️ Upgrade to Premium!</h2>
            <p>You're missing out on amazing features. Upgrade now!</p>
            <a href="#" class="button button-primary">Upgrade</a>
        </div>
        <?php
    }
});
```

✅ **Fixed Code**:
```php
// Only show on plugin's own pages, not every admin page
add_action( 'admin_notices', function() {
    $screen = get_current_screen();

    if ( $screen->id !== 'simple_history_page_settings' ) {
        return; // Only show on our pages
    }

    if ( get_user_meta( get_current_user_id(), 'sh_dismissed_premium_notice', true ) ) {
        return; // User dismissed it
    }

    ?>
    <div class="notice notice-info is-dismissible" data-dismiss="sh_premium_notice">
        <p>
            💡 <strong>Tip:</strong> Simple History Premium offers extended retention and more export formats.
            <a href="#">Learn more</a>
        </p>
    </div>
    <?php
});
```

---

## Pre-Submission Checklist

Before submitting to WordPress.org, verify:

### Functionality
- [ ] ✅ All features work without payment
- [ ] ✅ No license key requirements
- [ ] ✅ No trial periods
- [ ] ✅ No usage limits

### User Experience
- [ ] ✅ Free version provides value
- [ ] ✅ Upselling is non-intrusive
- [ ] ✅ No disabled UI elements
- [ ] ✅ No frustrating limitations

### Code Quality
- [ ] ✅ No license validation for local features
- [ ] ✅ No artificial limits in code
- [ ] ✅ Premium detection for enhancement only
- [ ] ✅ Clean separation of free/premium code

### Documentation
- [ ] ✅ readme.txt is accurate
- [ ] ✅ Screenshots show free version
- [ ] ✅ FAQ addresses free vs premium

---

## Testing After Updates

For each plugin update, test:

1. **Regression Test**: All free features still work
2. **New Features**: Any new features work in free version
3. **Upselling**: No new intrusive upsells added
4. **Performance**: No artificial slowdowns in free version

---

## Resources

- **WordPress.org Guidelines**: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#5-trialware-is-not-permitted
- **Plugin Review Team**: https://make.wordpress.org/plugins/
- **Support Forum**: Ask questions if unsure about compliance

---

## Summary

**The Test**: Would you be happy using the free version? If not, you're doing freemium wrong.

**Remember**:
- Test with premium deactivated
- Verify all features work
- Check for pushy upsells
- Review code for violations
- Ask yourself: "Is this user-friendly?"
