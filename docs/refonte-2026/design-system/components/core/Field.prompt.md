One-line: a labelled input box for the kit — square and 52px on the customer front, `dense` and 38px inside a workshop panel.

```jsx
<Field label="Immatriculation" value="EF-771-GH" focused hint="Nous retrouvons la moto si elle est déjà passée chez nous." />
<Field label="Carburant" value="½ réservoir" endIcon="ri-arrow-down-s-line" dense />
<Field label="Code postal" value="5960" error="5 chiffres attendus." />
```

- An error names the expected value. `error` renders icon + text, never red alone.
