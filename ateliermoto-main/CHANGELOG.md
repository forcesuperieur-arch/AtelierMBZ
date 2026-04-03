# Journal des Modifications - Atelier Moto Pro

## Version 2.0 - Mars 2025

### 🎯 Vue d'ensemble
Mise à jour majeure avec ajout du module technicien, suivi temps de travail, et améliorations du planning.

---

## ✅ Nouvelles Fonctionnalités

### 1. Espace Technicien (`/technicien.html`)
- **Interface dédiée** pour les mécaniciens
- **Points de contrôle** avec 3 états : Non vérifié / ✓ OK / ✗ Pas OK
- **Rapport structuré** : alertes, recommandations, travaux réalisés, pièces utilisées
- **Génération OR PDF** directement depuis l'espace tech
- **Statuts** : en_cours / termine / attente_piece

### 2. Suivi Temps de Travail
- **Chronomètre intégré** : Démarrer/Terminer le travail
- **Calcul automatique** du temps effectif
- **Temps confidentiel** (non visible sur l'OR client)
- **Règle de facturation** : max(temps_effectif, temps_estime)

### 3. Planning Amélioré
- **Vue semaine par défaut** : Lundi → Dimanche fixe
- **Drag & Drop** : Déplacer les RDV entre jours et heures
- **Modal clôture complète** :
  - Infos client/véhicule
  - Détail des pièces utilisées
  - Calcul main d'œuvre (temps × tarif horaire)
  - Totaux HT/TVA/TTC
  - Explication du temps facturé

### 4. Gestion des Utilisateurs
- **Création d'utilisateurs** avec rôles (admin, receptionnaire, mécanicien)
- **Édition et suppression**
- **Redirection auto** : les mécaniciens vont directement sur `/technicien.html`

### 5. OR PDF V2
- **Design moderne** : en-tête noir avec accent jaune
- **Mise en page 2 colonnes**
- **Rapport technicien intégré**
- **Section facturation** avec détail pièces et MO
- **CGV sur page 2**

---

## 🔧 Corrections Techniques

### Backend
- **API `/api/rendez-vous/public`** : Correction récupération noms prestations (utilise `GrilleTarifs` au lieu de `InterventionType`)
- **Modèle `RendezVousUpdate`** : Ajout champs `heure_rdv` et `date_rdv` pour mise à jour
- **Endpoint temps travail** : 3 nouveaux endpoints pour démarrer/terminer/récupérer le temps

### Frontend
- **Correction `API_URL`** : Passage de `''` à `window.location.origin` pour éviter les erreurs localhost
- **Modal admin** : Ajout `core.js` pour les fonctions `openModal`/`closeModal`
- **Cache** : Versioning des fichiers JS (`?v=2`, `?v=3`)

---

## 📁 Fichiers Modifiés

### Backend
```
backend/main.py
- RendezVousUpdate : +heure_rdv, +date_rdv
- update_rendez_vous() : gestion nouveaux champs
- create_rendez_vous_public() : correction types_intervention
- get_planning_semaine() : semaine fixe Lundi-Dimanche
- Nouveaux endpoints : /demarrer-travail, /terminer-travail, /temps-travail
```

### Frontend
```
frontend/planning.js
- Vue semaine par défaut
- Drag & drop (jour et semaine)
- Modal clôture avec détail facturation
- Modal édition RDV (date, heure, pont)

frontend/planning.html
- Structure boutons en 2 lignes
- Modal édition RDV
- CSS drag & drop

frontend/technicien.js (nouveau)
- Espace technicien complet
- Gestion points de contrôle
- Suivi temps de travail

frontend/technicien.html (nouveau)
- Interface technicien

frontend/app.js
- Correction API_URL
- Validation création utilisateur

frontend/admin.html
- Ajout core.js
- Correction bouton nouvel utilisateur
```

---

## 🗃️ Base de Données

### Nouvelles colonnes (table `rendez_vous`)
```sql
heure_debut_travail TIMESTAMP
heure_fin_travail TIMESTAMP
temps_effectif_minutes INTEGER
```

### Nouvelle table (créée précédemment)
```sql
rapport_technicien
- id, rendez_vous_id, points_controle (JSON)
- alertes, recommandations, travaux_realises
- pieces_utilisees (JSON), statut
```

---

## 🚀 Déploiement

```bash
# Redémarrer les services
docker compose restart

# Vérifier les logs
docker logs atelier-backend --tail 50
```

---

## 📋 TODO Prochaine Version

- [ ] Statistiques mécaniciens (productivité, temps moyen par type d'intervention)
- [ ] Gestion des tarifs et forfaits MO
- [ ] Notifications push (RDV assigné, terminé)
- [ ] Export comptabilité (CSV)

---

**Date** : 25 Mars 2025  
**Auteur** : NiceBot
