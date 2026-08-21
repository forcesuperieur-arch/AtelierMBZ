One-line: the customer-front service choice and the slot picker that follows it.

```jsx
<ServiceCard selected title="Révision 20 000 km" price="289 €"
  description="Huile, filtres, bougies, contrôle des 22 points constructeur. Immobilisation : une demi-journée."
  recommendation="Conseillée pour votre kilométrage" />
<SlotGrid selected="Mar 26 août 8 h 00" days={[
  { label: 'Lun 25 août', slots: [{ time: '8 h 00' }, { time: '8 h 30', full: true }] },
]} />
```

- The price is announced before the slot is chosen, never after.
- Slots shown are slots the planning can actually hold. A full slot says "complet" and stays on screen.
