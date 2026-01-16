---
name: premium-upsell-design
description: Design premium feature teasers, upsell prompts, and upgrade CTAs with consistent visual styling. Use when creating premium UI components, adding upsell banners, implementing freemium patterns, or styling premium badges. Triggers: "premium teaser", "upsell", "premium badge", "CTA design", "upgrade prompt".
allowed-tools: Read, Grep, Glob, Edit, Write
---

# Premium Upsell Design

Design guidelines for premium feature teasers in Simple History.

## Color Scheme

Cream/beige background with green accents:

| Element | Value |
|---------|-------|
| Background | `var(--sh-color-cream)` |
| Badge | `var(--sh-color-green-mint)` |
| Checkmarks | `#00a32a` |
| Text | `#1d2327` / `#3c434a` |

**Never use:** Blue backgrounds, blue buttons, or warning colors for premium upsells.

## CSS Classes

```html
<!-- Badge -->
<span class="sh-Badge sh-Badge--premium">Premium</span>

<!-- Teaser Container -->
<div class="sh-PremiumFeatureTeaser">
    <p class="sh-PremiumFeatureTeaser-title">
        Feature Name
        <span class="sh-Badge sh-Badge--premium">Premium</span>
    </p>
    <ul class="sh-PremiumFeatureTeaser-features">
        <li><span class="dashicons dashicons-yes"></span> Benefit</li>
    </ul>
    <p class="sh-PremiumFeatureTeaser-ctaLinkContainer">
        <a href="...">Unlock Feature →</a>
    </p>
</div>

<!-- Disabled Form Preview -->
<div class="sh-PremiumTeaser-disabledForm" inert>
    <!-- Non-interactive preview -->
</div>
```

## CTA Guidelines

- **Format:** Action verb + feature + arrow: `Unlock Alerts →`
- **Avoid:** Generic text like "Learn More" or "Upgrade Now"
- **Be specific:** "Unlock All Log Formats" not "Premium Feature"

## Tracking URLs

```php
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'alerts_settings_teaser'
);
```

## Copy Rules

- 2-4 benefit bullet points maximum
- Focus on outcomes, not features
- Use green checkmarks (`dashicons-yes`)

## Related

- CSS: `/css/styles.css` (search `.sh-PremiumFeatureTeaser`)
- Helper: `/inc/helpers/class-helpers.php` (`get_tracking_url()`)
- See `wordpress-org-compliance` skill for freemium guidelines
