One-line: the queue reached zero — a green result carrying the hour the last item was handled, not a grey hole.

```jsx
<NothingToDo
  title="Plus rien en attente"
  description="Aucun devis sans réponse, aucun travail à valider, aucune pièce sous le seuil."
  lastHandledAt="14:18"
  actionLabel="Voir le planning de demain"
  onAction={ouvrirDemain}
/>
```

- A void that was earned is congratulated; it is not drawn as a lack. Hence the success trio (`--pk-success-surface` / `-line` / `-ink`) and never the dashed grey box of `EmptyState`.
- Enumerate what was cleared, in the words of the work: « aucun devis sans réponse, aucun travail à valider, aucune pièce sous le seuil ».
- `lastHandledAt` is not decoration — without the hour, a zero reads like a list that failed to load.
- One action, quiet, pointing at the next thing. Nothing accent here: there is no decision left to close.
