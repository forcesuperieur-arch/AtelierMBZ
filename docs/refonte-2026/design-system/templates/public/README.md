# Front public — 390 × 844 et 900 px

Ce que voit un motard **sans compte**, avant et pendant l'intervention. Tours 50 et 53 du prototype.

| Fichier | Écrans | Source |
| --- | --- | --- |
| `PublicPages.jsx` | Landing (`LandingScreen`), mot de passe oublié (`PasswordResetScreen`), CGV versionnées (`TermsScreen`) | tour 53 |
| `BookingFlow.jsx` | Prise de RDV en ligne, 4 étapes cliquables : moto, prestation + prix, créneau, coordonnées | tour 50a |
| `TrackScreens.jsx` | Suivi par lien (`TrackScreen`), écran « prête » (`TrackReadyScreen`), mentions et confidentialité (`LegalScreen`) | tours 50b, 50c |

**Règles portées par ces écrans.** Le prix est annoncé avant le créneau, pas après. Les créneaux proposés sont ceux que le planning peut tenir. Le suivi n'a pas d'identifiant : le lien porte le jeton et expire quinze jours après la restitution. La demande de mot de passe répond la même chose que l'adresse existe ou non.

**À cliquer.** La prise de RDV va jusqu'à la confirmation puis se réarme. Sur le suivi, « J'accepte · 168 € » et « Non merci » modifient la frise, l'heure de restitution et l'estimation. Sur le mot de passe oublié, « Voir cet état » montre le lien expiré.

**Le SSO reste au personnel** (`ui_kits/atelier/login.card.html`) : côté client, e-mail et mot de passe.
