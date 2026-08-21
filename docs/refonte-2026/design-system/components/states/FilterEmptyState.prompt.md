One-line: the list is empty because the filters hide it — say how many are on, and count what a single removal would bring back.

```jsx
<FilterEmptyState
  title="Aucun devis ne correspond"
  filterCount={3}
  suggestion={{ filter: 'Roubaix', count: 4, noun: 'devis critiques' }}
  onRemove={retirerFiltre}
  onClear={toutEffacer}
/>
```

- Renders `3 filtres sont actifs. En retirant « Roubaix », 4 devis critiques apparaîtraient.` The figure turns a guess into a decision, so the calling screen computes `suggestion` — never invent it.
- Never `EmptyState` here: a dashed empty box tells the user to import data he already owns.
- The accent button names the filter it drops, in full; « Tout effacer » stays quiet beside it, because clearing everything loses the work of filtering.
- Solid border and no illustration — nothing is missing, and the filter pills above stay visible so the user sees what he set.
