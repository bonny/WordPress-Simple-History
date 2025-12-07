---
name: wordpress-org-compliance
description: Ensures WordPress.org compliance for freemium plugins (free vs premium features, license keys, trial limits, upselling). Prevents trialware violations. Use when adding premium features, implementing upsells, checking license keys, creating teasers, reviewing code before WordPress.org submission, or when the user mentions "premium", "upsell", or "freemium".
---

# WordPress.org Plugin Compliance Guidelines

Ensures WordPress.org compliance for plugin directory guidelines, particularly around free vs premium code and upselling practices.

## When to Use This Skill

**Trigger scenarios:**
- Adding new features that might have premium versions
- Implementing upselling or upgrade prompts
- Creating "teasers" for premium features
- Reviewing code for WordPress.org compliance
- Preparing plugin updates for WordPress.org submission
- Adding license key validation or premium checks

## Core Rule: No Locked Code

**WordPress.org Golden Rule**: No premium/locked code in wp.org plugins. All hosted code must be free and fully functional.

This means:
- Every feature in the free plugin must work completely without a license key
- No "trial periods" or usage limits
- No features that require payment to unlock
- No functionality restricted behind a paywall

## Quick Reference

### ❌ NOT Allowed
- Trial periods or time limits
- Usage quotas or artificial limits
- License keys for local features
- Disabled features requiring payment
- Crippled functionality in free version
- Intrusive nag screens

### ✅ Allowed
- Informational upselling (non-intrusive)
- Separate premium plugin from your site
- Feature detection (not restriction)
- External SaaS integrations
- Comparison tables
- Premium feature teasers
- Disabled form teasers for premium-only features (high conversion)

## Key Patterns

### ❌ Wrong: Feature Restriction

```php
// Blocks functionality without license
if ( ! $this->is_premium() ) {
    echo 'Premium required';
    return;
}
```

### ✅ Correct: Feature Detection

```php
// Free version works fully
$this->show_basic_export();

// Premium can enhance
if ( $this->has_premium_addon() ) {
    do_action( 'sh_premium_export_options' );
}
```

### ❌ Wrong: Artificial Limits

```php
// Artificially limits free version
$limit = $this->is_premium() ? 10000 : 100;
```

### ✅ Correct: No Artificial Limits

```php
// Free version has reasonable limit
$limit = 10000;

// Premium can extend
$limit = apply_filters( 'sh_event_limit', $limit );
```

## Recommended Freemium Model

### Free Plugin (WordPress.org)
- Core functionality fully working
- Basic features complete
- All UI features accessible
- No artificial limitations

### Premium Plugin (Your Site)
- Extended features (not unlocked basics)
- Premium integrations
- Advanced functionality
- Priority support

### Installation Options

**Add-on Style** (Recommended):
- Free: Standalone and functional
- Premium: Installs alongside, extends free
- Both active together

**Replacement Style**:
- Free: Standalone and functional
- Premium: Replaces free with more features
- User deactivates free when installing premium

## Upselling Best Practices

### ✅ Good Upselling

```php
// Subtle, informational
function render_premium_note() {
    ?>
    <p class="sh-premium-note">
        <small>
            💡 Premium: Export to JSON, XML, or PDF formats.
            <a href="https://example.com/premium/">Learn more</a>
        </small>
    </p>
    <?php
}
```

### ✅ High-Converting: Disabled Form Pattern

For premium-only features, show a disabled form that previews the UI:

```php
// Shows what premium feature looks like (greyed out, non-functional)
// CTA box remains clickable - converts 3-4x better than info box
<div class="sh-PremiumTeaser-disabledForm" style="opacity: 0.6; pointer-events: none;">
    <table class="form-table">
        <tr><th>Setting</th><td><select disabled>...</select></td></tr>
    </table>
    <div style="pointer-events: auto; opacity: 1;">
        <?php echo Helpers::get_premium_feature_teaser(...); ?>
    </div>
</div>
```

See [examples.md](examples.md) for full implementation details.

### ❌ Bad Upselling

```php
// Blocks functionality
if ( ! $this->is_premium() ) {
    echo '<div class="error">Upgrade required!</div>';
    return;
}
```

## Compliance Checklist

Before submitting updates to WordPress.org:

- [ ] All features work without license key
- [ ] No trial periods or time limits
- [ ] No usage quotas or restrictions
- [ ] No features disabled for free users
- [ ] Upselling is informational, not obstructive
- [ ] Premium features are separate addon
- [ ] No "locked" UI elements
- [ ] Free version provides real value

## Code Review Questions

For each feature, ask yourself:

1. **Does it work completely without premium?**
2. **Am I checking for a license key to enable functionality?**
3. **Would a free user be frustrated by this?**
4. **Is the upselling helpful or annoying?**
5. **Does the free version provide real value?**

## Common Violations

### Violation 1: Nag Screens
❌ Don't show constant popups asking users to upgrade

### Violation 2: Crippled Features
❌ Don't make features "work badly" in free to force upgrades

### Violation 3: Expired Trials
❌ Don't stop working after X days

### Violation 4: Usage Limits
❌ Don't impose artificial quotas

### Violation 5: License Validation for Local Features
❌ Don't require license key for features that run locally

## Detailed Information

For comprehensive examples and testing procedures:

- See [examples.md](examples.md) for detailed code examples of allowed and forbidden patterns
- See [testing.md](testing.md) for complete testing procedures, code review checklists, and pre-submission verification

## Resources

- **Official Guidelines**: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#5-trialware-is-not-permitted
- **WordPress.org Plugin Directory**: https://wordpress.org/plugins/
- **Plugin Review Team**: For questions about compliance

## Summary

**The Simple Rule**: If it's in the WordPress.org version, it must work completely without payment.

**The Philosophy**: Win users over with quality, not restrictions. Make them **want** premium, not **need** it.

**The Test**: Would you be happy using the free version? If not, you're doing freemium wrong.
