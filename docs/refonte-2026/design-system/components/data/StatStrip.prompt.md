One-line: the one-line stat band beside a page title, when the page's real subject is the list below it.

```jsx
<StatStrip items={[
  { label: 'Total clients', value: '1 284' },
  { label: 'Avec RDV', value: '742', suffix: '58 %' },
  { label: 'Conflits', value: '1', tone: 'error' },
]} />
```

- Use `KpiTile` instead when the numbers ARE the page (Stat) — tiles are cards and links; the strip is a frame.
- Three or four cells. Five is a dashboard, and a dashboard is a different page.
