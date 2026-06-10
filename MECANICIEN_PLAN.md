# Plan d'implémentation — Espace Mécanicien

**Date :** 2026-05-26  
**Contexte :** MVP mono-atelier  
**Objectif :** Corriger les bugs bloquants et finaliser l'espace mécanicien pour la mise en production

---

## État actuel (synthèse audit)

L'espace mécanicien est **à 75% fonctionnel**. Le flux principal est en place mais contient des bugs bloquants et des manques importants pour un usage réel en atelier.

### Ce qui fonctionne ✅

| Fonctionnalité | Détail |
|----------------|--------|
| RDVs du jour | Endpoint `/rendez-vous/mecanicien?date={today}`, 3 sections (actif / à faire / terminés) |
| Démarrer une intervention | Bouton → transition `start_travail` → `en_cours` + chrono live |
| Chrono avec alerte dépassement | HH:MM:SS, barre progression vs temps estimé, alerte rouge si retard |
| Checkup express | 10 points (OK/NOK/vide), sauvegarde via PATCH |
| Essai routier | 10 points de contrôle, km départ/retour, durée, validation ≥5 points |
| Rapport d'intervention | Travaux réalisés, alertes, recommandations, km restitution |
| Signature mécanicien | Canvas, sauvegarde base64, OR → `intervention_signee` |
| Notes intervention | Champ libre, sauvegarde indépendante |
| KPIs dashboard | En cours / À faire / Terminés / % journée |
| Contexte réception | État véhicule PDA, points checkup réception, statut OR |

### Ce qui est cassé ❌

| Bug | Impact | Fichier |
|-----|--------|---------|
| `isSignedByBoth` jamais défini → bloc "rapport signé" invisible | Le mécanicien ne sait jamais si les deux parties ont signé | `mecanicien.vue:243` |
| Endpoint PDF `/api/rapport/{id}/pdf` inexistant | Impossible de télécharger le rapport signé | Backend manquant |
| `essaiRoutierValide` utilise `isValide` au lieu de `statut === 'valide'` | Le bouton "Terminer" peut bloquer à tort | `mecanicien.vue` |
| Canvas signature 580px fixe → déformée sur mobile | Signatures illisibles sur téléphone | `mecanicien.vue:339` |

### Ce qui manque ❌

- Absences non exploitées (entité existe, aucune UI mécanicien)
- Aucune notification au client après signature mécanicien
- Pas de gestion des pauses/reprises d'intervention
- Pas de vue mobile optimisée
- Pas d'accès aux travaux supplémentaires depuis l'espace mécanicien
- Pas de sync temps réel planning ↔ vue mécanicien

---

## PHASE 1 — Bugs bloquants (1 jour)

### Tâche 1.1 — Corriger `isSignedByBoth` en backend

**Fichier :** `backend/src/Controller/MecanicienController.php`  
**Méthode :** `flattenRdvForMecanicien()`

Ajouter dans le tableau de retour :
```php
'is_signed_by_both' => (
    $or->getSignatureMecanicien() !== null &&
    $or->getSignatureClientRestitution() !== null
),
```

**Fichier :** `frontend/pages/mecanicien.vue`  
Remplacer `rapport.isSignedByBoth` par `rapport.is_signed_by_both`.

---

### Tâche 1.2 — Corriger `essaiRoutierValide`

**Fichier :** `frontend/pages/mecanicien.vue`

**Avant :**
```typescript
const essaiRoutierValide = computed(() => 
  Boolean(activeRdv.value?.essai_routier_valide || rapport.value?.essaiRoutier?.isValide)
)
```

**Après :**
```typescript
const essaiRoutierValide = computed(() => {
  const statut = activeRdv.value?.essai_routier_statut
  return statut === 'valide' || statut === 'anomalie_detectee'
})
```

---

### Tâche 1.3 — Créer l'endpoint PDF rapport d'intervention

**Fichier à créer :** `backend/src/Controller/RapportPdfController.php`

L'endpoint `/api/rapport/{id}/pdf` doit :
1. Récupérer l'`OrdreReparation` par son ID
2. Vérifier que l'utilisateur connecté appartient au même atelier
3. Appeler `PdfService` avec le template `ordre_reparation.html.twig` (déjà existant)
4. Retourner le PDF en réponse HTTP avec le header `Content-Disposition: attachment`

```php
#[Route('/api/rapport/{id}/pdf', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
public function downloadPdf(int $id, PdfService $pdfService): Response
{
    $or = $this->em->getRepository(OrdreReparation::class)->find($id);
    
    if (!$or || $or->getAtelierId() !== $this->getCurrentAtelierId()) {
        throw $this->createNotFoundException();
    }

    $pdf = $pdfService->generateOrPdf($or);

    return new Response($pdf, 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="OR-' . $or->getNumeroOr() . '.pdf"',
    ]);
}
```

**Note :** `PdfService` et le template `ordre_reparation.html.twig` existent déjà. Vérifier que `PdfService::generateOrPdf()` est bien implémentée ou l'ajouter.

---

### Tâche 1.4 — Corriger canvas signature mobile

**Fichier :** `frontend/pages/mecanicien.vue` (ligne ~339)

**Avant :**
```html
<canvas ref="sigRapportCanvas" width="580" height="180"
  style="width:100%;height:140px;...">
```

**Après :**
```html
<canvas ref="sigRapportCanvas"
  style="width:100%;aspect-ratio:4/1;border-radius:10px;...">
```

Et initialiser les dimensions canvas via JS au montage :
```typescript
function initCanvas() {
  const canvas = sigRapportCanvas.value
  if (!canvas) return
  canvas.width = canvas.offsetWidth
  canvas.height = canvas.offsetWidth / 4
}
onMounted(() => nextTick(initCanvas))
```

---

## PHASE 2 — Absences mécanicien (1 jour)

### Tâche 2.1 — Afficher les absences dans la vue mécanicien

**Pourquoi :** Un mécanicien doit voir ses jours d'absence planifiés. Aujourd'hui l'entité `Absence` existe mais n'est jamais affichée dans son espace.

**Backend — nouveau endpoint :**  
`GET /api/mecanicien/me/absences?from=YYYY-MM-DD&to=YYYY-MM-DD`

Dans `MecanicienController.php` :
```php
#[Route('/api/mecanicien/me/absences', methods: ['GET'])]
public function myAbsences(Request $request): JsonResponse
{
    $from = new \DateTime($request->query->get('from', 'now'));
    $to   = new \DateTime($request->query->get('to', '+30 days'));

    $absences = $this->em->getRepository(Absence::class)
        ->createQueryBuilder('a')
        ->where('a.mecanicien = :meca')
        ->andWhere('a.dateDebut <= :to')
        ->andWhere('a.dateFin >= :from')
        ->setParameter('meca', $this->getCurrentMecanicien())
        ->setParameter('from', $from)
        ->setParameter('to', $to)
        ->getQuery()
        ->getResult();

    return $this->json(array_map(fn($a) => [
        'id'         => $a->getId(),
        'date_debut' => $a->getDateDebut()->format('Y-m-d'),
        'date_fin'   => $a->getDateFin()->format('Y-m-d'),
        'motif'      => $a->getMotif(),
        'notes'      => $a->getNotes(),
    ], $absences));
}
```

**Frontend — afficher un bandeau si absence aujourd'hui :**
```vue
<div v-if="absenceAujourdhui" style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:8px;padding:12px;margin-bottom:16px;">
  ⚠️ Vous êtes en absence aujourd'hui ({{ absenceAujourdhui.motif }})
</div>
```

---

### Tâche 2.2 — Bloquer les affectations si mécanicien absent

**Backend — dans `SlotService` :** Lors du calcul des créneaux disponibles, exclure les mécaniciens en absence ce jour.

**Backend — dans `MecanicienController::myRdvs()` :** Ajouter un warning dans la réponse si le mécanicien est en absence ce jour :
```php
'absence_today' => $this->hasAbsenceToday($mecanicien),
```

---

## PHASE 3 — Pause/reprise d'intervention (0.5 jour)

### Tâche 3.1 — Ajouter boutons pause/reprise

**Pourquoi :** Un mécanicien peut être interrompu (livraison pièces, client qui appelle, pause déjeuner). Sans pause, le temps effectif est faussé.

**Backend — nouvelles transitions dans `workflow.yaml` :**
```yaml
pause_travail:
    from: en_cours
    to: en_pause
reprendre_travail:
    from: en_pause
    to: en_cours
```

Ces transitions existent peut-être déjà (`en_pause` est dans le workflow) — vérifier et exposer si absent.

**Backend — dans `RendezVousWorkflowService` :** Accumuler le temps effectif lors de la reprise :
```php
case 'reprendre_travail':
    $rdv->addTempsEffectifMinutes(
        (int) (($now->getTimestamp() - $rdv->getHeureDebutPause()->getTimestamp()) / 60)
    );
    break;
```

**Frontend — bouton pause dans l'intervention active :**
```vue
<button v-if="activeRdv.statut === 'en_cours'" @click="pauseWork">
  ⏸ Pause
</button>
<button v-if="activeRdv.statut === 'en_pause'" @click="resumeWork">
  ▶ Reprendre
</button>
```

---

## PHASE 4 — Notification client après signature (0.5 jour)

### Tâche 4.1 — Envoyer "moto prête" après signature mécanicien

**Pourquoi :** Une fois le mécanicien a signé l'OR, le client doit être notifié que sa moto est prête. Aujourd'hui aucune notification n'est envoyée à ce moment.

**Ce point est déjà prévu dans `NOTIFICATIONS_PLAN.md` (Tâche 1.2 — `RdvWorkflowListener`).**  
La transition `terminer` doit déclencher l'envoi du template `travaux_termines`.

Vérifier que le listener est bien branché (cf. NOTIFICATIONS_PLAN.md) et que la notification part au bon moment — après signature mécanicien, pas juste après `terminer`.

Le bon endroit est `MecanicienController::sign()`, après le changement de statut OR :
```php
// Après or->setStatut('intervention_signee')
$this->notificationDispatcher->sendFromTemplate(
    'travaux_termines', 'sms',
    $rdv->getAtelierId(),
    $rdv->getClient()->getTelephone(),
    ['client_prenom' => $rdv->getClient()->getPrenom(), ...],
    'RendezVous',
    $rdv->getId()
);
```

---

## PHASE 5 — Travaux supplémentaires depuis l'espace mécanicien (1 jour)

### Tâche 5.1 — Afficher et créer des demandes de travaux supplémentaires

**Pourquoi :** Lors d'une intervention, le mécanicien découvre souvent des problèmes supplémentaires. Aujourd'hui il n'a aucun moyen de les signaler depuis son espace — seule la réception peut créer une `DemandeTravauxSupp`.

**Frontend — ajouter section dans l'intervention active :**
```vue
<div class="section">
  <div class="section-title">🔧 Travaux supplémentaires</div>
  
  <!-- Liste des demandes existantes -->
  <div v-for="demande in travauxSupp" :key="demande.id">
    <span>{{ demande.description }}</span>
    <span :class="statusColor(demande.statut)">{{ demande.statut }}</span>
  </div>
  
  <!-- Formulaire nouvelle demande -->
  <button @click="showNewDemande = true">+ Signaler un problème</button>
  <div v-if="showNewDemande">
    <textarea v-model="newDemande.description" placeholder="Description du problème..." />
    <input v-model="newDemande.estimatedPrice" type="number" placeholder="Coût estimé (€)" />
    <button @click="submitDemande">Envoyer pour validation client</button>
  </div>
</div>
```

**Backend — endpoint existant :** `POST /api/demande-travaux-supps` (vérifier qu'il accepte les appels depuis `ROLE_MECANICIEN`).

Quand le mécanicien soumet → notification automatique vers la réception via Mercure (déjà implémenté dans `DemandeTravauxSuppController`).

---

## Récapitulatif des fichiers à modifier

| Fichier | Changement | Phase |
|---------|-----------|-------|
| `backend/src/Controller/MecanicienController.php` | Ajouter `is_signed_by_both`, endpoint absences, notification sign | 1.1 / 2.1 / 4.1 |
| `frontend/pages/mecanicien.vue` | Corriger `isSignedByBoth`, `essaiRoutierValide`, canvas mobile, absences, pause, travaux supp | 1.1 / 1.2 / 1.4 / 2.1 / 3.1 / 5.1 |
| `backend/src/Controller/RapportPdfController.php` | Créer (nouveau fichier) | 1.3 |
| `backend/src/Service/PdfService.php` | Ajouter `generateOrPdf()` si absent | 1.3 |
| `backend/config/packages/workflow.yaml` | Ajouter `pause_travail` / `reprendre_travail` si absent | 3.1 |
| `backend/src/Service/RendezVousWorkflowService.php` | Accumuler temps effectif lors reprise | 3.1 |
| `backend/src/Service/SlotService.php` | Exclure mécaniciens en absence | 2.2 |

---

## Checklist de validation MVP

- [x] Signature mécanicien → badge "✅ Signé par les deux parties" visible quand client a aussi signé
- [x] Bouton "📄 Télécharger PDF" télécharge réellement un PDF bien formé
- [x] Bouton "Terminer" ne bloque plus à tort si essai routier est validé
- [x] Signature mécanicien lisible sur un écran 375px (iPhone)
- [x] Mécanicien en absence → bandeau d'avertissement visible
- [x] Après signature mécanicien → SMS "moto prête" envoyé au client
- [x] Mécanicien peut signaler un problème supplémentaire pendant l'intervention

---

## Ce qui est hors scope MVP

- Vue mobile native (PWA ou app)
- Historique des interventions précédentes du client depuis la vue mécanicien
- Calcul dynamique des intervalles entretien par marque/modèle
- Sync temps réel planning ↔ mécanicien (WebSocket)
- Statistiques de performance mécanicien
- Gestion des certificats/habilitations mécanicien

---

## Estimation

| Phase | Effort estimé |
|-------|--------------|
| Phase 1 — Bugs bloquants | 1 jour |
| Phase 2 — Absences | 1 jour |
| Phase 3 — Pause/reprise | 0.5 jour |
| Phase 4 — Notification client | 0.5 jour |
| Phase 5 — Travaux supplémentaires | 1 jour |
| **Total** | **4 jours** |
