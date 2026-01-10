# Premium Upsell Design Guidelines

Use this skill when creating or modifying premium feature teasers, upsell prompts, or upgrade CTAs in the Simple History plugin.

## Design Principles

1. **Consistent, not intrusive** - Premium prompts should be helpful, not annoying
2. **Show value** - Demonstrate what users get, don't just tell them
3. **Visual consistency** - All premium upsells use the same color scheme and patterns

## Color Scheme

All premium upsells use a **cream/beige background** with **green accents**:

| Element | CSS Variable/Value |
|---------|-------------------|
| Background | `var(--sh-color-cream)` |
| Badge | `var(--sh-color-green-mint)` |
| Checkmarks | `#00a32a` |
| Text (primary) | `#1d2327` |
| Text (secondary) | `#3c434a` or `#50575e` |
| Border (if needed) | `#e0d9c8` |

**Do NOT use:**
- Blue backgrounds (`#f0f6fc`) for premium upsells
- Blue buttons for primary CTAs
- Warning/alert colors (yellow, red)

## CSS Classes

### Premium Badge
```html
<span class="sh-Badge sh-Badge--premium">Premium</span>
```
- Green mint background with black text
- Use alongside titles to indicate premium features

### Feature Teaser Container
```html
<div class="sh-PremiumFeatureTeaser">
    <p class="sh-PremiumFeatureTeaser-title">
        Feature Name
        <span class="sh-Badge sh-Badge--premium">Premium</span>
    </p>
    <ul class="sh-PremiumFeatureTeaser-features">
        <li><span class="dashicons dashicons-yes"></span> Benefit one</li>
        <li><span class="dashicons dashicons-yes"></span> Benefit two</li>
    </ul>
    <p class="sh-PremiumFeatureTeaser-ctaLinkContainer">
        <a href="...">Unlock Feature →</a>
    </p>
</div>
```

### Disabled Form Pattern
For showing disabled/grayed-out premium features:
```html
<div class="sh-PremiumTeaser-disabledForm" inert>
    <!-- Form fields shown but not interactive -->
</div>
```
- Use HTML `inert` attribute for accessibility
- CSS handles visual styling (opacity 0.75)

## CTA Patterns

### Preferred: Simple Link
```html
<a href="...">Unlock Feature →</a>
```
- Use arrow (`→`) suffix
- Action-oriented verb ("Unlock", "Get", "Enable")
- Feature-specific when possible ("Unlock Alerts" not "Get Premium")

### Alternative: Small Button (sparingly)
```html
<a href="..." class="button button-small button-primary">Get Premium</a>
```
- Only use when link would be missed
- Keep button text short

## Copy Guidelines

### Titles
- Be specific: "Unlock All Log Formats" not "Premium Feature"
- Include badge inline with title

### Benefits List
- Use green checkmarks (`dashicons-yes`)
- 2-4 bullet points maximum
- Focus on outcomes, not features
- Example: "Compatible with Graylog, Splunk, and more" not "Supports multiple formats"

### CTA Text
- Action verb + feature: "Unlock Alerts", "Enable Export"
- Avoid generic: "Learn More", "Upgrade Now"
- Add arrow suffix for links: "Unlock Alerts →"

## Component Examples

### Inline Teaser (in settings)
```php
<div class="sh-PremiumFeatureTeaser">
    <p class="sh-PremiumFeatureTeaser-title">
        <?php esc_html_e( 'Feature Name', 'simple-history' ); ?>
        <span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
    </p>
    <ul class="sh-PremiumFeatureTeaser-features">
        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Benefit one', 'simple-history' ); ?></li>
        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Benefit two', 'simple-history' ); ?></li>
    </ul>
    <p class="sh-PremiumFeatureTeaser-ctaLinkContainer">
        <a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Unlock Feature', 'simple-history' ); ?> →</a>
    </p>
</div>
```

### Preview Mode Banner (for full-page previews)
```php
<div class="sh-AlertsTeaser-banner">
    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
    <div class="sh-AlertsTeaser-banner-content">
        <span class="sh-AlertsTeaser-banner-title">
            <?php esc_html_e( 'Preview Mode', 'simple-history' ); ?>
            <span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
        </span>
        <span><?php esc_html_e( 'Description of what user is previewing.', 'simple-history' ); ?></span>
        <a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Unlock Feature', 'simple-history' ); ?> →</a>
    </div>
</div>
```

## Tracking URLs

Always use `Helpers::get_tracking_url()` for premium links:
```php
$premium_url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'source_identifier' // e.g., 'alerts_settings_teaser', 'export_formats_teaser'
);
```

## Related Files

- `/css/styles.css` - Search for `.sh-PremiumFeatureTeaser` and `.sh-Badge--premium`
- `/inc/helpers/class-helpers.php` - `get_tracking_url()` method
- See `wordpress-org-compliance` skill for freemium guidelines
