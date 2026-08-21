One-line: the right-hand work panel — the workstation is never left, so reception, hand-back and appointment detail all happen here.

```jsx
<SidePanel icon="ri-inbox-line" title="Réception · 08:30" subtitle="Tracer 9 · GT-908-ZK · N. Belkacem"
  footer={<Button variant="primary" tone="accent" fullWidth>Réceptionner et placer sur le pont 2</Button>}>
  <PanelSection label="Travaux prévus">…</PanelSection>
  <PanelSection label="État des lieux d'entrée">…</PanelSection>
</SidePanel>
```

- Focus goes to the first field to fill on open, not the title. Escape closes and returns focus to the row it came from.
- The footer holds exactly one accent action; alternatives ("Client absent", "Reporter") sit under it as secondaries.
- Enter/exit at 180ms. Nothing else in the panel animates.
