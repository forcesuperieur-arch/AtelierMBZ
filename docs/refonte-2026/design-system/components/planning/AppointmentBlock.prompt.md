One-line: an appointment in the grid — its state readable from the edge colour, the written status line and the glyph together.

```jsx
<AppointmentBlock column={3} row={1} span={3} state="open" icon="ri-inbox-line"
  statusLabel="08:30 · réception en cours" vehicle="Tracer 9 · Belkacem"
  detail="Révision + plaquettes" note="Panneau ouvert →" />
<AppointmentBlock column={5} row={4} span={2} state="conflict" icon="ri-error-warning-line"
  statusLabel="Conflit 11:00" vehicle="R1250 GS · Vasseur" detail="Chevauche 11:30" />
```

- `open` is the block whose panel is showing: 2px yellow frame, no left cap. Exactly one per grid.
- `unassigned` (dashed) means the appointment exists but has no bay — it must stay visible.
