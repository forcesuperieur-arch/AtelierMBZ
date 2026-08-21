One-line: the day grid by bay — the planning is the workstation, so it stays on screen while work happens in the right panel.

```jsx
<PlanningGrid hours={['08:00','09:00','10:00','11:00','12:00','14:00','15:00']}
  bays={[{ name: 'Pont 1', assignee: 'Karim M.' }, { name: 'Pont 3', assignee: 'Non affecté' }]}>
  <AppointmentBlock column={2} row={1} span={2} state="received" statusLabel="08:00 · réceptionnée" vehicle="MT-09 · Renard" detail="Révision 20 000" />
</PlanningGrid>
```

- The grid does not animate on a day change (0ms): it is read, not watched.
- Column index 1 is the hour gutter; appointments start at column 2.
