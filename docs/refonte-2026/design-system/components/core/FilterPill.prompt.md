One-line: the row of state filters above the planning grid — each pill names a state and how many appointments are in it.

```jsx
<FilterPill label="À réceptionner" count={5} selected />
<FilterPill label="Sans pont" count={1} dashed />
<FilterPill label="Jeudi 21 · 3 créneaux libres" icon="ri-calendar-check-line" tone="warning" />
```

- Selected = black fill. Never both selected and `tone="warning"`.
- `dashed` is reserved for anomalies (an appointment with no bay).
