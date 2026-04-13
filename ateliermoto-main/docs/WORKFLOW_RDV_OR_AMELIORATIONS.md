# Workflow RDV / OR — schémas et pistes d'amélioration

> Cadrage fonctionnel du cycle `rendez-vous -> réception -> OR -> atelier -> restitution`.
> La facturation reste un volet back-office optionnel et ne pilote plus le workflow opérationnel courant.

## 1. Rôles des objets

- **RDV** : pilotage opérationnel (date, client, véhicule, statut, affectation, avancement)
- **OR** : preuve atelier et client (état du véhicule, dommages, photos, signature, travaux demandés, PDF archivé)
- **Facture** : volet comptable optionnel, découplé du pilotage atelier

---

## 2. Workflow global du RDV

```text
[Prise de RDV]
      |
      v
reserve / en_attente
      |
      | confirmer
      v
confirme
      |
      | réception véhicule
      | + kilométrage
      | + état visuel
      | + dommages / photos
      | + signature client
      v
reception
      |
      | démarrer intervention
      v
en_cours
      |
      | finir intervention / rapport technicien
      v
termine
      |
      | restitution client / clôture
      v
restitue

Sorties alternatives : annule / non_presente
```

---

## 3. Workflow OR (ordre de réparation)

```text
RDV confirmé
   |
   v
Ouverture de la réception
   |
   +--> création ou mise à jour de l'OR initial
   |     - numéro OR
   |     - kilométrage
   |     - état véhicule
   |     - dommages carrosserie
   |     - photos
   |     - observations
   |     - estimation / lignes de travaux
   |     - signature client
   |
   v
OR validé / signé
   |
   +--> PDF consultable
   +--> archivage en base
   +--> verrouillage des données de réception
   +--> autorise le démarrage atelier
   |
   v
Intervention atelier
   |
   +--> check-up technicien
   +--> travaux supplémentaires si besoin
   |
   v
OR finalisé
```

---

## 4. Règles métier actuellement visées

### Transitions sensibles
Les statuts suivants ne doivent pas être forcés via une simple modification générique du RDV :

- `reception`
- `en_cours`
- `termine`
- `restitue`
- `facture`
- `paye`

Ils doivent passer par les **actions métier dédiées**.

### Verrouillage OR
- Dès qu'un OR est **signé**, il devient **verrouillé**.
- Les informations de réception ne doivent plus être modifiables en brouillon libre.
- Le PDF doit refléter l'état archivé de l'OR.

### Permissions / RBAC
Les actions atelier sensibles sont pilotées par :

- `workflow.manage`
- `or.manage`
- `workshop.manage`

Le simple droit `rdv.edit` ne doit pas suffire à piloter tout le workflow atelier.

---

## 5. Vue simplifiée des permissions

| Action | Permission attendue |
|---|---|
| Modifier un RDV administratif | `rdv.edit` |
| Faire la réception / OR | `or.manage` ou `workflow.manage` |
| Démarrer / terminer le travail | `workflow.manage` ou `workshop.manage` |
| Restituer / clôturer le RDV | `or.manage` ou `workflow.manage` |
| Facturer / encaisser | back-office dédié de facturation |

---

## 6. Points d'amélioration recommandés

### Court terme
1. **Journal d'audit** par étape : qui a confirmé, reçu, démarré, terminé, restitué.
2. **Historique de statut** visible dans la fiche RDV.
3. **Motif obligatoire** pour `annule` et `non_presente`.
4. **Badge visuel OR verrouillé / brouillon** partout dans l'interface.

### Moyen terme
1. **Travaux supplémentaires avec validation client** horodatée.
2. **Checklist technicien** standardisée par type d'intervention.
3. **Blocage de restitution** si l'OR ou le rapport atelier est incomplet.
4. **Notifications internes** lors des passages `reception -> en_cours -> termine -> restitue`.

### Plus tard
1. Refonte du **back-office de facturation**.
2. Liaison plus fine entre OR, stock et temps passé atelier.
3. Tableau de bord de performance par atelier / technicien.

---

## 7. Résumé cible

Le flux cible est :

1. **Créer et confirmer le RDV**
2. **Faire la réception avec OR complet et signature client**
3. **Verrouiller l'OR et générer le PDF archivé**
4. **Autoriser le travail atelier via actions dédiées**
5. **Restituer puis clôturer sans casser la traçabilité**
