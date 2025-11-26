# PHP Code Style Standards

## Core Requirements

- **PHP 7.4+** compatibility required
- **WordPress Coding Standards** (configured in phpcs.xml.dist)
- **No `mb_*` string functions** - Use standard string functions only
- **Short array syntax** - Use `[]` not `array()`
- **Prefixed hooks** - Always prefix with `sh`, `simplehistory`, or `simple_history`
- **Multi-line PHP blocks** - Never write single-line PHP blocks in templates

## PHP Block Formatting

**Never write single-line PHP blocks.** Always use multi-line format with opening and closing tags on separate lines.

✅ **Correct:**

```php
<?php
$count = $importer->get_count();
?>
```

```php
<?php
if ( $condition ) {
    do_something();
}
?>
```

❌ **Incorrect:**

```php
<?php $count = $importer->get_count(); ?>
```

```php
<?php if ( $condition ) { ?>
```

## Control Structures

**Always use curly brace syntax for control structures.** Do not use alternative syntax (colon + end statements).

✅ **Correct:**

```php
foreach ( $post_types as $post_type ) {
    // ...
}

if ( $condition ) {
    // ...
}

while ( $items ) {
    // ...
}
```

❌ **Incorrect:**

```php
foreach ( $post_types as $post_type ) :
    // ...
endforeach;

if ( $condition ) :
    // ...
endif;

while ( $items ) :
    // ...
endwhile;
```

## Code Style Patterns

### Happy Path Last

Handle errors and edge cases first, then process the success case last. This reduces nesting and improves readability.

✅ **Correct:**

```php
function process_user( $user_id ) {
    $user = get_user( $user_id );

    if ( ! $user ) {
        return null;
    }

    if ( ! $user->isActive() ) {
        return null;
    }

    if ( ! $user->hasPermission( 'edit' ) ) {
        return null;
    }

    // Process active user with permissions...
    return process_active_user( $user );
}
```

❌ **Incorrect (nested conditions):**

```php
function process_user( $user_id ) {
    $user = get_user( $user_id );

    if ( $user ) {
        if ( $user->isActive() ) {
            if ( $user->hasPermission( 'edit' ) ) {
                // Process active user with permissions...
                return process_active_user( $user );
            }
        }
    }

    return null;
}
```

### Avoid Else - Use Early Returns

Use early returns instead of else statements to reduce nesting and improve code clarity.

✅ **Correct:**

```php
function get_user_status( $user ) {
    if ( ! $user ) {
        return 'invalid';
    }

    if ( $user->is_banned ) {
        return 'banned';
    }

    if ( ! $user->is_active ) {
        return 'inactive';
    }

    return 'active';
}
```

❌ **Incorrect (using else):**

```php
function get_user_status( $user ) {
    if ( ! $user ) {
        return 'invalid';
    } else {
        if ( $user->is_banned ) {
            return 'banned';
        } else {
            if ( ! $user->is_active ) {
                return 'inactive';
            } else {
                return 'active';
            }
        }
    }
}
```

### Separate Conditions

Use multiple separate if statements instead of compound conditions for better readability.

✅ **Correct:**

```php
if ( ! $user ) {
    return false;
}

if ( ! $user->isActive() ) {
    return false;
}

if ( ! $user->hasRole( 'admin' ) ) {
    return false;
}

return true;
```

❌ **Incorrect (compound condition):**

```php
if ( ! $user || ! $user->isActive() || ! $user->hasRole( 'admin' ) ) {
    return false;
}

return true;
```

### Always Use Curly Brackets

Even for single-statement blocks, always use curly brackets.

✅ **Correct:**

```php
if ( $condition ) {
    do_something();
}

foreach ( $items as $item ) {
    process_item( $item );
}
```

❌ **Incorrect:**

```php
if ( $condition )
    do_something();

foreach ( $items as $item )
    process_item( $item );
```

### Ternary Operators

Use multi-line format for ternary operators unless they are very short.

✅ **Short ternary (acceptable on one line):**

```php
$name = $isFoo ? 'foo' : 'bar';
$status = $user ? 'active' : 'inactive';
```

✅ **Multi-line ternary (preferred for longer expressions):**

```php
$result = $object instanceof Model ?
    $object->name :
    'A default value';

$display = $is_premium_user ?
    $this->get_premium_features() :
    $this->get_free_features();
```

✅ **Ternary instead of if/else:**

```php
$condition
    ? $this->doSomething()
    : $this->doSomethingElse();
```

## WordPress-Specific Standards

### Always Escape Output

```php
// Escaping HTML
echo esc_html( $user_name );

// Escaping attributes
echo '<div class="' . esc_attr( $class_name ) . '">';

// Escaping URLs
echo '<a href="' . esc_url( $link ) . '">';

// Escaping JavaScript
echo '<script>var name = "' . esc_js( $name ) . '";</script>';
```

### Prefix Everything

All functions, classes, hooks, and constants must use the project prefix:

```php
// Functions
function simple_history_get_events() { }
function sh_format_date() { }

// Hooks
do_action( 'simple_history_after_log' );
apply_filters( 'simple_history_event_output', $output );

// Constants
define( 'SIMPLE_HISTORY_VERSION', '1.0.0' );
```

### Text Domain

Always use the `simple-history` text domain:

```php
__( 'Event logged', 'simple-history' );
_e( 'Simple History', 'simple-history' );
_n( '%s event', '%s events', $count, 'simple-history' );
```

## Summary Checklist

When writing PHP code, ensure:

- [ ] PHP 7.4+ compatible (no PHP 8+ only features)
- [ ] Using curly braces for all control structures
- [ ] Using short array syntax `[]`
- [ ] Happy path comes last
- [ ] Early returns instead of else
- [ ] All output is escaped
- [ ] All hooks/functions are prefixed
- [ ] Text domain is `simple-history`
- [ ] No `mb_*` functions used
