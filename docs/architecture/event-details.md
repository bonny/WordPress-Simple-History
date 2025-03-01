# Event Details System Architecture

Simple History's Event Details system provides a flexible and extensible way to format and display event information in both HTML and JSON formats. This document outlines the architecture and components of the event details system.

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
- Manages multiple event detail groups
- Handles context data
- Provides fluent interface
- Supports both HTML and JSON output

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
- Legacy event detail functions
- Simple string-based details
- Fallback container

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

Represents a single event detail:

```php
class Event_Details_Item {
    public ?string $name;
    public ?string $slug_new;
    public ?string $slug_prev;
    public ?string $new_value;
    public ?string $prev_value;
    public ?bool $is_changed;
    public ?bool $is_added;
    public ?bool $is_removed;
}
```

### 4. Formatters

#### Group Formatters

Base formatter class and implementations:

```php
abstract class Event_Details_Group_Formatter {
    abstract public function to_html($group);
    abstract public function to_json($group);
}

class Event_Details_Group_Inline_Formatter extends Event_Details_Group_Formatter {
    // Formats groups as inline lists
}
```

#### Item Formatters

Specialized formatters for items:

```php
class Event_Details_Item_RAW_Formatter extends Event_Details_Item_Formatter {
    public function to_html();
    public function to_json();
    public function set_html_output($html);
    public function set_json_output($json);
}
```

## Frontend Integration

The event details system integrates with the frontend through React components:

1. `EventDetails` - Main display component
2. `Event` - Container component
3. `EventInfoModal` - Detailed view modal

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
$container->add_items([
    // Using new/prev suffix format
    new Event_Details_Item(
        ['show_on_dashboard'],
        'Show on dashboard'
    ),
    
    // Using direct value
    new Event_Details_Item(
        'plugin_name',
        'Plugin name'
    ),
    
    // Using separate keys
    new Event_Details_Item(
        ['post_new_post_title', 'post_prev_post_title'],
        'Post title'
    )
]);
```

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

1. Use appropriate container type:
   - `Event_Details_Container` for complex details
   - `Event_Details_Simple_Container` for basic details

2. Implement custom formatters for specific needs

3. Always provide both HTML and JSON output

4. Use the context system for tracking changes

5. Leverage the fluent interface for cleaner code

6. Group related items together

## Performance Considerations

- Lazy loading of formatters
- Efficient context handling
- Minimal object creation
- Reusable components

## Security

- Proper data sanitization in formatters
- Context data validation
- Safe HTML output
- JSON data filtering 