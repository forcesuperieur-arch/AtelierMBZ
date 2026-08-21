One-line: the permanent 64px left rail — the retained navigation model for Paddock (tour 1a), used on every workshop screen.

```jsx
<IconRail active="planning" user="JD" items={[
  { id: 'stat', label: 'Stat', icon: 'ri-bar-chart-2-line' },
  { id: 'planning', label: 'Planning', icon: 'ri-calendar-2-line' },
  { id: 'atelier', label: 'En atelier', icon: 'ri-hourglass-line', badge: 4 },
]} />
```

- The rail never expands on hover: icon positions must not move.
- A module the workshop does not use leaves the rail entirely — it never becomes a greyed entry.
