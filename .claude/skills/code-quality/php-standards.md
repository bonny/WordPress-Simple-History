# PHP Code Style Standards

## Core Requirements

- **PHP 7.4+** compatibility required
- **WordPress Coding Standards** (configured in phpcs.xml.dist)
- **No `mb_*` string functions** - Use standard string functions only
- **Short array syntax** - Use `[]` not `array()`
- **Prefixed hooks** - Always prefix with `sh`, `simplehistory`, or `simple_history`

## PHP Block Formatting

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

```php
<fieldset class="sh-RadioOptions">
    <?php
    foreach ( $formatters as $formatter_slug => $formatter ) {
        ?>
        <label class="sh-RadioOption">
            <input
                type="radio"
                name="<?php echo esc_attr( $option_name ); ?>[formatter]"
                value="<?php echo esc_attr( $formatter_slug ); ?>"
                <?php checked( $selected_formatted_slug, $formatter_slug ); ?>
            />

            <?php echo esc_html( $formatter->get_name() ); ?>

            <span class="sh-RadioOptionDescription description">
                <?php echo esc_html( $formatter->get_description() ); ?>
            </span>
        </label>
        <?php
    }
    ?>
</fieldset>

```

❌ **Incorrect:**

```php
<?php $count = $importer->get_count(); ?>
```

```php
<?php if ( $condition ) { ?>
```

```php
<fieldset class="sh-RadioOptions">
    <?php foreach ( $formatters as $formatter_slug => $formatter ) { ?>
        <label class="sh-RadioOption">
            <input
                type="radio"
                name="<?php echo esc_attr( $option_name ); ?>[formatter]"
                value="<?php echo esc_attr( $formatter_slug ); ?>"
                <?php checked( $selected_formatted_slug, $formatter_slug ); ?>
            />
            <?php echo esc_html( $formatter->get_name() ); ?>
            <span class="sh-RadioOptionDescription description">
                <?php echo esc_html( $formatter->get_description() ); ?>
            </span>
        </label>
    <?php } ?>
</fieldset>
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

### Null Coalescing Operator

Prefer the null coalescing operator `??` over `isset()` ternaries for default values. It's cleaner, more readable, and available since PHP 7.0.

✅ **Correct (null coalescing):**

```php
$user_id    = $data['user_id'] ?? 0;
$post_title = $context['post_title'] ?? __( 'Unknown', 'simple-history' );
$settings   = get_option( 'my_settings' ) ?? [];
```

❌ **Incorrect (isset ternary):**

```php
$user_id    = isset( $data['user_id'] ) ? $data['user_id'] : 0;
$post_title = isset( $context['post_title'] ) ? $context['post_title'] : __( 'Unknown', 'simple-history' );
$settings   = isset( get_option( 'my_settings' ) ) ? get_option( 'my_settings' ) : [];
```

**Note:** Use `isset()` when you need to check existence without providing a default, or when checking object properties that may not exist.

### Readable Code Over Clever Code

Code is read more often than it's written. Optimize for the human reader, not for line count. Break complex expressions into named intermediate variables that explain what's happening.

✅ **Correct (readable):**

```php
$previous_count = (int) ( $settings['consecutive_errors'] ?? 0 );
$count          = $previous_count + 1;

$is_over_limit    = $count >= self::MAX_ERRORS;
$not_yet_disabled = ! $this->is_auto_disabled();
$should_disable   = $is_over_limit && $not_yet_disabled;

if ( $should_disable ) {
    $this->disable_channel();
}
```

❌ **Incorrect (clever one-liner):**

```php
$count = ( (int) ( $settings['consecutive_errors'] ?? 0 ) ) + 1;

if ( $count >= self::MAX_ERRORS && ! $this->is_auto_disabled() ) {
    $this->disable_channel();
}
```

This refactoring pattern is called **"Introduce Explaining Variable"** - it doesn't change behavior but makes the code self-documenting.

### Defensive Programming

WordPress plugins receive data from hooks, filters, and other plugins. Never assume data is valid or complete - other code may have modified variables or hooks may not pass all expected arguments.

**Validate hook arguments - make optional params have defaults:**

```php
// WordPress hooks may not pass all expected arguments.
// The delete_comment hook only added the $comment param in WP 6.2.
public function on_delete_comment( $comment_id, $comment = null ) {
    if ( empty( $comment_id ) ) {
        return;
    }

    // Fetch comment if not provided (backwards compatibility).
    if ( ! $comment ) {
        $comment = get_comment( $comment_id );
    }

    // Now safe to use $comment...
}
```

**Always verify data before using:**

```php
$post = get_post( $post_id );
if ( ! $post ) {
    return;
}

$user = get_userdata( $user_id );
if ( ! $user ) {
    return;
}
```

**Check types when uncertain:**

```php
// Data from external sources or filters may have unexpected types.
if ( ! is_array( $items ) ) {
    return;
}

if ( ! is_string( $value ) || empty( $value ) ) {
    return;
}
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
- [ ] Complex expressions broken into explaining variables
- [ ] All output is escaped
- [ ] All hooks/functions are prefixed
- [ ] Text domain is `simple-history`
- [ ] No `mb_*` functions used
- [ ] Hook callbacks handle missing/optional arguments
- [ ] Data is validated before use
- [ ] No duplicated logic (check for existing methods before writing new ones)
- [ ] Shared logic uses `public static` methods in a central location
