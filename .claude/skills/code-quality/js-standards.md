# JavaScript Code Standards

## Block Formatting

Always use braces on separate lines for control structures, even for single statements:

```javascript
// Correct
if (element) {
	element.addEventListener('change', handler);
}

// Wrong
if (element) element.addEventListener('change', handler);
```

This applies to `if`, `else`, `for`, `while`, and `switch`.
