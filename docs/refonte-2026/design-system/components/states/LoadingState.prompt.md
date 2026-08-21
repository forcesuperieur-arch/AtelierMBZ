One-line: the shape of the list that is on its way, drawn at the size it will take, so the page does not jump when the rows land.

```jsx
<LoadingState caption="Devis" columns={3} rows={4} />
<LoadingState title="Chargement des passages au kilométrage" columns={4} rows={6} compact />
```

- Never a spinner. Nothing in Paddock is allowed to spin or blink — draw the table that is coming instead.
- `columns` and `rows` must match the real table and its page size. A skeleton of the wrong size moves the layout twice instead of once.
- `caption` names the list in words, and `title` says what is loading for a screen reader. Grey bars carry no meaning on their own.
