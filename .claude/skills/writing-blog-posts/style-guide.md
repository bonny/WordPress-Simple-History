# Style Guide

Detailed writing guidelines for Simple History blog posts.

## Tone

- **Conversational but knowledgeable** - Write like explaining to a colleague
- **Helpful, not salesy** - Educate first, promote second
- **Confident but humble** - Share expertise without being condescending
- **Enthusiastic without hype** - Show genuine interest, avoid marketing speak

## Structure

### Paragraphs
- 2-4 sentences maximum
- One idea per paragraph
- Use line breaks generously

### Headings
- Descriptive and scannable
- Use H2 for main sections, H3 for subsections
- Front-load important words

### Lists
- Bullets for unordered items
- Numbers for sequential steps
- Keep items parallel in structure

## Voice Rules

### Do
- Use "you" to address readers
- Use contractions (it's, you'll, we've)
- Write in active voice
- Be specific with examples
- Explain technical terms when introduced

### Don't
- Use jargon without explanation
- Write walls of text
- Use passive voice unnecessarily
- Say "simply" or "just" (dismissive)
- Overuse exclamation marks

## Word Choices

### Prefer
| Instead of | Use |
|------------|-----|
| audit log | activity log |
| surveil | track, monitor |
| utilize | use |
| in order to | to |
| due to the fact that | because |

### Avoid These Phrases
- "In today's digital landscape"
- "Let's dive into"
- "Without further ado"
- "It goes without saying"
- "At the end of the day"
- "Game-changer"
- "Seamlessly"

## Code Examples

- Always explain what code does before showing it
- Use syntax highlighting
- Keep examples minimal but complete
- Add comments for non-obvious parts

```php
// Good: Minimal, explained
add_action( 'init', function() {
    // Log custom events to Simple History
    SimpleLogger()->info( 'User performed action' );
});
```

## Screenshots

- Crop to relevant area
- Add annotations for complex UI
- Use consistent browser/viewport
- Compress images appropriately

## Call-to-Actions

End posts with clear next steps:
- Try a feature
- Read related content
- Leave a comment
- Upgrade to premium (when relevant, not pushy)

## Premium Mentions

When mentioning premium features:
- Be factual, not salesy
- Clearly mark as premium
- Focus on value, not limitations
- Follow wordpress-org-compliance skill guidelines
