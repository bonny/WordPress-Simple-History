# CSS Standards

Simple History uses **SuitCSS** naming conventions with the `sh` prefix.

## Naming Convention: SuitCSS

SuitCSS is a component-based CSS methodology that provides clear, predictable class names.

### Basic Structure

```
sh-ComponentName
sh-ComponentName-subpart
sh-ComponentName--modifier
sh-ComponentName.is-stateOfComponent
```

## Prefix: `sh`

All CSS classes must be prefixed with `sh` (short for Simple History).

## Component Names

Components use PascalCase (UpperCamelCase) after the prefix.

### Examples

```css
/* Main components */
.sh-HelpSection { }
.sh-LogEntry { }
.sh-EventList { }
.sh-FilterBar { }
.sh-DatePicker { }

/* UI elements */
.sh-Button { }
.sh-Modal { }
.sh-Dropdown { }
.sh-Tooltip { }
```

## Subparts (Descendants)

Subparts are direct children or important parts of a component, connected with a single hyphen.

### Examples

```css
/* HelpSection component and its subparts */
.sh-HelpSection { }
.sh-HelpSection-title { }
.sh-HelpSection-content { }
.sh-HelpSection-footer { }

/* LogEntry component and its subparts */
.sh-LogEntry { }
.sh-LogEntry-header { }
.sh-LogEntry-author { }
.sh-LogEntry-timestamp { }
.sh-LogEntry-message { }
.sh-LogEntry-details { }

/* Button component and its subparts */
.sh-Button { }
.sh-Button-icon { }
.sh-Button-label { }
```

## Modifiers

Modifiers are variations of a component, connected with double hyphens.

### Examples

```css
/* Button variations */
.sh-Button--primary { }
.sh-Button--secondary { }
.sh-Button--danger { }
.sh-Button--large { }
.sh-Button--small { }

/* LogEntry variations */
.sh-LogEntry--highlighted { }
.sh-LogEntry--collapsed { }
.sh-LogEntry--premium { }
```

## State Classes

State classes use the `is-` prefix and are typically combined with component classes.

### Examples

```css
/* States that can be toggled */
.sh-Modal.is-open { }
.sh-Dropdown.is-expanded { }
.sh-LogEntry.is-selected { }
.sh-FilterBar.is-loading { }
```

## Utilities

Utility classes use lowercase and can be prefixed with `sh-u-`.

### Examples

```css
.sh-u-hidden { }
.sh-u-clearfix { }
.sh-u-textCenter { }
.sh-u-marginBottom { }
```

## Complete Examples

### Example 1: Help Section Component

```html
<div class="sh-HelpSection">
    <div class="sh-HelpSection-header">
        <h2 class="sh-HelpSection-title">Need Help?</h2>
    </div>
    <div class="sh-HelpSection-content">
        <p>Documentation and support information...</p>
    </div>
    <div class="sh-HelpSection-footer">
        <button class="sh-Button sh-Button--primary">
            <span class="sh-Button-label">Get Support</span>
        </button>
    </div>
</div>
```

### Example 2: Log Entry Component

```html
<article class="sh-LogEntry sh-LogEntry--highlighted">
    <header class="sh-LogEntry-header">
        <span class="sh-LogEntry-author">John Doe</span>
        <time class="sh-LogEntry-timestamp">2 hours ago</time>
    </header>
    <div class="sh-LogEntry-message">
        Updated post "Hello World"
    </div>
    <div class="sh-LogEntry-details">
        <button class="sh-Button sh-Button--small">
            View Details
        </button>
    </div>
</article>
```

### Example 3: Modal with State

```html
<div class="sh-Modal is-open">
    <div class="sh-Modal-overlay"></div>
    <div class="sh-Modal-dialog">
        <div class="sh-Modal-header">
            <h2 class="sh-Modal-title">Export Events</h2>
            <button class="sh-Modal-close">×</button>
        </div>
        <div class="sh-Modal-body">
            Modal content here...
        </div>
        <div class="sh-Modal-footer">
            <button class="sh-Button sh-Button--secondary">Cancel</button>
            <button class="sh-Button sh-Button--primary">Export</button>
        </div>
    </div>
</div>
```

## Rules Summary

1. **Always prefix** with `sh`
2. **Component names** use PascalCase: `sh-ComponentName`
3. **Subparts** use single hyphen: `sh-ComponentName-subpart`
4. **Modifiers** use double hyphen: `sh-ComponentName--modifier`
5. **State classes** use `is-` prefix: `.is-open`, `.is-selected`
6. **Be specific**: Use descriptive names that clarify purpose

## Benefits of SuitCSS

- **Scoped and predictable**: Easy to find related styles
- **Prevents collisions**: Prefix and naming convention reduce conflicts
- **Self-documenting**: Class names indicate component relationships
- **Maintainable**: Easy to refactor and reorganize components

## Anti-Patterns to Avoid

❌ **Don't use generic names without prefix:**
```css
.button { }          /* Too generic */
.header { }          /* Conflicts with HTML elements */
.container { }       /* Common naming collision */
```

❌ **Don't use underscores in component names:**
```css
.sh-log_entry { }    /* Wrong: use hyphen */
.sh-help_section { } /* Wrong: use PascalCase */
```

❌ **Don't mix naming conventions:**
```css
.sh-logEntry { }     /* Wrong: not PascalCase */
.sh-log-entry { }    /* Wrong: should be .sh-LogEntry */
```

✅ **Do use SuitCSS properly:**
```css
.sh-LogEntry { }
.sh-LogEntry-author { }
.sh-LogEntry--highlighted { }
```

## Resources

- [SuitCSS Documentation](https://suitcss.github.io/)
- [SuitCSS Naming Conventions](https://github.com/suitcss/suit/blob/master/doc/naming-conventions.md)
