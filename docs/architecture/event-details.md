# Event Details System Architecture

Simple History's Event Details system provides a powerful, flexible, and extensible way to format and display event information in both HTML and JSON formats. This system allows logger authors to create rich, detailed views of events with consistent styling and behavior across different output formats.

The main idea is to not require to manually fetch context values in most cases. Instead, the system automatically reads `_prev` and `_new` values from the context table, allowing for a more streamlined and efficient implementation.

Also the usage of these classes makes it possible to create both HTML and JSON output with the same code.

## Overview

The Event Details system is designed to handle complex event data with multiple values, comparisons, and custom formatting requirements. It supports:

-   **Automatic context reading** - The system automatically reads `_prev` and `_new` values from the context table, eliminating manual data fetching
-   **Multiple output formats** (HTML for web display, JSON for API consumption)
-   **Flexible data sources** (context keys, manual values, computed values)
-   **Various display formats** (inline, table, diff table, raw HTML)
-   **Extensible formatters** (custom HTML and JSON output)
-   **Responsive design** (automatically adapts to different screen sizes)

### Automatic Context Value Reading

One of the most powerful features of the Event Details system is its ability to automatically read previous and new values from the context table. When you create an `Event_Details_Item` with context keys, the system will:

1. **Automatically fetch values** from the context data passed to the container
2. **Handle multiple naming conventions**:
    - `key_new` and `key_prev` suffix format
    - Separate keys for new and previous values
    - Single key for current value only
3. **Detect changes** by comparing previous and new values
4. **Apply appropriate formatting** based on whether values changed

This eliminates the need for manual context value retrieval in most cases.

## Core Components

The event details system consists of several key components that work together to provide a comprehensive solution for handling event details.

### 1. Container Interface

The base interface that all event detail containers must implement:

```php
interface Event_Details_Container_Interface {
    public function to_html();    // Convert to HTML format
    public function to_json();    // Convert to JSON format
    public function __toString(); // String representation
}
```

### 2. Container Classes

#### Event_Details_Container

The main container class that manages groups of event details:

```php
class Event_Details_Container implements Event_Details_Container_Interface {
    public array $groups;
    protected array $context;

    public function add_group($group);
    public function add_groups($groups);
    public function add_items($items, $group_title = null);
    public function set_context($context);
}
```

Key features:

-   Manages multiple event detail groups
-   Handles context data
-   Provides fluent interface
-   Supports both HTML and JSON output

#### Event_Details_Simple_Container

A lightweight container for simple event details:

```php
class Event_Details_Simple_Container implements Event_Details_Container_Interface {
    private $html;

    public function __construct($html = '');
    public function to_html();
    public function to_json();
}
```

Use cases:

-   Legacy event detail functions
-   Simple string-based details
-   Fallback container

### 3. Groups and Items

#### Event_Details_Group

Represents a collection of related event details:

```php
class Event_Details_Group {
    public array $items = [];
    public Event_Details_Group_Formatter $formatter;
    public ?string $title;

    public function add_items($items);
    public function add_item($item);
    public function set_formatter($formatter);
    public function set_title($title);
}
```

#### Event_Details_Item

Represents a single event detail with flexible value handling:

```php
class Event_Details_Item {
    public ?string $name;         // Human readable label
    public ?string $slug_new;     // Context key for new/current value
    public ?string $slug_prev;    // Context key for previous value
    public ?string $new_value;    // Manually set new value
    public ?string $prev_value;   // Manually set previous value
    public ?bool $is_changed;     // Whether value changed
    public ?bool $is_added;       // Whether value was added
    public ?bool $is_removed;     // Whether value was removed

    // Constructor supports multiple formats:
    public function __construct($slug_or_slugs = null, $name = null);
}
```

**Constructor Parameter Formats:**

1. **Single context key**: `new Event_Details_Item('plugin_version', 'Plugin Version')`
    - Automatically reads `plugin_version` from context
2. **Array with new/prev keys**: `new Event_Details_Item(['title_new', 'title_prev'], 'Title')`
    - Automatically reads both `title_new` and `title_prev` from context
3. **Array with auto suffix**: `new Event_Details_Item(['title'], 'Title')`
    - Automatically looks for `title_new` and `title_prev` in context
4. **No context key**: `new Event_Details_Item(null, 'Label')` (use with `set_new_value()`)
    - Bypasses automatic reading, requires manual value setting

**Key Features:**

-   **Automatic Context Reading:** When context keys are provided, the Event Details system automatically fetches the corresponding values from the context data when the container is rendered.
-   **Smart Item Preservation:** Items with custom formatters (like RAW formatters) are always preserved, even if their context keys don't exist or have empty values.
-   **Empty Item Cleanup:** Items without custom formatters that have no new_value or prev_value are automatically removed to keep displays clean.

**Fluent Interface Methods:**

```php
$item = (new Event_Details_Item(null, 'Status'))
    ->set_new_value('Active')
    ->set_prev_value('Inactive')
    ->set_formatter(new Custom_Formatter());
```

### 4. Formatters

#### Group Formatters

Control how groups of items are displayed:

```php
abstract class Event_Details_Group_Formatter {
    abstract public function to_html($group);
    abstract public function to_json($group);
}
```

**Available Group Formatters:**

1. **`Event_Details_Group_Table_Formatter`** (Default)

    - Displays items in a two-column table (label | value)
    - Returns empty string if group has no items (no empty tables)
    - Best for: Settings changes, configuration updates, metadata

2. **`Event_Details_Group_Inline_Formatter`**

    - Displays items as inline text with separators
    - Best for: Short lists, simple status changes, compact displays

3. **`Event_Details_Group_Diff_Table_Formatter`**
    - Shows before/after values with color-coded differences
    - Returns empty string if group has no items (no empty tables)
    - Best for: Content changes, file modifications, text comparisons

#### Item Formatters

Control individual item output:

```php
abstract class Event_Details_Item_Formatter {
    abstract public function to_html();
    abstract public function to_json();
}
```

**Available Item Formatters:**

1. **`Event_Details_Item_Default_Formatter`**

    - Standard item display with automatic value handling
    - Handles single values, before/after comparisons

2. **`Event_Details_Item_RAW_Formatter`**

    - Complete custom HTML and JSON output control
    - Always outputs content regardless of context key existence
    - Items with RAW formatters are never removed during empty item cleanup
    - Best for: Custom layouts, complex data structures, static content

3. **`Event_Details_Item_Table_Row_RAW_Formatter`**
    - RAW formatter that maintains table row structure
    - Best for: Custom content within table layouts

**RAW Formatter Usage:**

```php
$formatter = new Event_Details_Item_Table_Row_RAW_Formatter();
$formatter->set_html_output('<strong>Custom HTML</strong> with <em>formatting</em>');
$formatter->set_json_output([
    'type' => 'custom',
    'data' => 'Raw json output'
]);

$item = (new Event_Details_Item(null, 'Custom Item'))
    ->set_formatter($formatter);
```

## Frontend Integration

The event details system integrates with the frontend through React components:

1. `EventDetails` - Main display component
2. `Event` - Container component
3. `EventInfoModal` - Detailed view modal

## Complete Real-World Examples

These examples are based on actual Simple History loggers and demonstrate practical usage patterns.

### 1. Settings Changes Logger

For logging WordPress settings modifications:

```php
public function get_log_row_details_output($row) {
    $event_details_group = new Event_Details_Group();
    $event_details_group->set_title(__('Changed Settings', 'simple-history'));

    // Add items using the new/prev context format
    // The Event Details system will AUTOMATICALLY read the values from context!
    $event_details_group->add_items([
        new Event_Details_Item(
            ['show_on_dashboard'],  // Automatically looks for show_on_dashboard_new/prev in context
            __('Show on dashboard', 'simple-history')
        ),
        new Event_Details_Item(
            ['show_as_page'],       // Automatically looks for show_as_page_new/prev
            __('Show as a page', 'simple-history')
        ),
        new Event_Details_Item(
            ['pager_size'],         // Automatically looks for pager_size_new/prev
            __('Items on page', 'simple-history')
        ),
        new Event_Details_Item(
            ['enable_rss_feed'],    // Automatically looks for enable_rss_feed_new/prev
            __('RSS feed enabled', 'simple-history')
        )
    ]);

    return $event_details_group;
}
```

**Expected Context Data (automatically read by the system):**

```php
$context = [
    'show_on_dashboard_prev' => '0',
    'show_on_dashboard_new' => '1',
    'show_as_page_prev' => '0',
    'show_as_page_new' => '1',
    'pager_size_prev' => '50',
    'pager_size_new' => '25',
    'enable_rss_feed_prev' => '0',
    'enable_rss_feed_new' => '1'
];
```

**Note:** The Event Details system automatically fetches these values from the context when the container is rendered. You don't need to manually retrieve them!

### 2. Plugin Updates Logger

For logging plugin installations and updates:

```php
public function get_log_row_details_output($row) {
    $context = $row->context;

    // Create multiple groups for different types of information
    $plugin_info_group = (new Event_Details_Group())
        ->set_title(__('Plugin Information', 'simple-history'))
        ->add_items([
            new Event_Details_Item('plugin_name', __('Plugin name', 'simple-history')),
            new Event_Details_Item('plugin_version', __('Available version', 'simple-history')),
            new Event_Details_Item('plugin_current_version', __('Installed version', 'simple-history')),
            new Event_Details_Item('plugin_author', __('Author', 'simple-history'))
        ]);

    // Custom formatter for plugin description with HTML content
    $description_formatter = new Event_Details_Item_Table_Row_RAW_Formatter();
    $description_formatter->set_html_output(
        '<div class="plugin-description">' . wp_kses_post($context['plugin_description']) . '</div>'
    );

    $plugin_info_group->add_item(
        (new Event_Details_Item(null, __('Description', 'simple-history')))
            ->set_formatter($description_formatter)
    );

    return $plugin_info_group;
}
```

### 3. Post Changes Logger with Diff

For showing content changes with visual differences:

```php
public function get_log_row_details_output($row) {
    // Create a group specifically for content changes using diff formatter
    $content_changes_group = (new Event_Details_Group())
        ->set_title(__('Content Changes', 'simple-history'))
        ->set_formatter(new Event_Details_Group_Diff_Table_Formatter())
        ->add_items([
            new Event_Details_Item(
                ['post_new_post_title', 'post_prev_post_title'],
                __('Post title', 'simple-history')
            ),
            new Event_Details_Item(
                ['post_new_post_content', 'post_prev_post_content'],
                __('Post content', 'simple-history')
            )
        ]);

    // Separate group for metadata without diff formatting
    $metadata_group = (new Event_Details_Group())
        ->set_title(__('Metadata', 'simple-history'))
        ->add_items([
            new Event_Details_Item('post_type', __('Post type', 'simple-history')),
            new Event_Details_Item(
                ['post_new_thumb_title', 'post_prev_thumb_title'],
                __('Featured image', 'simple-history')
            )
        ]);

    // Combine groups in a container
    return new Event_Details_Container(
        [$content_changes_group, $metadata_group],
        $row->context
    );
}
```

### 4. User Profile Changes

For tracking user account modifications:

```php
public function get_log_row_details_output($row) {
    $user_changes_group = (new Event_Details_Group())
        ->set_title(__('User Profile Changes', 'simple-history'))
        ->add_items([
            new Event_Details_Item('edited_user_login', __('Username', 'simple-history')),
            new Event_Details_Item('edited_user_email', __('Email', 'simple-history')),
            new Event_Details_Item(
                ['user_new_nickname', 'user_prev_nickname'],
                __('Nickname', 'simple-history')
            ),
            new Event_Details_Item(
                ['user_new_user_url', 'user_prev_user_url'],
                __('Website', 'simple-history')
            ),
            new Event_Details_Item(
                ['user_new_description', 'user_prev_description'],
                __('Bio', 'simple-history')
            )
        ]);

    return $user_changes_group;
}
```

### 5. Files Integrity Logger (Without Context Keys)

For displaying security-related file modifications:

```php
public function get_log_row_details_output($row) {
    $context = $row->context;

    if (empty($context['file_details'])) {
        return null;
    }

    // Decode JSON-stored file details
    $file_details = json_decode($context['file_details']);
    if (!is_array($file_details)) {
        return null;
    }

    $files_group = (new Event_Details_Group())
        ->set_title(__('Modified Core Files', 'simple-history'));

    // Limit to 5 files to prevent huge log entries
    $limited_files = array_slice($file_details, 0, 5);

    foreach ($limited_files as $file_data) {
        $status_text = match($file_data->issue) {
            'modified' => __('Hash mismatch', 'simple-history'),
            'missing' => __('File missing', 'simple-history'),
            'unreadable' => __('File unreadable', 'simple-history'),
            default => esc_html($file_data->issue)
        };

        // Create item without context key, manually set values
        $files_group->add_item(
            (new Event_Details_Item(null, $file_data->file))
                ->set_new_value($status_text)
        );
    }

    // Add summary if more files exist
    if (count($file_details) > 5) {
        $remaining = count($file_details) - 5;
        $files_group->add_item(
            (new Event_Details_Item(null, __('Additional files', 'simple-history')))
                ->set_new_value(
                    sprintf(_n('%d more file', '%d more files', $remaining, 'simple-history'), $remaining)
                )
        );
    }

    return $files_group;
}
```

### 6. Mixed Formatters and Complex Layout

Combining multiple formatters for rich displays:

```php
public function get_log_row_details_output($row) {
    // Inline group for quick summary
    $summary_group = (new Event_Details_Group())
        ->set_formatter(new Event_Details_Group_Inline_Formatter())
        ->set_title(__('Quick Summary', 'simple-history'))
        ->add_items([
            new Event_Details_Item('image_size', __('File size', 'simple-history')),
            new Event_Details_Item('image_format', __('Format', 'simple-history')),
            new Event_Details_Item('image_dimensions', __('Dimensions', 'simple-history'))
        ]);

    // Table group for detailed information
    $details_group = (new Event_Details_Group())
        ->set_title(__('Detailed Information', 'simple-history'))
        ->add_items([
            new Event_Details_Item('upload_path', __('Upload path', 'simple-history')),
            new Event_Details_Item('file_mime_type', __('MIME type', 'simple-history')),
            new Event_Details_Item('file_permissions', __('Permissions', 'simple-history'))
        ]);

    // Custom RAW output for special content
    $custom_formatter = new Event_Details_Item_RAW_Formatter();
    $custom_formatter->set_html_output(
        '<div class="media-preview">' .
        '<img src="' . esc_url($row->context['thumbnail_url']) . '" style="max-width: 150px;" />' .
        '</div>'
    );

    $details_group->add_item(
        (new Event_Details_Item(null, __('Preview', 'simple-history')))
            ->set_formatter($custom_formatter)
    );

    return new Event_Details_Container(
        [$summary_group, $details_group],
        $row->context
    );
}
```

## Usage Examples

### 1. Simple Container

```php
$container = new Event_Details_Simple_Container('<p>Basic event details</p>');
echo $container->to_html();
```

### 2. Complex Container with Groups and Formatters

```php
// Create items for the group
$event_group = [
    new Event_Details_Item(
        ['show_on_dashboard'],
        __('Show on dashboard', 'simple-history')
    ),
    new Event_Details_Item(
        ['show_as_page'],
        __('Show as a page', 'simple-history')
    ),
    new Event_Details_Item(
        ['pager_size'],
        __('Items on page', 'simple-history')
    )
];

// Create a group with inline formatter
$event_details_group_inline = new Event_Details_Group();
$event_details_group_inline->set_formatter(new Event_Details_Group_Inline_Formatter());
$event_details_group_inline->add_items($event_group);
$event_details_group_inline->set_title(__('Inline group with changes', 'simple-history'));

// Add group to container
$container = new Event_Details_Container();
$container->add_group($event_details_group_inline);
```

### 3. Using Raw Formatters

```php
// Create a raw formatter with custom HTML and JSON output
$item_raw_formatter = new Event_Details_Item_Table_Row_RAW_Formatter();
$item_raw_formatter->set_html_output('This is some <strong>RAW HTML</strong> output');
$item_raw_formatter->set_json_output([
    'raw_row_1' => 'Raw json row 1',
    'raw_row_2' => 'Raw json row 2'
]);

// Create item with raw formatter
$raw_item = new Event_Details_Item('version', 'Version');
$raw_item->set_formatter($item_raw_formatter);
```

### 4. Context-Based Details with Multiple Value Formats

```php
$container = new Event_Details_Container();
$container->set_context([
    // New/Previous format with _new/_prev suffix
    'show_on_dashboard_prev' => '0',
    'show_on_dashboard_new' => '1',

    // Direct value format
    'plugin_name' => 'Plugin Dependencies',
    'plugin_version' => '1.14.3',

    // Separate keys for values
    'post_prev_post_title' => 'About the company',
    'post_new_post_title' => 'About us'
]);

// Add items that use different context formats
// The Event Details system AUTOMATICALLY reads these values from the context!
$container->add_items([
    // Using new/prev suffix format - automatically reads show_on_dashboard_new and show_on_dashboard_prev
    new Event_Details_Item(
        ['show_on_dashboard'],
        'Show on dashboard'
    ),

    // Using direct value - automatically reads plugin_name from context
    new Event_Details_Item(
        'plugin_name',
        'Plugin name'
    ),

    // Using separate keys - automatically reads both values from context
    new Event_Details_Item(
        ['post_new_post_title', 'post_prev_post_title'],
        'Post title'
    )
]);
```

**Important:** Notice how we never manually fetch values from `$context`. The Event Details system handles this automatically when the container is rendered!

### 5. Working with Different Group Formatters

```php
// Create items
$items = [
    new Event_Details_Item('title', 'Page title'),
    new Event_Details_Item('content', 'Page content')
];

// Inline formatter (items in a paragraph)
$inline_group = new Event_Details_Group();
$inline_group->set_formatter(new Event_Details_Group_Inline_Formatter());
$inline_group->add_items($items);

// Table formatter (items in table rows)
$table_group = new Event_Details_Group();
$table_group->set_formatter(new Event_Details_Group_Table_Formatter());
$table_group->add_items($items);

// Diff table formatter (shows differences)
$diff_group = new Event_Details_Group();
$diff_group->set_formatter(new Event_Details_Group_Diff_Table_Formatter());
$diff_group->add_items($items);

// Add all groups to container
$container = new Event_Details_Container();
$container->add_groups([$inline_group, $table_group, $diff_group]);
```

## Best Practices

### 1. Choose the Right Approach

**Use `Event_Details_Group`** (most common):

-   When you have structured data with labels and values
-   For settings changes, metadata updates, configuration changes
-   When you want consistent styling with other loggers

**Use `Event_Details_Simple_Container`** (legacy support):

-   When migrating old HTML-based detail methods
-   For simple string output that doesn't need structured formatting

**Use `Event_Details_Container`** (advanced):

-   When you need multiple groups with different formatters
-   For complex layouts with mixed content types

### 2. Context Key Naming Conventions

The Event Details system automatically reads values from the context based on your key naming:

**For Before/After Changes:**

```php
// Preferred: Use array format for automatic _new/_prev detection
new Event_Details_Item(['setting_name'], 'Setting Label')
// The system automatically looks for: setting_name_new and setting_name_prev in context

// Alternative: Specify keys explicitly
new Event_Details_Item(['new_key', 'old_key'], 'Label')
// The system automatically reads new_key and old_key from context
```

**For Single Values:**

```php
// Direct context key - automatically reads from context
new Event_Details_Item('plugin_version', 'Version')
// The system automatically reads the 'plugin_version' value from context

// No context key (manual values) - bypasses automatic reading
new Event_Details_Item(null, 'Status')->set_new_value('Active')
// Manual values are used instead of reading from context
```

**Remember:** The automatic context reading is a core feature that eliminates boilerplate code for fetching values!

### 3. Group Organization

**Logical Grouping:**

-   Group related information together
-   Separate different types of changes (content vs metadata)
-   Use descriptive group titles

**Performance Considerations:**

-   Limit items per group (recommend 5-10 items max)
-   For large datasets, implement pagination or truncation
-   Consider using summary items for overflow (e.g., "5 more files...")

### 4. Formatter Selection Guide

**Default Table Formatter:**

-   Best for: Settings, metadata, simple key-value pairs
-   Automatically used if no formatter specified

**Inline Formatter:**

-   Best for: Short lists, status indicators, compact displays
-   Use when horizontal space is limited

**Diff Table Formatter:**

-   Best for: Content changes, text modifications, comparisons
-   Automatically highlights differences between old and new values

**RAW Formatters:**

-   Best for: Custom layouts, media previews, complex HTML
-   Always escape user input in custom HTML
-   Provide meaningful JSON alternatives
-   Always preserved regardless of context key existence

### 5. Security and Data Handling

**Always Escape Output:**

```php
// Good: Escaped user input in RAW formatter
$formatter->set_html_output('<strong>' . esc_html($user_input) . '</strong>');

// Bad: Unescaped user input
$formatter->set_html_output('<strong>' . $user_input . '</strong>');
```

**Validate Context Data:**

```php
// Check data exists and is expected type
if (empty($context['file_details']) || !is_string($context['file_details'])) {
    return null;
}

$file_details = json_decode($context['file_details']);
if (!is_array($file_details)) {
    return null;
}
```

**Limit Data Size:**

```php
// Prevent huge log entries
$limited_files = array_slice($file_details, 0, 5);

// Add summary for remaining items
if (count($file_details) > 5) {
    // Add "X more items" summary
}
```

### 6. Translation and Internationalization

**Always Use Translation Functions:**

```php
// Good
new Event_Details_Item('setting', __('Setting Name', 'textdomain'))

// Bad
new Event_Details_Item('setting', 'Setting Name')
```

**Handle Pluralization:**

```php
sprintf(
    _n('%d file', '%d files', $count, 'textdomain'),
    $count
)
```

### 7. Return Value Guidelines

**Return Types:**

-   `Event_Details_Group` - Most common, single group
-   `Event_Details_Container` - Multiple groups
-   `null` - When no details should be shown

**Early Returns:**

```php
public function get_log_row_details_output($row) {
    $context = $row->context;
    $message_key = $context['_message_key'] ?? null;

    // Early return for unsupported message types
    if ($message_key !== 'supported_message_type') {
        return null;
    }

    // Early return for missing data
    if (empty($context['required_field'])) {
        return null;
    }

    // Continue with implementation...
}
```

### 8. Empty Item and Table Handling

**Automatic Empty Item Removal:**
The Event Details system automatically removes items that have no meaningful content to display:

-   Items without custom formatters that have empty `new_value` and `prev_value` are removed
-   Items with custom formatters (RAW formatters) are always preserved
-   This prevents cluttered displays with empty rows

**Empty Table Prevention:**
Table formatters (both regular and diff) automatically prevent empty table generation:

-   If a group has no items after cleanup, table formatters return empty string
-   This prevents empty `<table>` elements from appearing in HTML output
-   Maintains clean, semantic HTML structure

**Example of Protected vs. Removed Items:**

```php
$group = new Event_Details_Group();

// This item will be removed if context doesn't contain 'missing_key'
$group->add_item(new Event_Details_Item('missing_key', 'Missing Field'));

// This item will ALWAYS be preserved, regardless of context
$raw_formatter = new Event_Details_Item_RAW_Formatter();
$raw_formatter->set_html_output('<strong>Always visible</strong>');
$group->add_item(
    (new Event_Details_Item('any_key', 'RAW Content'))
        ->set_formatter($raw_formatter)
);

// If only the RAW item remains, table will still render
// If no items remain, table formatter returns empty string
```

### 9. Testing Your Event Details

**Manual Testing:**

-   Test with various data sizes (empty, single item, many items)
-   Verify HTML output renders correctly
-   Check JSON output for API compatibility
-   Test with long text content and special characters

**Context Data Testing:**

-   Test with missing context keys
-   Test with malformed JSON data
-   Verify graceful fallbacks for missing formatters

## Performance Considerations

-   Lazy loading of formatters
-   Efficient context handling
-   Minimal object creation
-   Reusable components

## Security

-   Proper data sanitization in formatters
-   Context data validation
-   Safe HTML output
-   JSON data filtering
