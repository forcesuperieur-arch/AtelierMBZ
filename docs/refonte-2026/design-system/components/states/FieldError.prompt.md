One-line: the line under a refused entry — it names the expected value, where that value comes from, and when it was recorded.

```jsx
<FieldError
  message="Inférieur au dernier relevé connu : 24 180 km en mars 2026. Un compteur ne recule pas."
  issueLabel="Compteur remplacé"
/>
```

- The message carries the reference value with its origin and its date. « Valeur invalide » tells the receptionist nothing they can act on.
- `issueLabel` opens the case where the entry is right and the rule is wrong — a replaced odometer really does go backwards. Without it, the only way forward is a false number.
- Icon plus text, never red alone. Use `Field`'s own `error` prop when the message belongs inside the field block; this one stands under a field the kit did not draw.
