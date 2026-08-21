# Front atelier — 1440 × 900

Le logiciel utilisé par le personnel. Recréation des écrans du prototype (`Paddock Refonte.dc.html`), composée à partir des composants de ce design system — rien n’est réimplémenté sur place.

| Fichier | Écran | Source |
| --- | --- | --- |
| `index.html` | Coquille : rail + en-tête + routage entre tous les écrans | tour 1a (modèle de navigation retenu) |
| `StatScreen.jsx` | Stat › Atelier : 4 mesures, file à traiter, mécaniciens, état des ponts | tour 1a |
| `StatPeriodScreen.jsx` | Stat › Période (comparée à l’an dernier) et Stat › Analyse (temps vendu / temps passé) | tour 33a, 33b |
| `NewBookingScreen.jsx` | Prise de RDV au comptoir : client et moto, prestations filtrées, créneau recommandé | tour 2 (11130) |
| `PlanningScreen.jsx` | Planning par pont + panneau de réception + panneau de restitution | tour 10a, 10b |
| `AppointmentPanels.jsx` | Détail d’un RDV, et reporter / annuler avec le créneau libéré chiffré | tour 36a, 36b |
| `ReceptionScreen.jsx` | Réception du matin : une ligne par moto attendue, EDL et PDF sur la ligne de l’action | tour 2b |
| `BaysScreen.jsx` | Ponts & Méca : 4 onglets, 6 cartes de pont pilotables (activation, mécanicien, programme) | tour 2c |
| `ClientsScreen.jsx` | Clients : bandeau de stats, recherche dans l’entête du tableau, liste dense | tour 2d |
| `ClientDetailScreen.jsx` | Fiche client : encours, moto à l’atelier, historique fusionné, notes internes | tour 23a |
| `BikeDetailScreen.jsx` | Fiche moto : carnet par organe, rappel constructeur, passages au kilométrage | tour 24a |
| `QuotesListScreen.jsx` | Devis : liste triée par ancienneté, relance en masse, panneau de refus | tour 35a, 35b |
| `QuoteScreen.jsx` | Composer un devis : packs, marge par ligne, recherche pièce inline, envoi | tour 25a |
| `InvoicesScreen.jsx` | Factures : impayés en tête, échéance dépassée traitée comme une erreur | tour 2 (3b) |
| `StockScreen.jsx` | Stock : jauge stock / seuil dans la ligne, rupture qui nomme l’OR bloqué | tour 2 (3c) |
| `AdminScreen.jsx` | Administration : nav propre groupée Atelier / Personnes / Documents / Système | tours 16, 17, 20, 26, 27 |
| `ConfigScreen.jsx` | Configuration : taux horaire, acompte, seuils, colonne « ce que ça change » | tour 27a |
| `UsersScreen.jsx` | Utilisateurs et accès : comptes, rôle dit en peut faire / ne peut pas | tour 16a |
| `NotificationsScreen.jsx` | Notifications : messages du parcours, éditeur en phrase, aperçu du SMS reçu | tour 20a |
| `AuditScreen.jsx` | Journal d’audit : actions sensibles en rouge, détail avant / après | tour 26a |
| `DocTemplatesScreen.jsx` | Modèles de documents : clauses, aperçu A4, publication bloquée | tour 17a |
| `HoursScreen.jsx` | Horaires et fermetures : une ligne par jour, capacité recalculée, RDV hors horaires à trancher avant enregistrement | tour 14a |
| `TeamScreen.jsx` | Disponibilité de l’équipe : charge mécanicien × jour, absence en hachure, RDV laissés sans mécanicien | tour 15 |
| `ServicesScreen.jsx` | Prestations : catalogue par famille, main d’œuvre, pièces incluses, marge, forfaits mal calibrés | tour 37a |
| `HandoverScreen.jsx` | Restitution : relecture des travaux, état d’entrée / sortie, facture et encaissement | tour 9a |
| `PaperDocs.jsx` | Les documents papier à 452 px : OR, état des lieux, facture | tour 18a, 18b, 18c |
| `BenchScreen.jsx` | Poste mécanicien, thème sombre, cibles 56 px | tour 45b + règles de pointage |
| `ExplorerScreen.jsx` | Stat › Explorer : facettes cumulées, question en clair, résultat actionnable | tour 43a |
| `data.jsx`, `data2.jsx` | Les données de démonstration, isolées des écrans | — |

**Navigation.** Le rail 64 px et la nav 224 px groupée par métier sont **un seul contrôle à deux états** : le bouton en pied de rail déplie, celui en pied de nav replie. La sélection ne bouge pas d’un état à l’autre. La colonne « À traiter » suit l’utilisateur : dépliée sur Réception, en rail de compteurs sur Ponts et Clients, absente là où l’écran possède déjà sa surface droite (planning, Explorer).

**Parcours cliquable.** Stat → la tuile « Charge du jour » ouvre le planning. Réception → « Démarrer le check-in » ouvre le planning avec le panneau de réception. Dans le planning, la case de 08:30 ouvre la réception, celle de 15:30 la restitution ; « Détail du RDV de 08:30 » dans l’en-tête ouvre le panneau de rendez-vous, d’où l’on passe au report. Clients → « Fiche client » ouvre Nadia Belkacem, dont la Tracer 9 ouvre la fiche moto. Devis → DV-2447 ouvre le composer, « Enregistrer un refus » ouvre le panneau de refus. Restitution → « Encaisser et restituer » revient au planning ; les trois cadres pointillés de sortie se remplissent au clic et l’avertissement devient un accusé. Prise de RDV → « Créer le rendez-vous » revient au planning. Administration ouvre son propre shell — Horaires (fermer le mercredi à 17:00 et trancher le sort des 2 RDV), Disponibilité équipe (retirer / reposer l’absence de Thomas B.), Prestations (les trois forfaits calibrés différemment) — « Retour à l’atelier » en sort. L’icône de contraste bascule sur le poste d’atelier ; « Revenir au bureau » en sort. La loupe en bas du rail ouvre Explorer.

**Ce qui reste hors du kit.** Le compagnon VO (`ui_kits/vo/`) et les e-mails clients (`ui_kits/emails/`) ont leur propre dossier. Restent dehors : le catalogue réseau recopié (tour 2), le Cockpit SRC, le Suivi Live et Travaux compl. — l’app les a, le prototype les fond dans d’autres écrans — et le document de restitution en A4 — le prototype le décrit mais n’en donne pas le rendu, l’onglet le dit plutôt que de l’inventer. Les entrées de nav sans écran restent visibles mais ne mènent nulle part plutôt que de mener à une approximation — c’est la règle 7 appliquée au kit lui-même.

Les onglets Mécaniciens / Temps par type / Absences de Ponts & Méca existent dans l’app mais ne sont pas dessinés dans le prototype : l’écran le dit à la place de les inventer.
