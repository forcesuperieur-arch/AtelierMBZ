One-line: the bay tile — the live occupancy of the workshop, six of them in a 2-column grid on Stat.

```jsx
<BayCard name="Pont 1" vehicle="Yamaha MT-09 · EX-421-QR" customer="Ludovic Renard" note="2 RDV restants aujourd'hui" />
<BayCard name="Pont 3" state="free" note="3 RDV restants aujourd'hui" />
```

- A free bay is a dashed outline with no fill: emptiness must read as emptiness.
- `state="down"` (red cap) still shows its remaining appointments — they have to be moved somewhere.
