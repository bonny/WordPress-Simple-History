# Design Principles

Project-specific guidance on DRY, YAGNI, and code organization.

## DRY - Don't Repeat Yourself

Extract shared logic when you have **actual** duplication (3+ occurrences). But don't preemptively create abstractions.

### When to Refactor for DRY

1. **Same logic in 3+ places** - Time to extract
2. **Factory methods** (e.g., `get_sender()`) - Should be in one place
3. **Configuration data** (e.g., presets, type definitions) - Single source of truth
4. **Type labels/mappings** - Centralize to avoid sync issues

### How to Refactor

```php
// BEFORE: Duplicated in REST controller, CLI command, and module
private function get_sender( string $type ) {
    switch ( $type ) {
        case 'email': return new Email_Sender();
        case 'slack': return new Slack_Sender();
    }
}

// AFTER: Single public static method in the main module
public static function get_sender( string $type ): ?Sender {
    // ... implementation
}

// Other classes call:
$sender = Main_Module::get_sender( $type );
```

### Checklist When Adding New Code

- [ ] Does similar logic exist elsewhere? Search before writing.
- [ ] Will multiple classes need this? Make it `public static` in a central location.
- [ ] Is this configuration/mapping data? Put it in one place.

## YAGNI - You Aren't Gonna Need It

Don't implement functionality until it's actually needed. Avoid:

- Creating abstractions for hypothetical future use cases
- Building helper functions for one-time operations
- Adding configurability "just in case"
- Designing for requirements that don't exist yet

**Together**: DRY says extract when you have real duplication. YAGNI says wait until you actually need it. Three similar lines of code is often better than a premature abstraction.

## Readable Code - Code Should Read Like Prose

Good code should be self-documenting. When you read a function, its intent should be clear without needing comments.

### Techniques

**1. Extract well-named methods:**

```php
// BEFORE: What does this do?
foreach ( $preset_settings as $preset_id => $settings ) {
    if ( ! empty( $settings['enabled'] ) && ! empty( $settings['destinations'] ) ) {
        $enabled_rules[] = [ 'preset' => $preset_id, 'destinations' => $settings['destinations'] ];
    }
}

// AFTER: Intent is clear from method name
$enabled_rules = $this->get_enabled_rules( $preset_settings, $custom_rules );
```

**2. Use array destructuring:**

```php
// Clean and expressive
[ $destinations, $preset_settings, $custom_rules ] = $this->get_alert_options();
```

**3. Structure methods as a story:**

```php
public function process_logged_event( $context, $data, $logger ) {
    [ $destinations, $preset_settings, $custom_rules ] = $this->get_alert_options();

    if ( empty( $destinations ) ) {
        return;
    }

    $enabled_rules = $this->get_enabled_rules( $preset_settings, $custom_rules );

    if ( empty( $enabled_rules ) ) {
        return;
    }

    foreach ( $enabled_rules as $rule ) {
        if ( $this->rule_matches_event( $rule, $context, $data ) ) {
            $this->send_alerts( $rule, $context, $data, $destinations );
        }
    }
}
// Reads like: "Get options. If no destinations, stop. Get enabled rules.
// If none, stop. For each rule that matches, send alerts."
```

**4. Method names describe "what", not "how":**

- `get_enabled_rules()` not `loop_and_filter_rules()`
- `send_alerts()` not `iterate_destinations_and_post()`

## Proactive DRY Review

When creating new classes (CLI commands, REST controllers, etc.) that work with existing functionality:

1. **Check existing classes** for methods that could be shared
2. **Look for these patterns** that indicate duplication:
   - Factory methods (`get_sender()`, `get_formatter()`)
   - Configuration getters (`get_presets()`, `get_types()`)
   - Label/mapping functions (`get_type_label()`, `get_status_text()`)
3. **Refactor existing code** if needed - make private methods public static
4. **Use the shared method** instead of copying code
