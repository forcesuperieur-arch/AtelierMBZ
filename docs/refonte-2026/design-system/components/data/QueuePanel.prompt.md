One-line: the « À traiter » column — present on every workshop screen, expanded where there is room and collapsed to a counter rail where there is not.

```jsx
<QueuePanel count={7} collapsed={narrow} onToggle={toggle} items={[
  { kind: 'Devis · 6 jours', icon: 'ri-draft-line', level: 'critical', count: 3,
    title: 'DV-2418 · Ludovic Renard', detail: 'La moto est réceptionnée, le devis non signé',
    actions: ['Faire signer sur place'] },
]} />
```

- Never omit it. A screen without the queue hides the work.
- The `kind` line carries the age or the deadline — that is what makes the item sortable by hand.
- Two actions maximum, and the first one is the one that resolves the item.
