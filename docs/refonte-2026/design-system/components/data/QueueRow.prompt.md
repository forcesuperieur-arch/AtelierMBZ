One-line: a line of the to-handle queue — what it is, since when, how bad, how many.

```jsx
<QueueRow icon="ri-draft-line" level="critical" count={3}
  title="Devis en attente de validation client"
  detail="Le plus ancien : 6 jours · seuil 3 jours" />
```

- The severity is stated in words as well as colour, and the detail line always names the threshold that was crossed.
