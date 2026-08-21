One-line: the list that has never been filled — name the door the data arrives through, never the fact that there is none.

```jsx
<EmptyState
  icon="ri-group-line"
  title="Aucun client pour le moment"
  description="Un client se crée à la prise de rendez-vous, sans passer par ici. Vous pouvez aussi reprendre votre fichier existant."
  actionLabel="Importer un fichier clients"
  secondaryLabel="Créer à la main"
/>
```

- Here the data does not exist yet, so `description` names its origin. Write the consequence, not the category: "aucune donnée" tells the user nothing he did not already see.
- Two roads at most — the fast one as the accent action, the manual one beside it — and each label says the whole outcome, not a verb.
- Dashed border, left-aligned, no illustration: the dashes say "a place waiting to be filled"; a drawing says nothing and costs a load.
- If the rows exist and the filters hide them, this is the wrong template — use `FilterEmptyState`, which counts what a removal brings back.
