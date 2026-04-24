# Audit Critique — Module VO

## Périmètre audité

Backend :
- backend/src/Controller/VOPurchaseController.php
- backend/src/Controller/VODocumentController.php
- backend/src/Controller/PublicVoCompanionController.php
- backend/src/Service/VODocumentService.php
- backend/src/Service/VONumberingService.php
- backend/src/Entity/VOPurchase.php
- backend/src/Entity/VODocument.php
- backend/src/Entity/VOLivrePolice.php
- backend/src/Entity/VOFacture.php

Frontend :
- frontend/pages/vo/**
- frontend/composables/vo*.ts

Tests :
- backend/tests/Functional/VOControllerTest.php (exécution ciblée)

Note de périmètre : le contrôleur monolithique `VOController.php` n'existe plus. La logique est répartie entre `VOPurchaseController` et `VODocumentController`.

---

## Constats confirmés

### [CRITIQUE] Signature VO Companion : contournement possible des clauses

Preuve code (`PublicVoCompanionController::saveSignature`) :
```php
$partyRole = $payload['partyRole'] ?? '';
$codesByRole = [ ... ];
if (isset($codesByRole[$partyRole])) {
    $acceptedCodes = (array) ($payload['clausesAcceptees'] ?? []);
    $missingCodes = array_diff($codesByRole[$partyRole], $acceptedCodes);
    if (!empty($missingCodes)) {
        return $this->json([...], 422);
    }
}
```

Problème : si `partyRole` est absent ou non reconnu, la vérification des clauses n'est pas exécutée et la signature continue.

Impact : signature possible sans acceptation explicite des clauses attendues (risque juridique sur parcours public VO).

Fix minimal :
- Rendre `partyRole` obligatoire et validé (`422` si absent/invalide),
- Ou mieux : dériver le rôle serveur depuis le type de dossier (`rachat`/`depot`) et ignorer la valeur client.

---

### [CRITIQUE] Régime TVA VO non borné côté backend

Preuves code :
- `VOPurchase::setRegimeTva(string $v)` affecte sans validation.
- `VOFacture::setRegimeTva(string $v)` affecte sans validation.
- `VOPurchaseController` prend `regimeTva` directement du body (create/update).

Extrait :
```php
public function setRegimeTva(string $v): static { $this->regimeTva = $v; return $this; }
```

Impact : valeurs invalides possibles en base, risque fiscal et documentaire (régime hors `marge|normal`).

Fix minimal :
- Introduire une enum stricte (`marge`, `normal`) au niveau entité + validation controller/API,
- Retourner `422` pour toute autre valeur.

---

### [IMPORTANT] DA SIV bloquante pour la vente : règle partiellement couverte

Preuves code :
- `VODocumentService::getPurchaseSaleBlockers()` ajoute `DA SIV non enregistrée.` si `!$purchase->isSivRegistered()`.
- `VOPurchaseController` bloque la vente en se basant sur ce verdict.

Point faible : la règle métier/légale des 15 jours n'est pas vérifiée dans ce blocker (retard d'enregistrement non distingué).

Impact : un dossier peut devenir vendable après enregistrement tardif sans signaler un dépassement réglementaire.

Fix minimal :
- Ajouter une vérification `purchaseDate + 15 jours` vs `sivRecordedAt`,
- Ajouter un motif bloquant explicite en cas de dépassement.

---

### [IMPORTANT] Immutabilité Livre de Police assurée à l'API, pas durcie au domaine

Preuves code :
- `VOLivrePolice` expose uniquement GET/GET Collection en API Platform.
- L'entité conserve des setters publics sur les champs d'entrée LP.

Impact : une mutation interne (service/script) reste techniquement possible malgré l'intention d'immutabilité légale.

Fix minimal :
- Ajouter une garde Doctrine (`preUpdate`) ou une policy service pour refuser toute modification des champs immuables après création.

---

### [CONFORME] Rétention RGPD 0 jour sur pièces sensibles

Preuves code :
- `VODocument::RETENTION_YEARS` :
  - `TYPE_PIECE_IDENTITE => 0`
  - `TYPE_JUSTIFICATIF_DOMICILE => 0`
- Rejet upload côté back dans :
  - `VODocumentController::uploadDocument()`
  - `PublicVoCompanionController` (upload public)

Conclusion : la règle est bien implémentée des deux côtés API.

---

### [CONFORME] Numérotation sans `MAX+1`

Preuve code :
- `VONumberingService` utilise `INSERT ... ON CONFLICT ... DO UPDATE ... RETURNING counter_value`.

Conclusion : stratégie robuste concurrence pour numérotation facture VO et LP.

---

## Preuve d'exécution tests VO

Commande exécutée :
```bash
docker compose exec -T php bin/phpunit --filter VOControllerTest
```

Résultat observé :
- `Tests: 8, Assertions: 54, Failures: 5`
- Erreur répétée : `SQLSTATE[42703]: Undefined column ... char_box_width does not exist`
- Les échecs surviennent pendant la génération PDF obligatoire lors du flux VO.

Impact : blocage technique réel sur une partie des scénarios VO (confirmation/vente/PDF).

Fix minimal :
- Aligner schéma DB et mapping attendu pour `char_box_width` (migration manquante ou rollback incomplet),
- Relancer immédiatement `VOControllerTest` après correctif.

---

## Priorisation recommandée

P0 (immédiat)
1. Verrouiller validation `partyRole/clausesAcceptees` dans Companion VO.
2. Borner strictement `regimeTva` (`marge|normal`) en backend.
3. Corriger incident DB `char_box_width` bloquant tests VO.

P1 (court terme)
4. Ajouter contrôle de délai DA SIV (15 jours) dans verdict vendabilité.
5. Durcir immutabilité LP côté domaine.

P2
6. Étendre tests d'intégration VO aux cas de dépassement DA 15 jours et rejets TVA invalides.
