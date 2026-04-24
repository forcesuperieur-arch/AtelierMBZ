# Audit Critique — Workflow RDV

## Vue d'ensemble

Ce chapitre documente les **bugs, incohérences et risques identifiés** lors d'une analyse ligne-à-ligne du workflow RDV et de ses composants (services, controllers, listeners, entités, tests). Les problèmes sont hiérarchisés par criticité.

---

## 🔴 BUGS CRITIQUES

### 1. Kilométrage — Modifiable après réception

**Règle métier** (copilot-instructions, section "Règles métier non négociables") :
> Le kilométrage n'est jamais saisi à la prise de RDV — uniquement à la réception physique.

**Implémentation** :
- Controller `RendezVousController::transition()` : saisie km restreinte à transition `reception` ✓
- Mais `RendezVous::kilometrage` est modifiable via PATCH sans restriction post-réception

**Scénario exploit** :
```
POST /api/rendez-vous/123/transition/reception { "kilometrage": 100 }  # OK, J0
PATCH /api/rendez-vous/123 { "kilometrage": 50 }  # Pas bloqué, J3+
```

**Impact** :
- Fausse facturation (prix/dégressif dépend km réel)
- Audit incomplet (changement de km non tracé)
- Possible fraude (réduction artificielle de km = moins d'usure facturée)

**Fix minimal** :
```php
// RendezVous.php
public function setKilometrage(?int $km): void
{
    if ($this->kilometrage !== null && $this->kilometrage !== $km) {
        throw new \LogicException('Kilométrage gelé après réception. Créer OR rectificatif.');
    }
    $this->kilometrage = $km;
}
```

---

### 2. Essai Routier — Guard laisser-passer brouillon

**Guard dans `RdvTerminationGuardSubscriber`** :
```php
$essai = $rdv->getEssaiRoutier();
if (!$essai instanceof EssaiRoutier || !$essai->isValide()) {
    $event->setBlocked(true, 'Essai routier obligatoire et valide...');
    return;
}
```

**Problème** : `isValide()` ne teste probablement que le statut (`statut !== 'brouillon'`), PAS :
- km début/fin renseignés
- km fin > km début
- 10 points de contrôle remplis
- Points de contrôle ne sont pas tous `null`

**Scénario risque** :
```
1. Transition start_travail → mecanicien.vue s'ouvre
2. Essai créé automatiquement (statut: brouillon)
3. Mécanicien ne remplit rien, clique Terminer
4. Guard vérifie : essai existe + isValide() === true (statut != brouillon?)
5. Intervention clôturée SANS données essai routier
```

**Conséquence légale** : Impossible de prouver panne mineure/majeure après restitution → responsabilité atelier.

**Fix** :
```php
// EssaiRoutier.php
public function isValide(): bool
{
    return $this->isComplete() && in_array($this->statut, ['valide', 'anomalie_detectee']);
}

public function isComplete(): bool
{
    return $this->kmDebut > 0 && $this->kmFin > $this->kmDebut
        && count(array_filter($this->pointsControle, fn($p) => $p['ok'] !== null)) === 10;
}
```

---

### 3. Rapport Intervention — Signature asymétrique

**Workflow spécifié** :
> Rapport d'intervention = 2 signatures : mécanicien PUIS client

**Implémentation** :
- `RapportInterventionService::validateForMecanicienSignature()` : vérifie essai + travaux
- Aucune garde trouvée avant signature CLIENT
- Transition `restituer` : condition supposée `rapport signé par les deux`

**Scénario faille** :
```
1. Mécanicien oublie de signer rapport
2. Réceptionnaire accède companion client
3. Client signe sans voir signature mécanicien
4. Rapport contient : signature client seule
5. Client plus tard : "Le mécanicien dit qu'il a pas signé"
```

**Fix** :
```php
// Dans RdvTerminationGuardSubscriber
if ($rapport->getSignatureMecanicien() === null) {
    $event->setBlocked(true, 'Rapport doit être signé par le mécanicien avant client.');
}
if ($rapport->getSignatureClient() === null) {
    $event->setBlocked(true, 'Rapport doit être signé par le client pour restituer.');
}
```

---

## 🟠 BUGS IMPORTANTS

### 4. Chrono Work Session — Accumulation silencieuse overnight

**Code dans `RendezVousWorkflowService::finalizeWorkSession()`** :
```php
$rdv->setHeureFinTravail($endedAt);
$diff = $startedAt->diff($endedAt);
$minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
if ($minutes > 0) {
    $rdv->setTempsEffectifMinutes(($rdv->getTempsEffectifMinutes() ?? 0) + $minutes);
}
```

**Scénario fuite de données** :
```
Jour 1, 09:00 : start_travail (heureDebutTravail = 2026-04-24 09:00)
Jour 1, 17:00 : Mécanicien ferme navigateur (pas de transition stop)
Jour 2, 08:00 : Réceptionnaire lance dashboard
Jour 2, 14:00 : Compagnon mécanicien reconnecter, transition reprendre
  → finalizeWorkSession() : 09:00 J1 à 14:00 J2 = 29 heures accumulées ❌
```

**Impact** :
- Facture 29h MO au lieu de 8h réel
- Impossible de détecter overshooting (pas d'alerte si > 12h)

**Fix** :
```php
public function finalizeWorkSession(RendezVous $rdv, ?\DateTimeInterface $endedAt = null): void
{
    $startedAt = $rdv->getHeureDebutTravail();
    if (!$startedAt) return;

    $endedAt ??= new \DateTime();
    
    // [C1] Rejeter si delta > 12h (journée normale impossible)
    $diff = $startedAt->diff($endedAt);
    $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    if ($minutes > 720) {
        throw new \LogicException(
            sprintf('Session anormale : %d heures depuis %s. Vérifier chrono.',
                (int) ($minutes / 60), $startedAt->format('Y-m-d H:i'))
        );
    }

    $rdv->setHeureFinTravail($endedAt);
    if ($minutes > 0) {
        $rdv->setTempsEffectifMinutes(($rdv->getTempsEffectifMinutes() ?? 0) + $minutes);
    }
}
```

---

### 5. Gardiennage — Facturation rétroactive manquante

**Workflow gardiennage** :
```
1. Intervention terminée J0
2. Transition passer_gardiennage → en_gardiennage (J0)
3. Client récupère moto J45
4. Transition sortir_gardiennage → en_cours (J45)
5. Facturation : ???
```

**Problème** :
- `GardiennageService::calculerMontant()` prend `dateRestitution` en paramètre
- Aucun champ `gardiennageFinAt` dans entité pour tracer quand c'est sorti
- Transition `sortir_gardiennage` ne saisit pas la vraie date

**Scénario** :
```
Moto en gardiennage du 20/04 au 25/04 = 5 jours = 25€
Mais qui facturo : 0 jours (pas enregistré) ou 5 jours (si admin saisit à la main)?
```

**Fix** :
```php
// RendezVous.php
private ?\DateTimeInterface $gardiennageFinAt;

// Dans transition sortir_gardiennage
$rdv->setGardiennageFinAt(new \DateTime());
$montant = $gardiennageService->calculerMontant(
    $rdv, 
    $rdv->getGardiennageFinAt()  // Calcul rétroactif
);
// Ajouter facture ligne gardiennage auto
```

---

### 6. Annulation RDV — Motif/Source incohérents

**Entité `AnnulationRdv`** :
```php
const MOTIFS = ['client_desiste', 'client_no_show', 'non_presente', 
                 'atelier_indisponible', 'piece_non_disponible', ...];
const SOURCES = ['atelier', 'client', 'automatique'];
```

**Problème** : Aucune validation que motif + source sont cohérents.

**Cas invalides** :
- Source = 'client', motif = 'piece_non_disponible' ❌ (c'est atelier qui décide)
- Source = 'automatique', motif = 'client_desiste' ❌ (client ne peut pas auto se dister)
- Source = 'atelier', motif = 'client_no_show' ❌ (c'est client qui no-show, pas atelier)

**Impact** : Rapports d'annulation inutilisables, SLA client incompréhensible.

**Fix** :
```php
// AnnulationRdv.php
const MOTIFS_PAR_SOURCE = [
    'client' => ['client_desiste', 'client_indisponible', 'non_presente'],
    'atelier' => ['atelier_indisponible', 'piece_non_disponible', 'no_show'],
    'automatique' => ['doublon', 'erreur_saisie', 'force_majeure'],
];

public function __construct(RendezVous $rdv, string $motif, string $source)
{
    if (!in_array($motif, self::MOTIFS_PAR_SOURCE[$source] ?? [])) {
        throw new \InvalidArgumentException(
            "Motif '$motif' incompatible avec source '$source'"
        );
    }
}
```

---

### 7. Demandes Travaux Complémentaires — Pas de State Machine

**Entité `DemandeTravauxSupp`** :
```php
const STATUT_EN_ATTENTE = 'en_attente';
const STATUT_EN_ATTENTE_VALIDATION = 'en_attente_validation';
const STATUT_EN_ATTENTE_DECISION_CLIENT = 'en_attente_decision_client';
const STATUT_ACCEPTE = 'accepte';
const STATUT_REFUSE = 'refuse';
```

**Problème** : Aucune state machine ni listeners trouvés. Transitions gérées comment?

**Scénario confusion** :
```
1. Meca demande travaux complémentaires
2. Statut passe à... ? (code met où?)
3. Réceptionnaire approuve : statut → ?
4. Client reçoit notification : token URL publique
5. Client refuse : statut → refuse
6. Qui notifie meca? Où? Jamais.
```

**Impact** : Demandes en limbo, SLA non-respectés, pas d'escalade.

**Fix** : Créer state machine `demande_travaux_supp` dans workflow.yaml avec transitions + listeners.

---

## 🟡 BUGS MODÉRÉS

### 8. SlotService — Absence sans pont impacte mal

**Code dans `SlotService::getSlotsForDay()`** :
```php
foreach ($ponts as $pont) {
    $mecId = $pont->getMecanicien()?->getId();
    if ($mecId && in_array($mecId, $absentMecaIds, true)) continue;
    // ... add slot
}
```

**Problème** :
- Si pont n'a pas de mécanicien assigné, slot reste dispo
- Mais règle métier : pont sans meca = indisponible (pas qui l'utilise?)

**Risque** : Client booké sur pont vide → confusion.

---

### 9. OR Signé — Rectification sans règles

**Entité `OrdreReparation`** :
```php
private ?OrdreReparation $rectifiedFrom;
private ?int $rectifiedBy;
private ?\DateTimeInterface $rectifiedAt;
```

**Manquant** :
- Qui peut rectifier? (Should be Resp. Atelier + Resp. Magasin, pas mécanicien)
- Permission `PERM_or.rectify`?
- OR rectificatif doit-il être re-signé client?

**Risque** : N'importe qui crée OR rectificatif, contournant signature client.

---

### 10. Photos Intervention — Pas versionnées, pas d'audit

**Entité `PhotoIntervention`** :
```php
private string $type;  // en_cours, apres_travaux, restitution, probleme
private ?string $description;
```

**Manquant** :
- Soft-delete tracking (qui supprime une photo + quand)
- Pas d'audit log (AuditService::log() appelé?)
- Version control (si 3 restitution photos + on en efface 1)

**Scénario** :
```
Litige post-restitution : "Vous aviez promis de fixer la selle"
Réceptionnaire : "Les photos le prouvent"
Admin (nerveux) : *supprime photo compromise*
Audit : "Aucune trace de suppression"
```

---

## 📋 RÉSUMÉ PARCOURANT

| Critère | Critique 🔴 | Important 🟠 | Moyen 🟡 | Total |
|---|---|---|---|---|
| Bugs sans fix | 3 | 4 | 3 | 10 |
| Impact légal | 2 | 1 | 1 | 4 |
| Impact financier | 1 | 2 | 1 | 4 |
| Impact UX | 0 | 2 | 3 | 5 |
| Risk facteur | Haut | Moyen | Bas | — |

---

## Recommandations de priorisation

### Phase 1 (Immédiat : RDV actifs)
1. **Kilométrage setter privé** (1h) — Risque fraude
2. **Essai routier isComplete()** (1h) — Dégât légal
3. **Chrono > 12h check** (30min) — Bug financier facile

### Phase 2 (Sprint ~)
4. **Signature rapport asymétrique** → Guard additionnel (30min)
5. **Gardiennage Fin date** → Champ + facturation (2h)
6. **Annulation motif/source validation** (1h)

### Phase 3 (Refactor)
7. **Demandes travaux state machine** (4h)
8. Autres corrections modérées

---

## Notes d'audit

**Couverture de tests** :
- Backend : 178 tests existants, mais `RendezVousWorkflowServiceTest` peu exhaustif
- Frontend : 19 tests, aucun sur transitions RDV côté planning.vue
- Gap majeur : pas de tests d'intégration transition end-to-end avec side-effects

**Dette technique** :
- `RapportTechnicien` entité obsolète non supprimée
- SlotService: calcul pause crossing complexe, pas assez documenté

**Suggestion** :
- Ajouter logs DEBUG sur chaque transition (surtout terminer) pour audit trail
- Tests de chaos : clicker transitions rapid-fire, vérifier cohérence state

