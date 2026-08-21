One-line: the full 224px navigation, grouped by trade — use it on screens that have the width; `IconRail` is the same nav collapsed.

```jsx
<SideNav active="clients" workshop="Atelier Principal" logo="../../assets/paddock-logo-favicon.svg"
  onCollapse={() => setCollapsed(true)}
  groups={[
    { label: 'Pilotage', items: [{ id: 'stat', label: 'Stat', icon: 'ri-bar-chart-2-line' }] },
    { label: 'Atelier', items: [{ id: 'travaux', label: 'Travaux compl.', icon: 'ri-hammer-line', badge: 2, badgeTone: 'error' }] },
  ]} />
```

- The two nav states are one control: the collapse button at the foot swaps to `IconRail`, and the rail's unfold button swaps back. Never ship only one.
- `badgeTone="error"` is for what immobilises a motorcycle (travaux complémentaires en attente). Everything else counts in grey.
- Group order follows the workshop's day: Pilotage, Atelier, Commerce, then Réglages.
