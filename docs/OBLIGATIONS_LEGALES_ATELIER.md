# Obligations légales d'un atelier de réparation moto / mécanique en France

> Document généré le 01/06/2026 pour le projet **AtelierMBZ**  
> État des lieux juridique + correspondance avec les clauses de la base de données

---

## 1. Qualification professionnelle & immatriculation

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Qualification professionnelle** | Le dirigeant **ou** un salarié permanent assurant le contrôle effectif et permanent doit être qualifié. Soit par un diplôme (CAP, BEP, MC, Bac Pro, BTS, CQP, TP ou équivalent RNCP), soit par **3 ans d'expérience professionnelle** dans le métier (Art. L121-1 à R121-3 Code de l'artisanat). Les années d'apprentissage ne comptent pas. | `mentions_legales` | ✅ Mis à jour le 01/06/2026 |
| **Répertoire des Métiers (RM)** | Immatriculation obligatoire. Le n° RM doit figurer sur les factures. | `mentions_legales` | ✅ Mis à jour le 01/06/2026 |
| **Stage de gestion** | Obligatoire à la création (loi n°82-1091 du 23/12/1982), sauf dispense. | — | Info interne |

---

## 2. Assurances obligatoires

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **RC Pro** | **Obligatoire** dès la première manipulation d'un véhicule client (Art. **R211-3 Code des Assurances**). Amende : 1 500 € à 15 000 €. Mention (assureur, n° contrat, couverture géo) sur **chaque devis et facture**. | `assurance_travaux` | ✅ Existant |
| **Assurance décennale** | **NON obligatoire** pour un atelier mécanique pur (s'applique uniquement au BTP / construction d'ouvrages). | — | ✅ Justifié de l'exclure |
| **Multirisque pro / locaux** | Fortement recommandée mais non obligatoire. | — | Info interne |

---

## 3. Devis, facturation & prix

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Devis écrit** | Pas d'obligation stricte de le faire spontanément, mais le garagiste **ne peut refuser** si le client en demande un. Le devis doit être respecté ; tout dépassement nécessite l'**accord express** du client. | `devis` | ✅ Existant |
| **Affichage des tarifs** | Les tarifs horaires de main d'œuvre (T1/T2/T3) doivent être **affichés dans le lieu d'accueil clientèle**. | `cgv` (Art. 5) | ✅ Ajouté le 01/06/2026 |
| **Facturation** | Obligatoire dès 25 € TTC pour les prestations de services (Art. L441-3 Code du commerce). | `cgv` | ✅ Existant |
| **Mentions obligatoires sur les factures** | SIRET, RM, TVA, date, n° unique, description, prix HT/unitaire/TTC, taux TVA, date d'échéance, **pénalités de retard** (min. 3x taux légal ou majoré de 10 pts), **indemnité forfaitaire 40 €**, **escompte** (ou mention « néant »), assurance RC Pro. | `cgv` (Art. 4 bis) | ✅ Ajouté le 01/06/2026 |
| **Facturation électronique** | Obligation progressive à partir du **1er janvier 2026** (B2B) via Chorus Pro ou plateforme partenaire. Amende : 15 €/facture, plafond 15 000 €/an. | — | Info interne |

---

## 4. Garanties légales

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Garantie légale de conformité** | 2 ans pour les pièces neuves, 12 mois pour les pièces d'occasion vendues par un pro (Code de la consommation, Art. L.217-3). Présomption d'antériorité du défaut : 12 mois. | `garantie` | ✅ Existant |
| **Garantie vices cachés** | Art. 1641 Code civil. Le vendeur est responsable des défauts cachés. Délai : 2 ans à compter de la découverte. | `garantie` | ✅ Existant |
| **Garantie des réparations – présomption de faute** | Pas de durée légale fixée, mais la jurisprudence (Cass. 1re civ., 11 mai 2022, n° 20-18.867) **présume la faute du garagiste** si un désordre identique survient après son intervention. | `garantie` | ✅ Ajouté le 01/06/2026 |
| **Garantie constructeur** | Si intervention sous garantie constructeur, les pièces remplacées deviennent propriété du garagiste. | `mandat_reparation` | ✅ Existant |

---

## 5. Pièces de réemploi, récupération & environnement

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Proposition de pièces de réemploi** | **Obligatoire** depuis la loi AGEC : le professionnel doit proposer au client d'opter pour une pièce issue de l'économie circulaire (Art. **L.224-67 Code de la consommation**). Le client reste libre d'accepter ou de refuser. | `pieces_occasion` | ✅ Ajouté le 01/06/2026 |
| **Récupération des pièces usagées** | Les pièces remplacées appartiennent au client. Il a le droit de les récupérer (sauf intervention gratuite sous garantie constructeur ou échange standard). | `pieces_occasion` / `mandat_reparation` | ✅ Existant |
| **DEEE / Batteries / Pneus** | Obligation de collecte sélective et remise à un collecteur agréé (BSD / Trackdéchets). | `ecologie_recyclage` | ✅ Existant |
| **Huiles usagées** | Stockage étanche, remise à un collecteur agréé (Cyclevia), conservation des bordereaux. | `ecologie_recyclage` | ✅ Existant |

---

## 6. Droit de rétention & gardiennage

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Droit de rétention** | Art. **1948 Code civil** : le garagiste peut retenir le véhicule jusqu'au paiement intégral, à condition d'avoir un **devis détaillé signé** par le client. Non applicable si réparations non demandées. | `retention` | ✅ Existant |
| **Gardiennage** | Le garagiste est dépositaire du véhicule (Art. 1915 Code civil). Obligation de soin et de restitution. | `gardiennage` | ✅ Existant |

---

## 7. Relation client & litiges

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Obligation de conseil** | Doit conseiller le client sur l'intérêt des réparations vs la valeur vénale du véhicule (Civ. 1re, 31 janv. 1995). | `diagnostic` / `devis` | ✅ Existant |
| **Travaux supplémentaires** | Accord obligatoire du client. Le garagiste ne peut pas facturer des travaux non commandés (Cass. 1re civ., 24 mai 2005). | `travaux_supplementaires` | ✅ Existant |
| **Médiation** | Obligation d'information sur le médiateur de la consommation (Art. L.612-1 Code de la consommation). | `litiges_mediation` | ✅ Existant |
| **Droit de rétractation** | 14 jours pour les ventes à distance uniquement. **Pas applicable** à une prestation réalisée en atelier avec présence du client. | — | À ajouter si vente en ligne |

---

## 8. Données personnelles (RGPD)

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Registre des traitements** | Obligatoire si > 250 salariés, ou traitements à risque. Recommandé même pour un petit atelier. | — | Info interne |
| **Mention d'information** | Informations collectées, durée de conservation, droits d'accès/rectification/suppression. | `rgpd` | ✅ Existant + cookies ajoutés |

---

## 9. Sous-traitance

| Obligation | Base juridique | Clause DB | État |
|---|---|---|---|
| **Information du client** | Si l'atelier sous-traite une partie des travaux, le client doit en être informé. | `sous_traitance` | ✅ Existant |

---

## 10. Récapitulatif des mises à jour effectuées le 01/06/2026

### Modifications en base de données

| Clause | ID | Action |
|---|---|---|
| `mentions_legales` | 2 | Ajout du numéro RM, de la TVA intracommunautaire et de la mention du **contrôle effectif et permanent par un technicien qualifié** (le diplôme peut être détenu par un salarié, pas obligatoirement par le dirigeant). |
| `cgv` | 1 | Ajout de l'**Article 4 bis** (pénalités de retard, escompte, indemnité forfaitaire 40 €) et de l'**Article 5** (affichage des tarifs horaires). |
| `pieces_occasion` | 15 | Ajout explicite de l'obligation légale de proposer des pièces de réemploi (économie circulaire, Art. L.224-67 Code de la consommation). Ajout du droit de récupération des pièces usagées. |
| `garantie` | 5 | Ajout de la **présomption de faute du garagiste** (jurisprudence Cour de cassation, 1re civ., 11 mai 2022). |
| `rgpd` | 3 | Ajout d'une section sur les **cookies et traceurs**. |

### Placeholders encore à personnaliser

Dans `mentions_legales`, remplacer les valeurs entre crochets par les informations réelles de l'atelier :
- `[SIRET]`
- `[TVA]`
- `[Hébergeur]`
- `[capital]`
- `[ville]`
- `[adresse]`

---

## 11. Check-list de conformité recommandée

- [ ] SIRET et numéro RM présents sur **chaque facture**
- [ ] Mention de l'assurance RC Pro (assureur, n°, couverture géo) sur **chaque devis et facture**
- [ ] Tarifs horaires affichés en salle d'attente
- [ ] Pénalités de retard + indemnité 40 € + escompte mentionnés sur chaque facture
- [ ] Proposition systématique de pièces de réemploi au client (trace écrite)
- [ ] BSD / Trackdéchets à jour pour les huiles usagées
- [ ] Devis signé pour tout montant > 150 € avant travaux
- [ ] Médiateur de la consommation identifié et mentionné
- [ ] Facturation électronique activée (depuis janvier 2026 si B2B)
