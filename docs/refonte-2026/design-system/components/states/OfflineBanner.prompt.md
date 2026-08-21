One-line: the strip that says the network is gone and, before anything else, what the workshop can keep doing without it.

```jsx
<OfflineBanner
  offline
  since="2 min"
  stillPossible="pointer et à réceptionner"
  pending={3}
  pendingDetail="Pointages et 1 réception · signature capturée sur la tablette"
  unavailable="Envoi de SMS, encaissement, aperçu PDF" />
```

- Lead with `stillPossible`. A banner that names only the outage stops the work for nothing, when pointer and réceptionner both run offline.
- Collapsed by default: two lines are enough to keep going, and the detail is one press away instead of eating the height of a touch screen.
- `unavailable` names the acts, never the subsystem — "Envoi de SMS, encaissement, aperçu PDF", not "certaines fonctions".
- `offline={false}` renders nothing. The banner is the exception, so it must not leave a bar behind once the network is back.
