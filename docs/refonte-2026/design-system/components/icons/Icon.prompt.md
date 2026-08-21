One-line: the Motoblouz house glyph set — use it for the 19 glyphs it has, and Remix Icon for everything the workshop needs that it does not.

```jsx
<Icon name="SearchLine" size={20} />
<Icon name="CheckFill" size={16} style={{ color: 'var(--pk-success-line)' }} />
<Icon name="ArrowRightSLine" size={18} style={{ color: 'var(--pk-ink-muted)' }} />
```

- The glyph paints in `currentColor`: set `color` on the icon or inherit it from the row. Never hard-code a fill.
- `aria-hidden` is already set. An icon-only control needs its own `aria-label`.
- Sizes follow the app scale: 13 in a planning block, 15–18 in a list row, 20 in the rail, 22–24 for a confirmation.
- `IconNames` is the full list of glyph names, in source order — useful for a picker or a specimen.
- **Coverage.** The set is e-commerce shaped: arrows, check, close, search, star, basket, add/subtract. It has no calendar, no bay, no motorcycle, no invoice, no key — those come from Remix Icon (`ri-*` classes) exactly as the Paddock prototype does.
