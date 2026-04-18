# SPEC — BLOC 02 — Module VO : Refonte du flux (Backend + Frontend)

> **Périmètre** : module Véhicules d'Occasion complet  
> **Stack** : Symfony 7.2 / Nuxt 3 / PostgreSQL  
> **Fichiers touchés** : backend `VOController`, `VOPurchase`, `VODepotVente`, `security.yaml` + frontend `vo/index.vue`, `stores/vo.ts`, nouvelles pages

---

## 1. Bugs bloquants à corriger en priorité

### 1.1 `ROLE_VO_MANAGER` présent dans la hiérarchie mais absent de l'UI

Dans `security.yaml` la hiérarchie est :
```yaml
ROLE_ADMIN: [ROLE_USER, ROLE_VO_MANAGER]
ROLE_VO_MANAGER: [ROLE_USER]
```

`ROLE_ADMIN` hérite de `ROLE_VO_MANAGER` → les admins peuvent accéder au VO. **Ce n'est pas un bug bloquant**, mais c'est transparent pour l'utilisateur. Dans `admin/users.vue`, la liste des rôles n'inclut pas `ROLE_VO_MANAGER` → impossible d'assigner ce rôle depuis l'UI.

**Fix** : ajouter `{ value: 'vo_manager', label: 'VO Manager' }` dans le select des rôles de `admin/users.vue`. Pas de changement backend.

### 1.2 `VOPurchase.vehicule` déclaré `OneToOne` — contrainte incorrecte

```php
#[ORM\OneToOne(targetEntity: Vehicule::class)]
#[ORM\JoinColumn(name: 'vehicule_id', nullable: false)]
private Vehicule $vehicule;
```

Si une moto est rachetée, vendue, puis remise en vente plus tard par la même plaque, la création d'un deuxième `VOPurchase` sur ce véhicule est bloquée par la contrainte d'unicité SQL.

**Fix** : changer `OneToOne` en `ManyToOne`.

```php
// VOPurchase.php
#[ORM\ManyToOne(targetEntity: Vehicule::class)]
#[ORM\JoinColumn(name: 'vehicule_id', nullable: false)]
private Vehicule $vehicule;
```

Même changement sur `VODepotVente`.

**Migration SQL** :
```sql
-- Supprimer la contrainte UNIQUE sur vehicule_id dans vo_purchases
ALTER TABLE vo_purchases DROP CONSTRAINT IF EXISTS uniq_vo_purchases_vehicule_id;
-- Supprimer la contrainte UNIQUE sur vehicule_id dans vo_depot_ventes  
ALTER TABLE vo_depot_ventes DROP CONSTRAINT IF EXISTS uniq_vo_depot_ventes_vehicule_id;
```

### 1.3 Numérotation facture non concurrente-safe

```php
// Actuel — race condition possible
$maxNum = $repo->createQueryBuilder('f')
    ->select('MAX(f.numeroFacture)')
    ->where('f.numeroFacture LIKE :prefix')
    ->setParameter('prefix', "VOF-{$year}-%")
    ->getQuery()->getSingleScalarResult();
$seq = $maxNum ? ((int) substr($maxNum, -4)) + 1 : 1;
```

**Fix** : utiliser une séquence PostgreSQL dédiée.

```sql
CREATE SEQUENCE IF NOT EXISTS vo_facture_seq START 1 INCREMENT 1;
```

Dans `VOController::sellPurchase()` et `sellDepot()`, remplacer la logique par :

```php
// Récupérer le prochain numéro via séquence PostgreSQL
$seq = (int) $this->em->getConnection()->fetchOne("SELECT nextval('vo_facture_seq')");
$numero = sprintf('VOF-%s-%04d', date('Y'), $seq);
```

Même approche pour `VOLivrePolice.numeroOrdre` dans `VOLivrePoliceService::getNextNumeroOrdre()` :

```sql
CREATE SEQUENCE IF NOT EXISTS vo_livre_police_seq START 1 INCREMENT 1;
```

```php
// VOLivrePoliceService::getNextNumeroOrdre()
private function getNextNumeroOrdre(?int $atelierId): int
{
    return (int) $this->em->getConnection()->fetchOne("SELECT nextval('vo_livre_police_seq')");
}
```

### 1.4 PDF silencieux en cas d'erreur

```php
try {
    $pdfPath = $this->pdfService->generatePvRachatPdf($purchase);
    $this->documentService->archiveGeneratedPdf(...);
    $this->em->flush();
} catch (\Throwable) {
    // silencieux — le document n'existe pas mais l'utilisateur ne le sait pas
}
```

**Fix** : logger l'erreur et inclure un champ `pdf_error` dans la réponse.

```php
try {
    $pdfPath = $this->pdfService->generatePvRachatPdf($purchase);
    $this->documentService->archiveGeneratedPdf(...);
    $this->em->flush();
    $pdfGenerated = true;
} catch (\Throwable $e) {
    $pdfGenerated = false;
    // logger ici avec $this->logger->warning('VO PDF generation failed', ['error' => $e->getMessage()])
}

return $this->json([
    'purchase'        => ...,
    'livrePoliceId'   => $entry->getId(),
    'pdfGenerated'    => $pdfGenerated,
    'pdfError'        => $pdfGenerated ? null : 'PDF non généré, à faire manuellement.',
]);
```

---

## 2. Nouveau endpoint : `GET /api/vo/purchases/{id}/full`

Le front a besoin d'une fiche complète en une requête pour la nouvelle page de détail. Le `GET /purchases/{id}` actuel ne retourne pas les documents attachés.

Ajouter dans `VOController` :

```php
#[Route('/purchases/{id}/full', methods: ['GET'])]
public function getPurchaseFull(int $id): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

    $purchase = $this->em->getRepository(VOPurchase::class)->find($id);
    if (!$purchase) {
        return $this->json(['error' => 'Not found'], 404);
    }

    // Données financières
    $data = $this->serializer->normalize($purchase, null, ['groups' => 'vo:read']);
    $data['margin']           = $purchase->getMargin();
    $data['totalFre']         = $purchase->getTotalFre();
    $data['missingDocuments'] = $this->documentService->getMissingDocuments($purchase);
    $data['canConfirm']       = count($this->documentService->getMissingDocuments($purchase)) === 0
                                && $purchase->getStatus() === 'brouillon';
    $data['canSell']          = in_array($purchase->getStatus(), ['en_stock', 'en_vente', 'reserve']);

    // Documents attachés (évite un deuxième appel)
    $documents = $this->em->getRepository(VODocument::class)->findBy(['voPurchase' => $purchase]);
    $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);

    // Entrée livre de police
    $lpEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voPurchase' => $purchase]);
    $data['livrePolice'] = $lpEntry
        ? $this->serializer->normalize($lpEntry, null, ['groups' => 'livrepolice:read'])
        : null;

    // Calcul marge live pour simulateur
    $data['marginCalculation'] = null;
    if ($purchase->getTargetSalePrice() && $purchase->getPurchasePrice()) {
        $data['marginCalculation'] = $this->marginService->calculateMarginVat(
            $purchase->getPurchasePrice(),
            $purchase->getTargetSalePrice()
        );
    }

    return $this->json($data);
}
```

Même endpoint pour dépôts :

```php
#[Route('/depots/{id}/full', methods: ['GET'])]
public function getDepotFull(int $id): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

    $depot = $this->em->getRepository(VODepotVente::class)->find($id);
    if (!$depot) return $this->json(['error' => 'Not found'], 404);

    $data = $this->serializer->normalize($depot, null, ['groups' => 'vo:read']);
    $data['commissionAmount']  = $depot->getCommissionAmount();
    $data['deposantNet']       = $depot->getDeposantNet();
    $data['mandatExpire']      = $depot->isMandatExpire();
    $data['joursRestants']     = $depot->getJoursRestantsMandat(); // ajouter méthode
    $data['missingDocuments']  = $this->documentService->getMissingDocumentsDepot($depot);
    $data['canSell']           = $depot->getStatus() === 'actif';

    $documents = $this->em->getRepository(VODocument::class)->findBy(['voDepotVente' => $depot]);
    $data['documents'] = $this->serializer->normalize($documents, null, ['groups' => 'vodoc:read']);

    $lpEntry = $this->em->getRepository(VOLivrePolice::class)->findOneBy(['voDepotVente' => $depot]);
    $data['livrePolice'] = $lpEntry
        ? $this->serializer->normalize($lpEntry, null, ['groups' => 'livrepolice:read'])
        : null;

    return $this->json($data);
}
```

Ajouter `getJoursRestantsMandat()` sur `VODepotVente` :

```php
public function getJoursRestantsMandat(): int
{
    $fin = (clone $this->dateDebut)->modify("+{$this->dureeMandat} days");
    $diff = (new \DateTime('today'))->diff($fin);
    return $diff->invert ? 0 : $diff->days;
}
```

---

## 3. Nouveau endpoint : `GET /api/vo/stock`

Vue "stock disponible à la vente" — la page d'accueil VO cible.

```php
#[Route('/stock', methods: ['GET'])]
public function stock(Request $request): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');

    $search = $request->query->get('q', '');

    // Rachats disponibles à la vente
    $qbPurchases = $this->em->getRepository(VOPurchase::class)->createQueryBuilder('p')
        ->leftJoin('p.vehicule', 'v')->addSelect('v')
        ->leftJoin('p.seller', 's')->addSelect('s')
        ->where('p.status IN (:statuses)')
        ->setParameter('statuses', ['en_stock', 'en_vente', 'reserve'])
        ->orderBy('p.createdAt', 'ASC'); // plus ancien en premier

    if ($search) {
        $qbPurchases
            ->andWhere('v.plaque LIKE :q OR v.marque LIKE :q OR v.modele LIKE :q')
            ->setParameter('q', '%' . $search . '%');
    }

    $purchases = $qbPurchases->getQuery()->getResult();

    // Dépôts actifs
    $qbDepots = $this->em->getRepository(VODepotVente::class)->createQueryBuilder('d')
        ->leftJoin('d.vehicule', 'v')->addSelect('v')
        ->leftJoin('d.deposant', 'c')->addSelect('c')
        ->where('d.status = :s')
        ->setParameter('s', 'actif')
        ->orderBy('d.dateDebut', 'ASC');

    if ($search) {
        $qbDepots
            ->andWhere('v.plaque LIKE :q OR v.marque LIKE :q OR v.modele LIKE :q')
            ->setParameter('q', '%' . $search . '%');
    }

    $depots = $qbDepots->getQuery()->getResult();

    // Mapper les données pour le front
    $stockItems = [];

    foreach ($purchases as $p) {
        $v = $p->getVehicule();
        $missingDocs = $this->documentService->getMissingDocuments($p, true); // inclure docs vente
        $joursEnStock = $p->getPurchaseDate()
            ? (new \DateTime('today'))->diff($p->getPurchaseDate())->days
            : null;

        $stockItems[] = [
            'id'           => $p->getId(),
            'source'       => 'purchase',
            'status'       => $p->getStatus(),
            'plaque'       => $v->getPlaque(),
            'marque'       => $v->getMarque(),
            'modele'       => $v->getModele(),
            'annee'        => $v->getAnnee(),
            'km'           => $v->getMileage(),
            'couleur'      => $v->getCouleur(),
            'prix_achat'   => $p->getPurchasePrice(),
            'prix_vente'   => $p->getTargetSalePrice(),
            'marge'        => $p->getMargin(),
            'total_fre'    => $p->getTotalFre(),
            'regime_tva'   => $p->getRegimeTva(),
            'jours_stock'  => $joursEnStock,
            'missing_docs' => $missingDocs,
            'can_sell'     => empty($missingDocs) || in_array($p->getStatus(), ['en_vente', 'reserve']),
            'created_at'   => $p->getCreatedAt()->format('Y-m-d'),
        ];
    }

    foreach ($depots as $d) {
        $v = $d->getVehicule();
        $missingDocs = $this->documentService->getMissingDocumentsDepot($d);
        $joursRestants = $d->getJoursRestantsMandat();

        $stockItems[] = [
            'id'             => $d->getId(),
            'source'         => 'depot',
            'status'         => $d->getStatus(),
            'plaque'         => $v->getPlaque(),
            'marque'         => $v->getMarque(),
            'modele'         => $v->getModele(),
            'annee'          => $v->getAnnee(),
            'km'             => $v->getMileage(),
            'couleur'        => $v->getCouleur(),
            'prix_vente'     => $d->getPrixVenteSouhaite(),
            'commission_ttc' => null, // calculé à la vente
            'jours_restants' => $joursRestants,
            'mandat_expire'  => $d->isMandatExpire(),
            'missing_docs'   => $missingDocs,
            'can_sell'       => $d->getStatus() === 'actif',
            'created_at'     => $d->getDateDebut()->format('Y-m-d'),
        ];
    }

    return $this->json([
        'items'          => $stockItems,
        'total_purchases'=> count($purchases),
        'total_depots'   => count($depots),
        'total'          => count($stockItems),
    ]);
}
```

---

## 4. Endpoint `POST /api/vo/margin/simulate`

Calcul de marge à la volée depuis le formulaire front.

```php
#[Route('/margin/simulate', methods: ['POST'])]
public function simulateMargin(Request $request): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
    $body = $request->toArray();

    $purchasePrice = (string) ($body['purchasePrice'] ?? '0');
    $salePrice     = (string) ($body['salePrice'] ?? '0');
    $regime        = $body['regime'] ?? 'marge';
    $freItems      = $body['freItems'] ?? []; // [{ label, amount }]

    // Total FRE
    $totalFre = '0.00';
    foreach ($freItems as $fre) {
        $totalFre = bcadd($totalFre, (string) ($fre['amount'] ?? '0'), 2);
    }

    $totalCost = bcadd($purchasePrice, $totalFre, 2);

    if ($regime === 'marge') {
        $vatCalc = $this->marginService->calculateMarginVat($purchasePrice, $salePrice);
    } else {
        $vatCalc = $this->marginService->calculateNormalVat($salePrice);
    }

    $netMargin = bcsub($salePrice, $totalCost, 2);
    $marginPct = bccomp($totalCost, '0', 2) > 0
        ? bcdiv(bcmul($netMargin, '100', 4), $totalCost, 2)
        : '0.00';

    return $this->json([
        'purchase_price'  => $purchasePrice,
        'total_fre'       => $totalFre,
        'total_cost'      => $totalCost,
        'sale_price'      => $salePrice,
        'net_margin'      => $netMargin,
        'margin_pct'      => $marginPct,
        'vat_detail'      => $vatCalc,
        'is_profitable'   => bccomp($netMargin, '0', 2) > 0,
    ]);
}
```

---

## 5. Ajout méthodes utilitaires sur `VODepotVente`

```php
// VODepotVente.php — ajouter :

public function getJoursRestantsMandat(): int
{
    $fin = (clone $this->dateDebut)->modify("+{$this->dureeMandat} days");
    $today = new \DateTime('today');
    if ($fin <= $today) return 0;
    return (int) $today->diff($fin)->days;
}

public function isMandatExpire(): bool
{
    $fin = (clone $this->dateDebut)->modify("+{$this->dureeMandat} days");
    return new \DateTime('today') > $fin;
}

public function getCommissionAmount(): string
{
    if ($this->commissionType === 'forfait') {
        return $this->commissionValeur;
    }
    return bcdiv(
        bcmul($this->prixVenteSouhaite, $this->commissionValeur, 4),
        '100',
        2
    );
}

public function getDeposantNet(): string
{
    $commission = $this->getCommissionAmount();
    // TVA 20% sur la commission
    $tva = bcdiv(bcmul($commission, '20', 4), '100', 2);
    $commissionTtc = bcadd($commission, $tva, 2);
    return bcsub($this->prixVenteSouhaite, $commissionTtc, 2);
}
```

---

## 6. Refonte architecture front — nouvelles pages

### 6.1 Structure de navigation cible

```
/vo                      → Tableau de bord VO (stats + stock à vendre)
/vo/rachats              → Liste de tous les rachats (tous statuts)
/vo/rachats/new          → Création rachat (wizard)
/vo/rachats/[id]         → Fiche détail rachat
/vo/depots               → Liste dépôts-vente
/vo/depots/new           → Création dépôt-vente (wizard)
/vo/depots/[id]          → Fiche détail dépôt-vente
/vo/livre-police         → Registre légal
/vo/factures             → Factures VO
/vo/documents            → Documents (tous)
```

La page actuelle `vo/index.vue` (1163 lignes, tout en un) est **découpée** en ces pages distinctes.

---

### 6.2 Page `/vo` — Dashboard VO

**Ce qu'elle affiche :**

1. **4 KPIs** : En stock, Vendus (mois), Dépôts actifs, Alertes docs
2. **Vue stock rapide** : les 5 véhicules les plus anciens en stock (rachats + dépôts actifs), avec prix, marge (rachat) ou commission (dépôt), jours en stock, badge docs manquants
3. **Alertes prioritaires** : docs expirés, mandats qui expirent dans < 7 jours, docs manquants sur véhicules en cours de vente
4. **Accès rapides** : boutons "+ Nouveau rachat", "+ Dépôt-vente", "Voir tout le stock"

**Source de données** :
- `GET /api/vo/stats`
- `GET /api/vo/stock` (limité à 5 items pour la vue rapide)
- `GET /api/vo/documents/alerts`

---

### 6.3 Page `/vo/rachats` — Liste rachats

**Filtres** :
- Statut : Tous / Brouillon / En stock / En vente / Réservé / Vendu
- Recherche texte (plaque, marque, modèle, vendeur)

**Colonnes du tableau** :
- Immat + Marque Modèle (avec lien vers la fiche)
- Prix achat / Prix vente cible / **Marge** (colorée vert/rouge)
- Statut (badge)
- Jours en stock (depuis `purchaseDate`)
- Documents manquants (badge rouge avec nombre)
- Actions : Voir fiche, Confirmer (si brouillon), Vendre (si en stock), PDF PV

**Pas de modal** pour les actions principales — tout passe par la fiche dédiée.

---

### 6.4 Page `/vo/rachats/new` — Création rachat (wizard 4 étapes)

**Étape 1 — Véhicule**
- Recherche par plaque (même logique que RDV)
- Si trouvé : préremplissage. Sinon : saisie manuelle (marque, modèle, année, km, couleur, VIN, cylindrée)
- Saisie VIN optionnelle mais recommandée (pour livre de police)

**Étape 2 — Vendeur**
- Recherche client existant (même composant que RDV)
- Si non trouvé : création rapide (prénom, nom, téléphone, adresse)
- Identité pièce d'identité (type, numéro, date) — obligatoire pour livre de police

**Étape 3 — Financier**
- Prix d'achat (obligatoire)
- Prix de vente cible
- FRE (Frais Remise en État) : liste dynamique de postes `{ label, amount }` avec total calculé
- **Simulateur de marge en temps réel** (appel `POST /vo/margin/simulate` debounced 500ms)
  - Affichage : Prix achat + FRE = Coût total / Prix vente cible / Marge € et % / TVA sur marge estimée
- Régime TVA (marge / normal)
- Date d'achat
- Date non-gage

**Étape 4 — Documents & Confirmation**
- Contrôle technique coché/décoché
- Expert (select des users ayant `ROLE_VO_MANAGER` ou `ROLE_ADMIN` — **plus d'ID manuel**)
- Checklist des documents requis avec statut (manquant / présent)
- Upload documents directs depuis cette étape (facultatif)
- Notes libres
- Bouton "Créer le dossier" → crée en statut `brouillon`
- Bouton "Créer et confirmer" → crée + confirme si tous les docs identité présents

---

### 6.5 Page `/vo/rachats/[id]` — Fiche rachat

**Layout en 2 colonnes** :

**Colonne gauche (principale)**

*Section Véhicule* :
- Marque, Modèle, Immat, Année, Km, Couleur, VIN
- Lien vers la fiche client/véhicule atelier

*Section Financier* :
- Tableau Prix achat / FRE détaillés / Coût total
- Prix vente cible
- **Marge calculée** (€ et %) — colorée selon seuil rentabilité
- TVA estimée selon régime
- Comparatif Prix achat vs Prix vente effectif (après vente)

*Section Documents* :
- Liste des documents attachés avec statut (présent ✅ / manquant ❌ / expiré ⚠️)
- Upload direct depuis la fiche
- Téléchargement PDF de chaque document

*Section Livre de Police* :
- N° d'ordre, date acquisition, infos vendeur — lecture seule

**Colonne droite (sidebar)**

*Statut & Actions* :
- Badge statut actuel avec workflow visuel (brouillon → en stock → vendu)
- Bouton **"Confirmer"** — désactivé si documents manquants avec message explicite
- Bouton **"Mettre en vente"** (brouillon → en_vente, optionnel)
- Bouton **"Réserver"** (en_stock → reserve, avec saisie nom acheteur potentiel)
- Bouton **"Vendre"** → ouvre panneau vente latéral (pas de modale)
- Bouton **"Générer PV"** (PDF)
- Bouton **"Archiver"** (vendu → archivé, hors stock)

*Checklist conformité* :
- Documents requis avec pourcentage de complétude
- CTA "Ajouter document manquant"

*Vendeur* :
- Nom, prénom, téléphone
- Pièce d'identité (type + masqué partiellement pour RGPD)
- Lien vers fiche client

*Expert* :
- Nom de l'expert assigné (select, pas ID)

---

### 6.6 Page `/vo/depots/[id]` — Fiche dépôt-vente

**Similaire à la fiche rachat mais avec :**

*Section Commission* :
- Prix souhaité déposant
- Type commission + valeur
- **Calcul net déposant** = Prix vente - Commission TTC
- Durée mandat + jours restants (barre de progression)
- Alerte si < 7 jours restants

*Section Mandat* :
- Date début / Date fin calculée
- Statut mandat (actif / expiré)
- Conditions restitution
- Assurance info

*Actions sidebar* :
- Bouton **"Vendre"**
- Bouton **"Restituer"** (si mandat expiré ou déposant demande la reprise) → passe le dépôt en `restitue` et envoie notif déposant
- Bouton **"Prolonger mandat"** → modal avec nouvelle durée

---

### 6.7 Panneau vente (inline, pas de modale)

Remplacer la modale de vente par un panneau qui apparaît dans la sidebar de la fiche dossier :

**Contenu** :
- Recherche acheteur (existant dans base ou nouveau)
- Prix de vente effectif (pré-rempli avec `targetSalePrice` ou `prixVenteSouhaite`)
- Simulateur marge live (pour rachat : recalcul avec prix effectif réel)
- Pour dépôt : affichage net déposant recalculé
- Confirmation et résumé : "Facture VOF-2026-0012 sera générée"
- Bouton "Confirmer la vente"

---

### 6.8 Mise à jour `stores/vo.ts`

Ajouter les nouvelles actions dans le store Pinia :

```typescript
// Ajouter dans useVoStore.actions :

async fetchStock(query?: string): Promise<any> {
  const api = useApi()
  const qs = query ? `?q=${encodeURIComponent(query)}` : ''
  return await api.get(`/vo/stock${qs}`)
},

async fetchPurchaseFull(id: number): Promise<any> {
  const api = useApi()
  return await api.get(`/vo/purchases/${id}/full`)
},

async fetchDepotFull(id: number): Promise<any> {
  const api = useApi()
  return await api.get(`/vo/depots/${id}/full`)
},

async simulateMargin(data: {
  purchasePrice: string
  salePrice: string
  regime: string
  freItems: Array<{ label: string; amount: string }>
}): Promise<any> {
  const api = useApi()
  return await api.post('/vo/margin/simulate', data)
},

async restituerDepot(id: number, data?: { notes?: string }): Promise<any> {
  const api = useApi()
  return await api.post(`/vo/depots/${id}/restituer`, data ?? {})
},

async prolongerMandat(id: number, data: { dureeSupplementaire: number }): Promise<any> {
  const api = useApi()
  return await api.patch(`/vo/depots/${id}`, { dureeMandat: data.dureeSupplementaire })
},
```

---

### 6.9 Endpoints manquants à créer

Ajouter dans `VOController` :

```php
// Restituer un dépôt-vente
#[Route('/depots/{id}/restituer', methods: ['POST'])]
public function restituerDepot(int $id, Request $request): JsonResponse
{
    $this->denyAccessUnlessGranted('ROLE_VO_MANAGER');
    $depot = $this->em->getRepository(VODepotVente::class)->find($id);
    if (!$depot) return $this->json(['error' => 'Not found'], 404);

    if (!in_array($depot->getStatus(), ['actif'])) {
        return $this->json(['error' => 'Seul un dépôt actif peut être restitué'], 400);
    }

    $body = $request->toArray();
    $depot->setStatus('restitue');
    $depot->setDateFin(new \DateTime());
    if (!empty($body['notes'])) {
        $depot->setNotes(($depot->getNotes() ?? '') . "\n[RESTITUTION] " . $body['notes']);
    }

    $this->em->flush();

    // TODO LOT 11 : notifier le déposant par SMS/email

    return $this->json($this->serializer->normalize($depot, null, ['groups' => 'vo:read']));
}
```

---

## 7. Récapitulatif migrations SQL

```sql
-- Fix OneToOne → ManyToOne (contraintes unicité)
ALTER TABLE vo_purchases DROP CONSTRAINT IF EXISTS uniq_vo_purchases_vehicule_id;
ALTER TABLE vo_depot_ventes DROP CONSTRAINT IF EXISTS uniq_vo_depot_ventes_vehicule_id;

-- Séquences pour numérotation concurrente-safe
CREATE SEQUENCE IF NOT EXISTS vo_facture_seq START 1 INCREMENT 1;
CREATE SEQUENCE IF NOT EXISTS vo_livre_police_seq START 1 INCREMENT 1;

-- Initialiser la séquence livre de police depuis le max actuel
SELECT setval('vo_livre_police_seq', COALESCE((SELECT MAX(numero_ordre) FROM vo_livre_police), 0) + 1);
```

---

## 8. Récapitulatif fichiers à créer / modifier

### Backend

| Action | Fichier |
|--------|---------|
| **MODIFIER** | `src/Entity/VOPurchase.php` (OneToOne → ManyToOne) |
| **MODIFIER** | `src/Entity/VODepotVente.php` (OneToOne → ManyToOne + méthodes utilitaires) |
| **MODIFIER** | `src/Controller/VOController.php` (+`getPurchaseFull`, +`getDepotFull`, +`stock`, +`simulateMargin`, +`restituerDepot`) |
| **MODIFIER** | `src/Service/VOLivrePoliceService.php` (séquence PostgreSQL) |
| **MODIFIER** | `src/Service/VODocumentService.php` (logging erreur PDF) |
| **CRÉER** | `migrations/VxxxxVORefonte.php` |

### Frontend

| Action | Fichier |
|--------|---------|
| **REMPLACER** | `pages/vo/index.vue` → Dashboard VO uniquement |
| **CRÉER** | `pages/vo/rachats/index.vue` |
| **CRÉER** | `pages/vo/rachats/new.vue` (wizard 4 étapes) |
| **CRÉER** | `pages/vo/rachats/[id].vue` (fiche détail) |
| **CRÉER** | `pages/vo/depots/index.vue` |
| **CRÉER** | `pages/vo/depots/new.vue` |
| **CRÉER** | `pages/vo/depots/[id].vue` |
| **CRÉER** | `pages/vo/livre-police.vue` |
| **CRÉER** | `pages/vo/factures.vue` |
| **CRÉER** | `pages/vo/documents.vue` |
| **MODIFIER** | `stores/vo.ts` (nouvelles actions) |
| **MODIFIER** | `layouts/default.vue` (menu VO mis à jour → lien vers `/vo`) |

---

## 9. Ce qui ne change pas

- `VOLivrePolice` reste **immuable** (pas de PUT/PATCH/DELETE) — conforme Art. 321-7 Code Pénal
- `VODocumentService` — logique de vérification des docs requis inchangée
- `VOMarginService` — calculs TVA sur marge inchangés
- `PdfService` — génération PDF inchangée
- Sécurité `ROLE_VO_MANAGER` — hiérarchie déjà correcte dans `security.yaml`

