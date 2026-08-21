One-line: the 52px header — context on the left (workshop, day, view), search and actions on the right.

```jsx
<TopBar workshop="Atelier Principal" live="Données en direct · 14:32">
  <div style={{ flex: 1 }} />
  <SearchField />
  <IconAction icon="ri-notification-3-line" label="Notifications" badge={4} />
  <Button variant="primary" tone="accent" size="small" startIcon="ri-add-line">Nouveau RDV</Button>
</TopBar>
```

- One search field per app, and it searches all three keys at once.
- The accent button in the header is always "Nouveau RDV" — the only creation entry.
