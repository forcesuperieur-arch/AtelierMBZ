One-line: the tracking timeline on the customer link — one line per event, the hour it happened, the next step left hollow.

```jsx
<StatusTimeline steps={[
  { state: 'done', title: 'Moto réceptionnée · 8 h 06', detail: 'État des lieux signé, 6 photos prises.' },
  { state: 'current', title: 'En attente de votre réponse · 15 h 24', detail: 'Disque arrière.' },
  { state: 'pending', title: 'Restitution', detail: 'À partir de 17 h, jusqu'à 19 h.' },
]} />
```

- The page answers "where is my motorcycle" in one line above this timeline; the timeline is the detail, not the answer.
