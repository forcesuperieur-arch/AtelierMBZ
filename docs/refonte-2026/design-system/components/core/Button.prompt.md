One-line: the action button — use `tone="accent"` for the single action that closes a moment (réceptionner, encaisser, réserver), `neutral` for everything else.

```jsx
<Button variant="primary" tone="accent" size="medium" fullWidth>Réceptionner et placer sur le pont 2</Button>
<Button variant="secondary" tone="neutral">Client absent</Button>
<Button variant="tertiary" startIcon="ri-add-line">Nouveau RDV</Button>
```

- One accent button per screen region. Two yellow buttons side by side means neither is the action.
- `size="medium"` (52px) on workshop screens, `small` (41px) in the top bar.
- `shape="square"` when the button sits in a row of segmented controls; `rounded` otherwise.
