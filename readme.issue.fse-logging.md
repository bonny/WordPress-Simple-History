# Full Site Editor (FSE) Logging Research

This document researches what Full Site Editor features should be logged in Simple History.

## FSE Overview

The Full Site Editor (FSE) was introduced in WordPress 5.9 and allows users to edit all parts of their site (templates, template parts, global styles, navigation menus, etc.) using blocks.

## FSE Post Types

WordPress uses custom post types to store FSE data:

| Post Type | Description | WordPress Version |
|-----------|-------------|-------------------|
| `wp_template` | Block templates for pages, archives, etc. | 5.9+ |
| `wp_template_part` | Header, footer, sidebar template parts | 5.9+ |
| `wp_global_styles` | Site-wide design settings (colors, typography, spacing) | 5.9+ |
| `wp_block` | Synced Patterns (formerly Reusable Blocks) | 5.0+ |
| `wp_navigation` | Block-based navigation menus | 5.9+ |
| `wp_font_family` | Custom font families | 6.5+ |
| `wp_font_face` | Font face declarations (weight/style variants) | 6.5+ |

## Current Simple History Logging Status

### Already Logged via Post_Logger

The `Post_Logger` (`loggers/class-post-logger.php`) already logs changes to all post types by default, with some exclusions.

**Current exclusions** (from `get_skip_posttypes()` on line 535):
- `nav_menu_item` - handled by Menu_Logger
- `jetpack_migration` - Jetpack internal
- `jp_sitemap`, `jp_img_sitemap`, `jp_sitemap_master` - Jetpack sitemaps
- `attachment` - media files
- `secupress_log_action` - SecuPress internal
- `customize_changeset` - added by Theme_Logger (line 147)

**FSE post types NOT currently excluded**, so they should be logged:
- `wp_template`
- `wp_template_part`
- `wp_global_styles`
- `wp_block` (Synced Patterns)
- `wp_navigation`
- `wp_font_family`
- `wp_font_face`

### Menu_Logger Logs Classic Menus Only

The `Menu_Logger` (`loggers/class-menu-logger.php`) only logs classic navigation menus from `Appearance > Menus`. It does NOT log `wp_navigation` post type changes.

### Theme_Logger Logs Theme Changes

The `Theme_Logger` (`loggers/class-theme-logger.php`) logs:
- Theme switches
- Theme installation/update/deletion
- Customizer changes (appearance_customized)
- Widget changes
- Custom background changes

It does NOT specifically log Global Styles (`wp_global_styles`) changes in a meaningful way.

## What Should Be Logged

### 1. Templates (`wp_template`)

**Actions to log:**
- Template created (user first edits a theme template)
- Template updated (content changes)
- Template deleted
- Template reset to default/theme version

**Special considerations:**
- Templates start as `auto-draft` when synced from theme
- Become `publish` when user edits them
- Need to track the template type (single, archive, page, etc.)
- Should show which theme template was overridden

### 2. Template Parts (`wp_template_part`)

**Actions to log:**
- Template part created
- Template part updated
- Template part deleted
- Template part reset to default

**Special considerations:**
- Has an "area" (header, footer, general)
- Should show which area the template part belongs to

### 3. Global Styles (`wp_global_styles`)

**Actions to log:**
- Color palette changed
- Typography settings changed (fonts, sizes)
- Spacing/padding changed
- Border/shadow settings changed
- Layout settings changed
- Styles reset to defaults

**Special considerations:**
- Stored as JSON in post_content
- Changes should be parsed and shown meaningfully
- Show which specific style properties changed
- Very noisy - many small changes during editing session

### 4. Synced Patterns (`wp_block`)

**Actions to log:**
- Pattern created
- Pattern updated (affects all instances)
- Pattern deleted
- Sync status changed (synced <-> unsynced)

**Special considerations:**
- Post meta `wp_pattern_sync_status` tracks if synced
- Updates to synced patterns affect all uses
- Should indicate sync status in log

### 5. Navigation Menus (`wp_navigation`)

**Actions to log:**
- Navigation menu created
- Navigation menu items added/removed
- Navigation menu items reordered
- Navigation menu deleted

**Special considerations:**
- Different from classic menus (Menu_Logger)
- Menu items stored as block content
- Should show menu structure changes

### 6. Custom Fonts (`wp_font_family`, `wp_font_face`)

**Actions to log:**
- Font family installed
- Font family deleted
- Font activated/deactivated
- Font face (variant) added/removed

**Special considerations:**
- WordPress 6.5+ feature
- Font files stored in `wp-content/uploads/fonts/`
- Should show font name and source

## Gaps and Recommendations

### Gap 1: FSE Post Types Get Generic Logging

**Issue:** FSE post types are logged by Post_Logger but with generic "Updated post" messages.

**Recommendation:** Create dedicated FSE logger(s) or enhance Post_Logger to:
- Use FSE-specific messages (e.g., "Updated template 'Single Post'" instead of "Updated wp_template")
- Parse and display meaningful change details
- Use appropriate terminology (template, pattern, etc.)

### Gap 2: Global Styles Need Special Handling

**Issue:** wp_global_styles changes are logged as generic post updates, but the JSON content changes aren't parsed.

**Recommendation:**
- Parse the JSON to detect specific style changes
- Show "Changed primary color from #xxx to #yyy"
- Group style changes meaningfully

### Gap 3: Template Reset Not Detected

**Issue:** When user resets a template to default, it may be deleted (removing user customizations), which is logged as deletion, but context is lost.

**Recommendation:**
- Detect reset-to-default actions
- Log as "Reset template to default" with template info

### Gap 4: Navigation Block Menus Not Tracked Meaningfully

**Issue:** wp_navigation is logged by Post_Logger, but menu structure changes in block content aren't analyzed.

**Recommendation:**
- Parse navigation block content to detect menu item changes
- Show "Added/removed menu item 'About Us'"
- Track menu item reordering

### Gap 5: No Font Management Logging

**Issue:** wp_font_family and wp_font_face are new in WordPress 6.5 and may not be handled well.

**Recommendation:**
- Create dedicated Font Logger or extend Theme_Logger
- Show font names and sources
- Track font activation status

## Implementation Priority

| Feature | Priority | Effort | Notes |
|---------|----------|--------|-------|
| Better template/template_part labels | High | Low | Simple message improvements |
| Synced Patterns sync status | High | Medium | Track meta changes |
| Global Styles parsing | Medium | High | Complex JSON parsing |
| Navigation menu structure | Medium | High | Block content parsing |
| Font management | Low | Medium | WordPress 6.5+ only |
| Template reset detection | Low | Medium | Edge case handling |

## Relevant WordPress Hooks

### Post-based hooks (already used by Post_Logger)

```php
// These already work for FSE post types
save_post_{$post_type}
rest_after_insert_{$post_type}
transition_post_status
delete_post
```

### FSE-specific hooks

```php
// Global Styles
update_option('theme_mods_{$theme}')

// Template/Template Part REST
rest_insert_wp_template
rest_insert_wp_template_part
rest_insert_wp_global_styles

// Font management (6.5+)
wp_register_font_family
wp_unregister_font_family
```

## Related Resources

- [Site Editing Templates - Block Editor Handbook](https://developer.wordpress.org/block-editor/explanations/architecture/full-site-editing-templates/)
- [Global Settings & Styles - Block Editor Handbook](https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/)
- [Synced Patterns Documentation](https://wordpress.org/documentation/article/reusable-blocks/)
- [Navigation Block Documentation](https://make.wordpress.org/core/2022/01/07/the-new-navigation-block/)

## Manual Testing Checklist

Use this section to test what is currently logged and record findings.

**Test environment:**
- WordPress version: ___
- Theme (must be block theme): ___
- Simple History version: ___

### Templates (`wp_template`)

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Create template | Appearance > Editor > Templates > Add New | [ ] Yes [ ] No | | |
| Edit existing template | Edit any template (e.g., Single Posts), add/remove blocks, Save | [ ] Yes [ ] No | | |
| Edit template title | Change template title in sidebar | [ ] Yes [ ] No | | |
| Delete template | Templates > select > Delete | [ ] Yes [ ] No | | |
| Reset template to default | Templates > select > Reset (three-dot menu) | [ ] Yes [ ] No | | |
| Duplicate template | Templates > select > Duplicate | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

### Template Parts (`wp_template_part`)

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Create template part | Patterns > Template Parts > Add New | [ ] Yes [ ] No | | |
| Edit header | Edit the Header template part | [ ] Yes [ ] No | | |
| Edit footer | Edit the Footer template part | [ ] Yes [ ] No | | |
| Delete template part | Template Parts > select > Delete | [ ] Yes [ ] No | | |
| Reset template part | Template Parts > select > Reset | [ ] Yes [ ] No | | |
| Change area assignment | Change from header to footer area | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

### Global Styles (`wp_global_styles`)

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Change site background color | Styles > Colors > Background | [ ] Yes [ ] No | | |
| Change text color | Styles > Colors > Text | [ ] Yes [ ] No | | |
| Change link color | Styles > Colors > Links | [ ] Yes [ ] No | | |
| Change heading color | Styles > Colors > Headings | [ ] Yes [ ] No | | |
| Change button colors | Styles > Colors > Buttons | [ ] Yes [ ] No | | |
| Change font family | Styles > Typography > Text > Font | [ ] Yes [ ] No | | |
| Change font size | Styles > Typography > Text > Size | [ ] Yes [ ] No | | |
| Change heading font | Styles > Typography > Headings > Font | [ ] Yes [ ] No | | |
| Change spacing/padding | Styles > Layout > Padding | [ ] Yes [ ] No | | |
| Change content width | Styles > Layout > Content width | [ ] Yes [ ] No | | |
| Reset all styles | Styles > Reset to defaults | [ ] Yes [ ] No | | |
| Save style variation | Styles > Browse styles > Save | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

### Synced Patterns (`wp_block`)

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Create synced pattern | Select blocks > Create Pattern > Synced | [ ] Yes [ ] No | | |
| Create non-synced pattern | Select blocks > Create Pattern > Not synced | [ ] Yes [ ] No | | |
| Edit synced pattern | Patterns > My Patterns > Edit synced pattern | [ ] Yes [ ] No | | |
| Edit non-synced pattern | Patterns > My Patterns > Edit non-synced | [ ] Yes [ ] No | | |
| Delete pattern | Patterns > My Patterns > Delete | [ ] Yes [ ] No | | |
| Convert synced to non-synced | Pattern > Detach | [ ] Yes [ ] No | | |
| Rename pattern | Change pattern name | [ ] Yes [ ] No | | |
| Add pattern to category | Assign pattern to category | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

### Navigation Menus (`wp_navigation`)

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Create navigation menu | Add Navigation block > Create new menu | [ ] Yes [ ] No | | |
| Add menu item | Navigation > Add link/page | [ ] Yes [ ] No | | |
| Remove menu item | Navigation > Remove item | [ ] Yes [ ] No | | |
| Reorder menu items | Drag to reorder | [ ] Yes [ ] No | | |
| Create submenu | Drag item under another | [ ] Yes [ ] No | | |
| Rename menu | Change navigation block name | [ ] Yes [ ] No | | |
| Delete navigation menu | Delete navigation post | [ ] Yes [ ] No | | |
| Edit menu item label | Change link text | [ ] Yes [ ] No | | |
| Edit menu item URL | Change link URL | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

### Custom Fonts (`wp_font_family`, `wp_font_face`) - WordPress 6.5+

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Install Google Font | Styles > Typography > Manage Fonts > Install | [ ] Yes [ ] No | | |
| Upload custom font | Manage Fonts > Upload | [ ] Yes [ ] No | | |
| Remove/delete font | Manage Fonts > Remove | [ ] Yes [ ] No | | |
| Activate font variant | Enable specific weight/style | [ ] Yes [ ] No | | |
| Deactivate font variant | Disable specific weight/style | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

### Additional FSE Actions

| Action | How to Test | Logged? | Log Message | Notes |
|--------|-------------|---------|-------------|-------|
| Export templates | Tools > Export | [ ] Yes [ ] No | | |
| Import templates | Tools > Import | [ ] Yes [ ] No | | |
| Switch style variation | Styles > Browse styles > Apply | [ ] Yes [ ] No | | |
| Edit block styles per-block | Styles > Blocks > [Block] | [ ] Yes [ ] No | | |

**Findings:**
```
(Record observations here)
```

---

## Testing Notes

### How to Access Site Editor

1. **Direct URL:** `/wp-admin/site-editor.php`
2. **Menu:** Appearance > Editor (requires block theme)
3. **Templates:** Appearance > Editor > Templates
4. **Template Parts:** Appearance > Editor > Patterns > Template Parts
5. **Styles:** Appearance > Editor > Styles (paintbrush icon)
6. **Patterns:** Appearance > Editor > Patterns

### Block Themes for Testing

If you need a block theme for testing:
- Twenty Twenty-Four (default)
- Twenty Twenty-Three
- Twenty Twenty-Two

### Viewing Simple History Log

After each action, check:
1. Simple History dashboard widget
2. Dashboard > Simple History
3. WP-CLI: `wp simple-history list`

### What to Record

For each test, note:
- **Was it logged?** Yes/No
- **Log message:** The actual message shown
- **Post type shown:** Does it say "wp_template" or "template"?
- **Details shown:** What details are visible when expanding?
- **Missing info:** What information would be useful but isn't shown?

## Summary

Simple History currently logs FSE post types via the generic Post_Logger, but the logging could be significantly improved with:

1. **FSE-specific messages** - Better labels and terminology
2. **Parsed change details** - Show what specifically changed in styles/templates
3. **Context preservation** - Track template types, sync status, font names
4. **Specialized loggers** - Dedicated FSE logger or enhanced Theme_Logger

The most impactful improvement would be enhancing post type labels and messages for FSE content, which requires relatively low effort but provides significant UX improvement.
