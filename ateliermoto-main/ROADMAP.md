# Roadmap Soft (Version test)

## Objectif

Lancer une version **testable rapidement**, sans facturation, avec un parcours atelier solide :

- planning RDV fiable,
- gestion des statuts claire,
- espace mecanicien fluide,
- zero conflit de planning,
- historique client exploitable.

## Decision figee (cadre Soft)

- La **version Soft est l'etat actuel de l'application**.
- On **conserve integralement la base metier existante** (clients, vehicules, historiques, affectations, ateliers) sans refonte de schema pour cette phase.
- Les evolutions Soft se font en priorite sur le **parcours operationnel** (RDV, planning, espace mecanicien), pas sur la structure de donnees.
- **Aucune destruction de l'existant** : pas de suppression de fonctionnalite backend deja en place durant la phase Soft.
- Si une fonction n'est pas utilisee dans le parcours Soft, on la **masque cote frontend** (navigation/boutons/actions), mais on conserve le code backend.

---

## Problematique metier de reference (figee)

Les points ci-dessous representent le **constat avant ce logiciel** (situation initiale terrain).
Objectif transverse de la version Soft : **fluidifier le parcours actuel dans l'outil**, pour Client, SRC, Administratif et Mecanicien, avec reduction maximale des erreurs.

### SRC

- Remontee SC sur non visibilite RDV -> **OK**
- Autonomie SRC sur prise de RDV -> **OK**

### Client

- Prise de RDV longue, rappel shop aleatoire -> **OK**
- Prise de RDV magasin obligatoire -> **Non valide**
- Confirmation de RDV (date, montant, infos importantes) -> **Non valide**
- Rappel de RDV avant echeance -> **A faire**
- Acompte -> **A faire**
- OR non systematique (risque de conflit) -> **A traiter dans le flux soft**
- Pas d'appel client en fin d'intervention -> **A faire**

### Atelier

- Reception chronophage -> **A reduire**
- Prix RDV calcule manuellement selon moto -> **A automatiser**
- Calendrier AlloPneus utilise comme prise de RDV -> **A cadrer / remplacer dans le flux cible**
- En cas de probleme pendant intervention, difficultes de joindre le client pour validation -> **A fluidifier**
- Trop de charge administrative -> **A reduire**
- Risque client procedurier -> **A couvrir par tracabilite (statuts, OR, motifs, historique)**

### Priorisation immediate (Roadmap Soft)

1. Fiabiliser le cycle RDV -> OR -> intervention (sans facturation)
2. Fluidifier les etapes critiques deja dans le logiciel (moins de clics, moins d'ambiguite, statuts clairs)
3. Rendre la confirmation client explicite et tracable
4. Standardiser la fin d'intervention avec action de contact client

---

## Perimetre IN / OUT

### IN (priorite absolue)

- RDV (creation, edition, assignation, deplacement, annulation)
- Planning atelier (jour + semaine)
- Espace mecanicien (a faire, en cours, termine, checkup)
- Statuts operationnels (`reserve`, `confirme`, `reception`, `en_cours`, `termine`, `annule`, `non_presente`)
- Historique client lisible (y compris annules/non presentes)
- RBAC utile au flux principal (visualisation + edition RDV)

### OUT (desactive pour la version soft)

- Facturation (devis/facture/encaissement)
- KPIs financiers
- Fonctions non critiques au cycle RDV -> intervention

---

## Etat de depart (deja acquis)

- Multi-atelier et RBAC largement en place.
- Assignation technicien/pont stabilisee.
- Historique preserve sur RDV passes.
- Annulation avec motif modal deja presente.
- Statut `reserve` et blocage de conflit deja engages.

---

## Lot 1 — Gel fonctionnel “sans facturation”

**But :** eviter toute confusion en environnement test.

- Masquer les entrees facturation (menus, boutons, badges financiers).
- Bloquer les endpoints de facturation cote UI (pas d’appel).
- Verifier que les ecrans Dashboard/OR restent operationnels sans CA.
- Conserver OR PDF uniquement en logique atelier (pas finance).

**Definition of Done**

- Aucun bouton “Facturer/Encaisser” visible pour les profils test.
- Aucun plantage UI si les APIs facturation ne repondent pas.

---

## Lot 2 — Flux RDV “exploitation” verrouille

**But :** rendre le cycle RDV robuste et simple.

- Entree online/public en statut `reserve`.
- Confirmation manuelle -> `confirme`.
- **Gate reception obligatoire** avant lancement des travaux.
- En reception, afficher toutes les informations OR avant validation.
- Autoriser l'edition OR **avant signature** (correction infos + ajout prestations demande client).
- A validation reception + signature client : OR passe en **verrouille**.
- Apres signature : impossible d'editer infos vehicule et accords travaux.
- Apres signature : seules les informations technicien (execution/rapport) restent editables.
- Lancement travail autorise uniquement si `reception` faite **et** OR signe.
- Fin de flux : `reception` -> `en_cours`, puis `termine`.
- Annulation avec motif obligatoire.
- Option `non_presente` traitee comme statut explicite.
- Message clair pour chaque transition interdite.

**Definition of Done**

- Tous les statuts s’affichent correctement partout (planning, suivi, detail, historique).
- Le parcours complet d’un RDV est faisable sans contournement.
- Impossible de passer `en_cours` sans reception validee et OR signe.
- Verrouillage OR effectif apres signature (hors champs technicien).

---

## Lot 3 — Planning “zero conflit”

**But :** fiabilite operationnelle.

- Interdiction stricte des chevauchements pont/technicien.
- Validation serveur sur creation + update.
- Retour frontend lisible en drag&drop (alerte conflit).
- Recalcul coherent des creneaux dispos selon statuts actifs (`reserve` inclus).
- Verification des cas limites (deplacement meme heure, changement de duree, reassignment).

**Definition of Done**

- Impossible de sauver un RDV en conflit.
- Les utilisateurs comprennent immediatement pourquoi c’est bloque.

---

## Lot 4 — Espace mecanicien “production-ready”

**But :** execution terrain sans friction.

- Liste “A faire maintenant” propre (reserve/confirme/reception).
- Priorisation visuelle des retards.
- Actions rapides stables (demarrer, terminer, checkup).
- Persistance fiable du rapport technicien.
- Cohérence entre espace mecanicien et planning.

**Definition of Done**

- Un mecanicien peut faire sa journee complete depuis cet ecran.
- Aucune incoherence de statut entre ecrans.

---

## Lot 4 bis — Travaux complementaires (validation simple et tracable)

**But :** traiter les imprevus atelier sans casser le flux principal.

- Garder le mecanisme travaux supplementaires deja en place (pas de suppression backend).
- Cadrer un flux unique : `en_attente` -> `approuve` ou `refuse`.
- Rendre **obligatoire un OR complementaire** pour toute demande approuvee.
- Exiger **accord client signe** (signature) avant execution des travaux complementaires.
- Afficher clairement cote reception/SRC les demandes en attente de decision.
- Exiger une trace de decision (notes reception) sur refus et sur ecart notable.
- Integrer la decision dans l'historique RDV/client pour limiter le risque procedurier.
- Si non utilise dans le test Soft, conserver backend et masquer uniquement les entrees frontend secondaires.

**Definition of Done**

- Une demande complementaire peut etre creee, validee ou refusee sans ambiguite.
- Impossible de lancer les travaux complementaires sans OR complementaire signe.
- La decision reste visible dans le detail intervention et l'historique client.
- Aucun impact regressif sur le parcours principal RDV/planning/espace mecanicien.

---

## Lot 5 — Suivi client et memoire atelier

**But :** garder la trace operationnelle.

- Historique client affiche visites terminees + annulees + non presente.
- Motif annulation/non presentation visible dans le detail.
- Affichage resilient meme si mecanicien/pont ont ete desactives ensuite.

**Definition of Done**

- Le service client retrouve l’historique reel sans perte d’info.

---

## Lot 6 — Stabilisation pre-demo

**But :** pouvoir lancer la version test sans stress.

- Revue RBAC mini (qui voit quoi, qui edite quoi).
- Campagne de tests manuels guidee (parcours principal uniquement).
- Corrections des regressions bloquantes.
- Nettoyage UX (messages, labels, etats vides).

**Definition of Done**

- Parcours principal valide de bout en bout :
  RDV -> planning -> mecanicien -> cloture intervention.

---

## Checklist de recette (version soft)

- Creer un RDV (manuel) et un RDV online (`reserve`).
- Confirmer, assigner, deplacer sans conflit.
- Verifier blocage en cas de conflit pont/technicien.
- Passer en reception puis en cours puis termine.
- Annuler avec motif + marquer non presente.
- Relire historique client et details intervention.
- Verifier que la facturation n’apparait pas dans le parcours test.

---

## Bloc Go-Live Soft (chef de projet + responsable atelier)

### Criteres Go / No-Go

- 0 contournement possible des gates metier (reception + OR signe avant `en_cours`).
- 0 conflit planning enregistrable (pont/technicien).
- 100% des OR signes verrouilles (hors champs technicien).
- 100% des travaux complementaires approuves avec OR complementaire signe.
- Aucune action facturation visible dans le parcours Soft.

### Plan de recette terrain (pre-lancement)

- Executer une campagne de tests sur cas reels (minimum 20 dossiers).
- Couvrir obligatoirement : retard, no-show, annulation, reassignation, conflit planning, travaux complementaires.
- Valider le parcours multi-role : reception/SRC/mecanicien/admin.

### Runbook operationnel equipe

- Definir qui valide la reception.
- Definir qui fait signer le client.
- Definir qui autorise les travaux complementaires.
- Definir la procedure en cas de blocage (escalade interne + trace dans commentaire).

### Suivi post-lancement (2 semaines)

- Point quotidien incidents (bloquant/majeur/mineur).
- Correctifs rapides sur les points bloquants metier.
- Suivi de 4 KPI operationnels :
  - taux de RDV finalises,
  - taux de non presente,
  - nombre de conflits bloques,
  - delai moyen reception -> demarrage.

---

## Suite apres validation soft

Quand la version soft est stable :

1. Reintroduire la facturation progressivement (feature flag).
2. Brancher l’envoi email a la confirmation RDV.
3. Finaliser indicateurs business et pilotage avance.
