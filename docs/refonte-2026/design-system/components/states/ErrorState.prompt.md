One-line: the panel standing where a screen the server could not fill would be — the cause, what it did not cost, and the two ways on.

```jsx
<ErrorState
  icon="ri-cloud-off-line"
  title="Le planning n'a pas pu être chargé"
  description="Le serveur n'a pas répondu."
  consequence="Vos rendez-vous ne sont pas perdus : rien n'a été modifié."
  issueLabel="Voir la feuille du jour"
  code="PLN-503"
  failedAt="14:52"
/>
```

- Two sentences, two jobs: `description` is the cause, `consequence` is what it cost. The second one answers the question actually being asked, and it is usually "rien".
- `issueLabel` is the legitimate way out, and it names where it leads — « Voir la feuille du jour », never « Continuer ». Retry alone locks the workshop out of its own day.
- `code` and `failedAt` render as the one line read out to support. Leave them empty when there is nothing to quote — never put "une erreur est survenue" in their place.
