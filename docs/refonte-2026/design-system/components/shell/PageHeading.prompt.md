One-line: page title, 4px yellow underline, and the tab set that switches what the page is about.

```jsx
<PageHeading title="Stat" description="L'état de l'atelier maintenant et ce qu'il y a à traiter.">
  <PillTabs value="atelier" items={[
    { value: 'atelier', label: 'Atelier', count: 7 },
    { value: 'periode', label: 'Période' },
  ]} />
</PageHeading>
```

- The yellow bar is the only decoration a page header gets. No icon, no card, no gradient.
- Active tab: fill and colour. Never a weight change — it shifts the widths of the whole set.
