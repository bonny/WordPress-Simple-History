---
name: wordpress-org-compliance
description: Ensures WordPress.org compliance for freemium plugins and prevents trialware violations. Reviews premium features, upsells, license keys, and teasers before WordPress.org submission. Triggers: "premium", "upsell", "freemium", "license key", "wp.org guidelines", "trialware".
allowed-tools: Read, Grep, Glob
---

# WordPress.org Plugin Compliance

Ensures WordPress.org compliance for free vs premium code and upselling.

## Core Rule: No Locked Code

**Golden Rule:** All code in WordPress.org plugins must be free and fully functional.

- Every feature works completely without a license key
- No trial periods or usage limits
- No features requiring payment to unlock

## Quick Reference

### Not Allowed
- Trial periods or time limits
- Usage quotas or artificial limits
- License keys for local features
- Disabled features requiring payment
- Intrusive nag screens

### Allowed
- Informational upselling (non-intrusive)
- Separate premium plugin from your site
- Feature detection (not restriction)
- Comparison tables and teasers
- Disabled form previews for premium-only features

## Key Patterns

```php
// ❌ WRONG: Feature Restriction
if ( ! $this->is_premium() ) {
    echo 'Premium required';
    return;
}

// ✅ CORRECT: Feature Detection
$this->show_basic_export();
if ( $this->has_premium_addon() ) {
    do_action( 'sh_premium_export_options' );
}

// ❌ WRONG: Artificial Limits
$limit = $this->is_premium() ? 10000 : 100;

// ✅ CORRECT: No Artificial Limits
$limit = apply_filters( 'sh_event_limit', 10000 );
```

## Compliance Checklist

- [ ] All features work without license key
- [ ] No trial periods or time limits
- [ ] No usage quotas
- [ ] Upselling is informational, not obstructive
- [ ] Free version provides real value

## Detailed Information

- [examples.md](examples.md) - Detailed code examples
- [testing.md](testing.md) - Testing procedures and pre-submission verification

## Resources

- [WordPress.org Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#5-trialware-is-not-permitted)

**The Simple Rule:** If it's in the WordPress.org version, it must work completely without payment.
