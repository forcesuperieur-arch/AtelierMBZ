One-line: the four measures of the moment at the top of Stat — each one a link to the list it counts.

```jsx
<KpiTile label="Ponts occupés" value="5" unit="/6" ratio={0.83} note="1 libre maintenant · 83 % de la capacité" />
<KpiTile label="À traiter" value="7" tone="error" note="dont 4 critiques" />
```

- Every tile is a link, and the destination arrives filtered on what the number said.
- Hover reveals the border. Never underline the number: a number must stay a number.
