# SPEC — BLOC 01 — Espace Mécanicien (Backend)

> **Périmètre** : backend uniquement — PHP/Symfony/Doctrine/Workflow  
> **Pas de front dans ce fichier.**  
> **Base cible** : `AtelierMBZ-atelier-v2-only` — Symfony 7.2 + API Platform 4.1 + PostgreSQL 15

---

## 1. État actuel — Ce qui existe et ce qui est cassé

### 1.1 State machine (workflow.yaml)

Statuts actuels : `en_attente → reserve → confirme → reception → en_cours → termine → restitue → facture → paye → annule`

**Problèmes identifiés :**
- `annuler` uniquement depuis `[en_attente, reserve, confirme]` → impossible d'annuler un RDV après réception (no-show réel non géré)
- `pause` référencé dans `RendezVousController` (`in_array($transitionName, ['terminer', 'pause'])`) mais **absent du workflow** → appel qui échoue silencieusement
- Statuts métier manquants : `no_show`, `en_attente_pieces`, `en_gardiennage`
- Pas de transition `reprendre` depuis `en_cours` (nécessaire si on ajoute `pause`)

### 1.2 RendezVousController — transition `start_travail`

```php
// Vérifie OR signé — OK
$ordreInitial = repo->findOneBy(['rendezVous' => $rdv], ['id' => 'DESC']);
if (!$ordreInitial || !$ordreInitial->getSignatureClient()) {
    return HTTP 400
}
$rdv->setHeureDebutTravail(new \DateTime());
```

**Problème** : le front (`mecanicien.vue`) appelle `ensureOrForRdv()` qui crée un OR **sans signature** si aucun n'existe, puis tente `start_travail` qui échoue avec 400. Boucle de blocage silencieux. Le back doit clarifier cette règle.

### 1.3 Lien User → Mecanicien

```php
$mecanicien = $repo->findOneBy(['userId' => $user->getId()]);
if (!$mecanicien) return $this->json([]); // silencieux
```

`Mecanicien.userId` est un `?int` nu, pas une FK Doctrine. Aucun index, aucune contrainte d'unicité. Si un admin crée un User avec role mécanicien sans créer l'entité `Mecanicien` associée, la page mécanicien retourne une liste vide sans aucune explication.

### 1.4 Endpoint `GET /api/ordres-reparation` appelé sans filtre

Le front charge **tous les OR de l'atelier** pour construire un mapping `rdvId → OR`. Sur un atelier avec 500 OR actifs c'est 500 rows transférées inutilement. Aucun endpoint filtré par mécanicien n'existe.

### 1.5 Notes mécanicien écrasent le commentaire RDV

```js
// mecanicien.vue — persistWorkshopReport()
await rdvStore.updateRdv(rdv.id, { commentaire: interventionNotes })
```

Le `commentaire` du RDV contient la description originale du problème saisie par le réceptionniste à la prise de RDV. Cette ligne l'écrase définitivement avec les notes techniques du mécanicien. Perte d'information irréversible.

### 1.6 RapportTechnicien existe mais est inutilisé

L'entité `RapportTechnicien` (OneToOne avec RendezVous) a des champs propres : `pointsControle`, `alertes`, `recommandations`, `travauxRealises`, `piecesUtilisees`, `statut`, `dateDebut`, `dateFin`. Mais le front ne l'utilise pas — il stocke tout dans `OrdreReparation.etatVehicule` (JSON).

### 1.7 EssaiRoutier inexistant

Aucune entité, aucun endpoint, aucune vérification. La règle métier impose un essai routier obligatoire avant `terminer` (règle du dernier intervenant, zéro exception).

### 1.8 Transition `terminer` ne vérifie rien

Actuellement `terminer` :
1. Pose `heure_fin_travail = NOW()`
2. Calcule `temps_effectif_minutes`
3. Apply transition
4. Dispatch `SendRappelMessage(travaux_termines)`

Aucune vérification : pas d'essai routier, pas de rapport, pas de checkup minimum.

---

## 2. Ce qu'il faut faire — Liste des modifications

---

### 2.1 Mise à jour `workflow.yaml`

**Fichier** : `backend/config/packages/workflow.yaml`

Ajouter les statuts et transitions manquants :

```yaml
framework:
    workflows:
        rendez_vous:
            type: state_machine
            audit_trail:
                enabled: true
            marking_store:
                type: method
                property: statut
            supports:
                - App\Entity\RendezVous
            initial_marking: en_attente
            places:
                - en_attente
                - reserve
                - confirme
                - reception
                - en_cours
                - en_pause          # NOUVEAU
                - en_attente_pieces # NOUVEAU
                - en_gardiennage    # NOUVEAU
                - termine
                - restitue
                - facture
                - paye
                - annule
            transitions:
                reserver:
                    from: en_attente
                    to: reserve
                confirmer:
                    from: [en_attente, reserve]
                    to: confirme
                reception:
                    from: confirme
                    to: reception
                start_travail:
                    from: reception
                    to: en_cours
                # NOUVEAU : pause et reprise
                mettre_en_pause:
                    from: en_cours
                    to: en_pause
                reprendre:
                    from: en_pause
                    to: en_cours
                # NOUVEAU : attente pièces
                mettre_en_attente_pieces:
                    from: [en_cours, en_pause]
                    to: en_attente_pieces
                reprendre_apres_pieces:
                    from: en_attente_pieces
                    to: en_cours
                # NOUVEAU : gardiennage
                mettre_en_gardiennage:
                    from: [reception, confirme, en_attente_pieces]
                    to: en_gardiennage
                sortir_gardiennage:
                    from: en_gardiennage
                    to: confirme
                terminer:
                    from: [en_cours, en_pause]
                    to: termine
                restituer:
                    from: termine
                    to: restitue
                facturer:
                    from: [termine, restitue]
                    to: facture
                payer:
                    from: facture
                    to: paye
                # MODIFIÉ : annuler étendu
                annuler:
                    from: [en_attente, reserve, confirme, reception, en_attente_pieces, en_gardiennage]
                    to: annule
                # NOUVEAU : no_show
                no_show:
                    from: [confirme, reception]
                    to: annule
```

**Notes importantes :**
- `en_pause` → le chrono s'arrête, `heure_fin_travail` intermédiaire posée, `temps_effectif_minutes` incrémenté
- `reprendre` → nouveau `heure_debut_travail` pour le segment suivant
- `en_attente_pieces` → la moto reste en atelier, client notifié
- `en_gardiennage` → la moto est stockée, facturation gardiennage possible
- `no_show` → synonyme d'`annule` mais motif distinct pour les stats

---

### 2.2 Nouvelle entité `EssaiRoutier`

**Fichier à créer** : `backend/src/Entity/EssaiRoutier.php`

```php
<?php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'essais_routiers')]
#[ApiResource(
    normalizationContext: ['groups' => ['essai:read']],
    denormalizationContext: ['groups' => ['essai:write']],
    operations: [
        new Get(uriTemplate: '/essais-routiers/{id}'),
        new Post(uriTemplate: '/essais-routiers'),
        new Put(uriTemplate: '/essais-routiers/{id}'),
    ]
)]
class EssaiRoutier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['essai:read', 'rdv:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    // Lien OneToOne avec RendezVous
    #[ORM\OneToOne(targetEntity: RendezVous::class)]
    #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false, unique: true)]
    #[Groups(['essai:read', 'essai:write'])]
    private RendezVous $rendezVous;

    // Mécanicien qui a fait l'essai (règle du dernier intervenant)
    #[ORM\ManyToOne(targetEntity: Mecanicien::class)]
    #[ORM\JoinColumn(name: 'mecanicien_id', nullable: false)]
    #[Groups(['essai:read'])]
    private Mecanicien $mecanicien;

    // Kilométrage début (récupéré depuis RendezVous.kilometrage à la réception)
    #[ORM\Column]
    #[Groups(['essai:read', 'essai:write'])]
    private int $kmDebut;

    // Kilométrage fin (saisi après l'essai)
    #[ORM\Column(nullable: true)]
    #[Groups(['essai:read', 'essai:write'])]
    private ?int $kmFin = null;

    // Checkpoints : JSON array de { key: string, statut: 'ok'|'nok'|'na', note?: string }
    // Clés : demarrage, acceleration, freinage, virage_gauche, virage_droit,
    //         tenue_route, bruit_moteur, bruit_freins, vibrations, comportement_general
    #[ORM\Column(type: 'text', options: ['default' => '[]'])]
    #[Groups(['essai:read', 'essai:write'])]
    private string $checkpoints = '[]';

    // Observations libres du mécanicien
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['essai:read', 'essai:write'])]
    private ?string $observations = null;

    // Statut : en_cours | valide | anomalie_detectee
    #[ORM\Column(length: 30, options: ['default' => 'en_cours'])]
    #[Groups(['essai:read', 'essai:write'])]
    private string $statut = 'en_cours';

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['essai:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['essai:read', 'essai:write'])]
    private ?\DateTimeInterface $validatedAt = null;

    public function __construct() { $this->createdAt = new \DateTime(); }

    // Getters/setters standards
    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getMecanicien(): Mecanicien { return $this->mecanicien; }
    public function setMecanicien(Mecanicien $v): static { $this->mecanicien = $v; return $this; }
    public function getKmDebut(): int { return $this->kmDebut; }
    public function setKmDebut(int $v): static { $this->kmDebut = $v; return $this; }
    public function getKmFin(): ?int { return $this->kmFin; }
    public function setKmFin(?int $v): static { $this->kmFin = $v; return $this; }
    public function getCheckpoints(): array { return json_decode($this->checkpoints, true) ?: []; }
    public function setCheckpoints(array $v): static { $this->checkpoints = json_encode($v); return $this; }
    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $v): static { $this->observations = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $v): static { $this->validatedAt = $v; return $this; }

    public function isValide(): bool
    {
        return $this->statut === 'valide' && $this->kmFin !== null;
    }
}
```

**Ajouter sur `RendezVous`** la relation inverse :

```php
// Dans RendezVous.php — ajouter :
#[ORM\OneToOne(targetEntity: EssaiRoutier::class, mappedBy: 'rendezVous', cascade: ['persist', 'remove'])]
#[Groups(['rdv:read'])]
private ?EssaiRoutier $essaiRoutier = null;

// + getter/setter
public function getEssaiRoutier(): ?EssaiRoutier { return $this->essaiRoutier; }
public function setEssaiRoutier(?EssaiRoutier $v): static { $this->essaiRoutier = $v; return $this; }
```

---

### 2.3 Ajout champ `mechanic_notes` sur `OrdreReparation`

**Fichier** : `backend/src/Entity/OrdreReparation.php`

Ajouter un champ dédié pour les notes techniques du mécanicien, **séparé** du JSON `etatVehicule` et du `commentaire` RDV :

```php
// Ajouter dans OrdreReparation :
#[ORM\Column(type: 'text', nullable: true)]
#[Groups(['ordre:read', 'ordre:write'])]
private ?string $mechanicNotes = null;

#[ORM\Column(type: 'datetime', nullable: true)]
#[Groups(['ordre:read'])]
private ?\DateTimeInterface $mechanicNotesUpdatedAt = null;

public function getMechanicNotes(): ?string { return $this->mechanicNotes; }
public function setMechanicNotes(?string $v): static { 
    $this->mechanicNotes = $v; 
    $this->mechanicNotesUpdatedAt = new \DateTime();
    return $this; 
}
public function getMechanicNotesUpdatedAt(): ?\DateTimeInterface { return $this->mechanicNotesUpdatedAt; }
```

**Migration SQL correspondante :**
```sql
ALTER TABLE ordres_reparation 
    ADD COLUMN mechanic_notes TEXT NULL,
    ADD COLUMN mechanic_notes_updated_at TIMESTAMP NULL;
```

---

### 2.4 Ajout champ `mechanic_checkup` sur `OrdreReparation`

Même logique : sortir le checkup mécanicien du JSON `etatVehicule` pour le mettre dans un champ dédié.

```php
// Ajouter dans OrdreReparation :
#[ORM\Column(type: 'text', options: ['default' => '{}'])]
#[Groups(['ordre:read', 'ordre:write'])]
private string $mechanicCheckup = '{}';

#[ORM\Column(type: 'datetime', nullable: true)]
#[Groups(['ordre:read'])]
private ?\DateTimeInterface $mechanicCheckupUpdatedAt = null;

public function getMechanicCheckup(): array { return json_decode($this->mechanicCheckup, true) ?: []; }
public function setMechanicCheckup(array $v): static { 
    $this->mechanicCheckup = json_encode($v);
    $this->mechanicCheckupUpdatedAt = new \DateTime();
    return $this;
}
public function getMechanicCheckupUpdatedAt(): ?\DateTimeInterface { return $this->mechanicCheckupUpdatedAt; }
```

**Migration SQL :**
```sql
ALTER TABLE ordres_reparation 
    ADD COLUMN mechanic_checkup TEXT NOT NULL DEFAULT '{}',
    ADD COLUMN mechanic_checkup_updated_at TIMESTAMP NULL;
```

---

### 2.5 Mise à jour `RendezVousController`

**Fichier** : `backend/src/Controller/RendezVousController.php`

#### 2.5.1 — Transition `mettre_en_pause`

Ajouter dans la méthode `transition()` :

```php
if ($transitionName === 'mettre_en_pause') {
    // Stopper le chrono du segment en cours
    if ($rdv->getHeureDebutTravail()) {
        $diff = $rdv->getHeureDebutTravail()->diff(new \DateTime());
        $minutes = $diff->h * 60 + $diff->i;
        $rdv->setTempsEffectifMinutes(($rdv->getTempsEffectifMinutes() ?? 0) + $minutes);
    }
    $rdv->setHeureFinTravail(new \DateTime());
    // heure_debut_travail reste posée pour info, sera réécrasée au reprendre
}
```

#### 2.5.2 — Transition `reprendre`

```php
if ($transitionName === 'reprendre') {
    // Nouveau segment : on repose heure_debut_travail maintenant
    $rdv->setHeureDebutTravail(new \DateTime());
    $rdv->setHeureFinTravail(null);
}
```

#### 2.5.3 — Transition `terminer` — vérification essai routier obligatoire

```php
if ($transitionName === 'terminer') {
    // Vérifier qu'un essai routier validé existe pour ce RDV
    $essai = $this->em->getRepository(EssaiRoutier::class)->findOneBy(['rendezVous' => $rdv]);
    if (!$essai || !$essai->isValide()) {
        return $this->json([
            'error' => 'Essai routier obligatoire avant clôture. Créez et validez un essai routier pour ce RDV.',
            'code' => 'ESSAI_ROUTIER_REQUIS',
        ], Response::HTTP_BAD_REQUEST);
    }
    // Calcul temps effectif final
    if ($rdv->getHeureDebutTravail()) {
        $diff = $rdv->getHeureDebutTravail()->diff(new \DateTime());
        $minutes = $diff->h * 60 + $diff->i;
        $rdv->setTempsEffectifMinutes(($rdv->getTempsEffectifMinutes() ?? 0) + $minutes);
    }
    $rdv->setHeureFinTravail(new \DateTime());
}
```

#### 2.5.4 — Transition `no_show`

```php
if ($transitionName === 'no_show') {
    // Stocker le motif dans un champ dédié (voir 2.6)
    $motifNoShow = $data['motif'] ?? 'no_show';
    $rdv->setMotifAnnulation($motifNoShow);
}
```

#### 2.5.5 — Enrichir `flattenRdv()` avec les données mécanicien

Ajouter dans le tableau retourné :

```php
// Dans flattenRdv() :
'heure_debut_travail' => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
'heure_fin_travail'   => $r->getHeureFinTravail()?->format('Y-m-d H:i:s'),
'temps_effectif_minutes' => $r->getTempsEffectifMinutes(),
'or_id'               => $orInitial?->getId(),         // ajouter fetch OR
'or_mechanic_notes'   => $orInitial?->getMechanicNotes(),
'or_mechanic_checkup' => $orInitial?->getMechanicCheckup(),
'essai_routier_id'    => $r->getEssaiRoutier()?->getId(),
'essai_routier_statut'=> $r->getEssaiRoutier()?->getStatut(),
'km_reception'        => $r->getKilometrage(),
'etat_vehicule_reception' => $r->getEtatVehicule() 
    ? json_decode($r->getEtatVehicule(), true) 
    : null,
```

Pour récupérer l'OR initial sans requête N+1 :

```php
// Ajouter en haut de flattenRdv() :
$orInitial = $this->em->getRepository(OrdreReparation::class)
    ->findOneBy(['rendezVous' => $r, 'typeOr' => 'initial']);
```

---

### 2.6 Ajout `motifAnnulation` sur `RendezVous`

**Fichier** : `backend/src/Entity/RendezVous.php`

Remplacer le stockage du motif en commentaire free-text par un champ dédié :

```php
// Ajouter dans RendezVous :
#[ORM\Column(length: 50, nullable: true)]
#[Groups(['rdv:read', 'rdv:write'])]
private ?string $motifAnnulation = null;

#[ORM\Column(type: 'text', nullable: true)]
#[Groups(['rdv:read', 'rdv:write'])]
private ?string $commentaireAnnulation = null;

public function getMotifAnnulation(): ?string { return $this->motifAnnulation; }
public function setMotifAnnulation(?string $v): static { $this->motifAnnulation = $v; return $this; }
public function getCommentaireAnnulation(): ?string { return $this->commentaireAnnulation; }
public function setCommentaireAnnulation(?string $v): static { $this->commentaireAnnulation = $v; return $this; }
```

**Valeurs acceptées pour `motifAnnulation`** :
`client_indisponible`, `atelier_indisponible`, `piece_non_disponible`, `non_presente`, `no_show`, `doublon`, `autre`

**Migration SQL :**
```sql
ALTER TABLE rendez_vous 
    ADD COLUMN motif_annulation VARCHAR(50) NULL,
    ADD COLUMN commentaire_annulation TEXT NULL;
```

Mettre à jour la transition `annuler` dans le controller :

```php
if (in_array($transitionName, ['annuler', 'no_show'])) {
    if (!empty($data['motif'])) {
        $rdv->setMotifAnnulation($data['motif']);
    }
    if (!empty($data['commentaire'])) {
        $rdv->setCommentaireAnnulation($data['commentaire']);
    }
}
```

---

### 2.7 Nouveau endpoint `GET /api/mecanicien/me`

**Fichier à créer** : `backend/src/Controller/MecanicienController.php`

```php
<?php
namespace App\Controller;

use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mecanicien')]
class MecanicienController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Retourne la fiche du mécanicien connecté.
     * Retourne 404 avec message clair si pas de Mecanicien lié au User.
     */
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $mecanicien = $this->em->getRepository(Mecanicien::class)
            ->findOneBy(['userId' => $user->getId()]);

        if (!$mecanicien) {
            return $this->json([
                'error' => 'Aucun profil mécanicien lié à ce compte.',
                'code' => 'MECANICIEN_NOT_LINKED',
                'hint' => 'Un administrateur doit lier ce compte à un mécanicien dans la gestion des utilisateurs.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'         => $mecanicien->getId(),
            'nom'        => $mecanicien->getNom(),
            'prenom'     => $mecanicien->getPrenom(),
            'specialites'=> $mecanicien->getSpecialites(),
            'couleur'    => $mecanicien->getCouleur(),
            'userId'     => $mecanicien->getUserId(),
            'atelierId'  => $mecanicien->getAtelierId(),
            'isActive'   => $mecanicien->getIsActive(),
        ]);
    }

    /**
     * RDVs du jour du mécanicien connecté — avec OR et essai routier inclus.
     * Remplace GET /api/rendez-vous/mecanicien (gardé pour compat).
     */
    #[Route('/me/rdvs', methods: ['GET'])]
    public function myRdvs(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $mecanicien = $this->em->getRepository(Mecanicien::class)
            ->findOneBy(['userId' => $user->getId()]);

        if (!$mecanicien) {
            return $this->json([
                'error' => 'Aucun profil mécanicien lié.',
                'code'  => 'MECANICIEN_NOT_LINKED',
            ], Response::HTTP_NOT_FOUND);
        }

        $date = $request->query->get('date', date('Y-m-d'));

        $rdvs = $this->em->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.ordresReparation', 'or_', 'WITH', "or_.typeOr = 'initial'")
            ->leftJoin('r.essaiRoutier', 'er')
            ->addSelect('or_', 'er')
            ->where('r.mecanicien = :meca')
            ->andWhere('r.dateRdv = :date')
            ->setParameter('meca', $mecanicien)
            ->setParameter('date', new \DateTime($date))
            ->orderBy('r.heureRdv', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn(RendezVous $r) => $this->flattenRdvForMecanicien($r), $rdvs);

        return $this->json($data);
    }

    /**
     * Sauvegarde partielle du rapport mécanicien (checkup + notes) sur un OR.
     * Ne touche PAS au commentaire RDV.
     */
    #[Route('/me/rapport/{orId}', methods: ['PATCH'])]
    public function saveRapport(int $orId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $mecanicien = $this->em->getRepository(Mecanicien::class)
            ->findOneBy(['userId' => $user->getId()]);

        if (!$mecanicien) {
            return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_FORBIDDEN);
        }

        $or = $this->em->getRepository(OrdreReparation::class)->find($orId);
        if (!$or) {
            return $this->json(['error' => 'OR not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que ce mécanicien est bien assigné à ce RDV
        $rdv = $or->getRendezVous();
        if ($rdv->getMecanicien()?->getId() !== $mecanicien->getId()) {
            return $this->json(['error' => 'Non autorisé sur cet OR'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Mise à jour notes mécanicien (NE TOUCHE PAS à rdv.commentaire)
        if (array_key_exists('mechanic_notes', $data)) {
            $or->setMechanicNotes($data['mechanic_notes']);
        }

        // Mise à jour checkup mécanicien
        if (array_key_exists('mechanic_checkup', $data) && is_array($data['mechanic_checkup'])) {
            $or->setMechanicCheckup($data['mechanic_checkup']);
        }

        $this->em->flush();

        return $this->json([
            'id'                       => $or->getId(),
            'mechanic_notes'           => $or->getMechanicNotes(),
            'mechanic_checkup'         => $or->getMechanicCheckup(),
            'mechanic_notes_updated_at'=> $or->getMechanicNotesUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function flattenRdvForMecanicien(RendezVous $r): array
    {
        $client  = $r->getClient();
        $vehicule = $r->getVehicule();
        $pont    = $r->getPont();
        $essai   = $r->getEssaiRoutier();

        // OR initial seulement
        $orInitial = null;
        foreach ($r->getOrdresReparation() as $or) {
            if ($or->getTypeOr() === 'initial') {
                $orInitial = $or;
                break;
            }
        }

        // Données réception depuis OR (pas depuis etatVehicule du RDV qui est redondant)
        $etatReception = null;
        if ($orInitial?->getEtatVehicule()) {
            $decoded = json_decode($orInitial->getEtatVehicule(), true);
            if (is_array($decoded)) {
                $etatReception = [
                    'priority'     => $decoded['priority'] ?? null,
                    'fuel_level'   => $decoded['fuel_level'] ?? null,
                    'points'       => $decoded['points'] ?? [],
                    'body_damages' => $decoded['body_damages'] ?? [],
                    'observations' => $decoded['observations'] ?? null,
                ];
            }
        }

        return [
            // Identité RDV
            'id'                     => $r->getId(),
            'date_rdv'               => $r->getDateRdv()->format('Y-m-d'),
            'heure_debut'            => $r->getHeureRdv()->format('H:i'),
            'type_intervention'      => $r->getTypeIntervention(),
            'commentaire_client'     => $r->getCommentaire(), // lecture seule pour le méca
            'statut'                 => $r->getStatut(),
            'status'                 => $r->getStatut(),
            // Chrono
            'temps_estime'           => $r->getTempsEstime(),
            'temps_effectif_minutes' => $r->getTempsEffectifMinutes(),
            'heure_debut_travail'    => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'heure_fin_travail'      => $r->getHeureFinTravail()?->format('Y-m-d H:i:s'),
            // Client (lecture seule, pas d'email/adresse inutiles)
            'client_nom'             => $client ? ($client->getPrenom() . ' ' . $client->getNom()) : null,
            'client_telephone'       => $client?->getTelephone(),
            // Véhicule
            'vehicule_info'          => $vehicule ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? '')) : null,
            'vehicule_plaque'        => $vehicule?->getPlaque(),
            'vehicule_type'          => $vehicule?->getTypeMoto(),
            'km_reception'           => $r->getKilometrage(),
            // Pont
            'pont_nom'               => $pont?->getNom(),
            // OR
            'or_id'                  => $orInitial?->getId(),
            'or_signe'               => $orInitial?->getSignatureClient() !== null,
            'or_mechanic_notes'      => $orInitial?->getMechanicNotes(),
            'or_mechanic_checkup'    => $orInitial?->getMechanicCheckup(),
            // Données réception (lecture seule pour le méca)
            'etat_reception'         => $etatReception,
            // Essai routier
            'essai_routier_id'       => $essai?->getId(),
            'essai_routier_statut'   => $essai?->getStatut(),
            'essai_routier_valide'   => $essai?->isValide() ?? false,
            // Token suivi
            'token_suivi'            => $r->getTokenSuivi(),
        ];
    }
}
```

---

### 2.8 Endpoint `POST /api/mecanicien/me/essai-routier`

Ajouter dans `MecanicienController` :

```php
/**
 * Crée ou met à jour l'essai routier d'un RDV.
 * Le mécanicien connecté doit être assigné au RDV.
 */
#[Route('/me/essai-routier', methods: ['POST'])]
public function createEssai(Request $request): JsonResponse
{
    $user = $this->getUser();
    $mecanicien = $this->em->getRepository(Mecanicien::class)
        ->findOneBy(['userId' => $user->getId()]);

    if (!$mecanicien) {
        return $this->json(['error' => 'MECANICIEN_NOT_LINKED'], Response::HTTP_FORBIDDEN);
    }

    $data = json_decode($request->getContent(), true) ?? [];
    $rdvId = $data['rdv_id'] ?? null;

    if (!$rdvId) {
        return $this->json(['error' => 'rdv_id requis'], Response::HTTP_BAD_REQUEST);
    }

    $rdv = $this->em->getRepository(RendezVous::class)->find($rdvId);
    if (!$rdv) {
        return $this->json(['error' => 'RDV not found'], Response::HTTP_NOT_FOUND);
    }

    // Vérifier que ce mécanicien est assigné à ce RDV
    if ($rdv->getMecanicien()?->getId() !== $mecanicien->getId()) {
        return $this->json(['error' => 'Non autorisé sur ce RDV'], Response::HTTP_FORBIDDEN);
    }

    // Vérifier que le RDV est en_cours ou en_pause
    if (!in_array($rdv->getStatut(), ['en_cours', 'en_pause', 'termine'])) {
        return $this->json([
            'error' => "Essai routier impossible depuis le statut '{$rdv->getStatut()}'",
        ], Response::HTTP_CONFLICT);
    }

    // Créer ou récupérer l'essai existant
    $essai = $rdv->getEssaiRoutier();
    if (!$essai) {
        $essai = new EssaiRoutier();
        $essai->setRendezVous($rdv);
        $essai->setMecanicien($mecanicien);
        $essai->setKmDebut($rdv->getKilometrage() ?? 0);
        $this->em->persist($essai);
    }

    // Mise à jour des champs
    if (isset($data['km_debut'])) $essai->setKmDebut((int) $data['km_debut']);
    if (isset($data['km_fin']))   $essai->setKmFin((int) $data['km_fin']);
    if (isset($data['checkpoints']) && is_array($data['checkpoints'])) {
        $essai->setCheckpoints($data['checkpoints']);
    }
    if (isset($data['observations'])) $essai->setObservations($data['observations']);

    // Validation : km_fin requis + au moins 1 checkpoint pour valider
    if (isset($data['valider']) && $data['valider'] === true) {
        if ($essai->getKmFin() === null) {
            return $this->json(['error' => 'km_fin obligatoire pour valider'], Response::HTTP_BAD_REQUEST);
        }
        $checkpoints = $essai->getCheckpoints();
        $done = array_filter($checkpoints, fn($c) => in_array($c['statut'] ?? '', ['ok', 'nok', 'na']));
        if (count($done) < 5) {
            return $this->json([
                'error' => 'Au moins 5 checkpoints doivent être renseignés pour valider.',
            ], Response::HTTP_BAD_REQUEST);
        }
        // Détecter anomalie
        $hasAnomalie = !empty(array_filter($checkpoints, fn($c) => ($c['statut'] ?? '') === 'nok'));
        $essai->setStatut($hasAnomalie ? 'anomalie_detectee' : 'valide');
        $essai->setValidatedAt(new \DateTime());
    }

    $this->em->flush();

    return $this->json([
        'id'           => $essai->getId(),
        'rdv_id'       => $rdv->getId(),
        'km_debut'     => $essai->getKmDebut(),
        'km_fin'       => $essai->getKmFin(),
        'checkpoints'  => $essai->getCheckpoints(),
        'observations' => $essai->getObservations(),
        'statut'       => $essai->getStatut(),
        'valide'       => $essai->isValide(),
        'validated_at' => $essai->getValidatedAt()?->format('Y-m-d H:i:s'),
    ]);
}
```

---

### 2.9 Mise à jour `RdvWorkflowListener`

**Fichier** : `backend/src/EventListener/RdvWorkflowListener.php`

Ajouter les listeners pour les nouvelles transitions :

```php
#[AsEventListener(event: 'workflow.rendez_vous.completed.confirmer')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.terminer')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.mettre_en_attente_pieces')]
#[AsEventListener(event: 'workflow.rendez_vous.completed.no_show')]
class RdvWorkflowListener
{
    public function __construct(private MessageBusInterface $bus) {}

    public function __invoke(CompletedEvent $event): void
    {
        $rdv = $event->getSubject();
        $transition = $event->getTransition()->getName();

        match ($transition) {
            'confirmer'               => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'confirmation')),
            'terminer'                => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'travaux_termines')),
            'mettre_en_attente_pieces'=> $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'attente_pieces')),
            'no_show'                 => $this->bus->dispatch(new SendRappelMessage($rdv->getId(), 'no_show')),
            default                   => null,
        };
    }
}
```

---

### 2.10 Fix index sur `Mecanicien.userId`

**Migration SQL :**

```sql
-- Index pour la recherche fréquente User → Mecanicien
CREATE UNIQUE INDEX idx_mecanicien_user_id ON mecaniciens (user_id)
    WHERE user_id IS NOT NULL;
```

**Doctrine** — ajouter sur l'entité :

```php
// Dans Mecanicien.php :
#[ORM\Column(nullable: true)]
#[ORM\Index(name: 'idx_mecanicien_user_id', columns: ['user_id'])]
private ?int $userId = null;
```

---

### 2.11 Migration SQL complète du bloc

```sql
-- 1. Nouveaux champs RendezVous
ALTER TABLE rendez_vous 
    ADD COLUMN motif_annulation VARCHAR(50) NULL,
    ADD COLUMN commentaire_annulation TEXT NULL;

-- 2. Nouveaux champs OrdreReparation
ALTER TABLE ordres_reparation 
    ADD COLUMN mechanic_notes TEXT NULL,
    ADD COLUMN mechanic_notes_updated_at TIMESTAMP NULL,
    ADD COLUMN mechanic_checkup TEXT NOT NULL DEFAULT '{}',
    ADD COLUMN mechanic_checkup_updated_at TIMESTAMP NULL;

-- 3. Nouvelle table EssaiRoutier
CREATE TABLE essais_routiers (
    id                SERIAL PRIMARY KEY,
    atelier_id        INT NULL,
    rendez_vous_id    INT NOT NULL UNIQUE,
    mecanicien_id     INT NOT NULL,
    km_debut          INT NOT NULL,
    km_fin            INT NULL,
    checkpoints       TEXT NOT NULL DEFAULT '[]',
    observations      TEXT NULL,
    statut            VARCHAR(30) NOT NULL DEFAULT 'en_cours',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    validated_at      TIMESTAMP NULL,
    CONSTRAINT fk_essai_rdv    FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id) ON DELETE CASCADE,
    CONSTRAINT fk_essai_meca   FOREIGN KEY (mecanicien_id) REFERENCES mecaniciens(id)
);

-- 4. Index mecanicien.user_id
CREATE UNIQUE INDEX idx_mecanicien_user_id 
    ON mecaniciens (user_id) 
    WHERE user_id IS NOT NULL;
```

---

## 3. Récapitulatif des endpoints nouveaux / modifiés

| Méthode | Route | Statut | Description |
|---------|-------|--------|-------------|
| `GET` | `/api/mecanicien/me` | **NOUVEAU** | Fiche du mécanicien connecté, erreur claire si pas lié |
| `GET` | `/api/mecanicien/me/rdvs?date=` | **NOUVEAU** | RDVs du jour avec OR + essai, sans filtre inutile |
| `PATCH` | `/api/mecanicien/me/rapport/{orId}` | **NOUVEAU** | Sauvegarde checkup + notes, ne touche pas `rdv.commentaire` |
| `POST` | `/api/mecanicien/me/essai-routier` | **NOUVEAU** | Créer/mettre à jour/valider un essai routier |
| `POST` | `/api/rendez-vous/{id}/transition/mettre_en_pause` | **NOUVEAU** | Chrono pause, cumule temps effectif |
| `POST` | `/api/rendez-vous/{id}/transition/reprendre` | **NOUVEAU** | Reprise après pause |
| `POST` | `/api/rendez-vous/{id}/transition/mettre_en_attente_pieces` | **NOUVEAU** | Moto immobilisée en attente pièces |
| `POST` | `/api/rendez-vous/{id}/transition/reprendre_apres_pieces` | **NOUVEAU** | Retour en cours après arrivée pièces |
| `POST` | `/api/rendez-vous/{id}/transition/mettre_en_gardiennage` | **NOUVEAU** | Moto en gardiennage |
| `POST` | `/api/rendez-vous/{id}/transition/no_show` | **NOUVEAU** | Client non présenté |
| `POST` | `/api/rendez-vous/{id}/transition/terminer` | **MODIFIÉ** | Exige essaiRoutier.isValide() = true |
| `POST` | `/api/rendez-vous/{id}/transition/annuler` | **MODIFIÉ** | Accepte motif + commentaire, élargi aux nouveaux statuts |
| `GET` | `/api/rendez-vous/mecanicien` | **DÉPRÉCIÉ** | Garder pour compat front existant, pointer vers `/me/rdvs` |

---

## 4. Règles métier à respecter à l'implémentation

1. **Le mécanicien ne peut pas modifier `rdv.commentaire`** — c'est la description du problème du réceptionniste. Les notes mécanicien vont exclusivement dans `or.mechanic_notes`.

2. **`start_travail` exige un OR signé** — cette règle back est correcte et doit être conservée. C'est le front qui doit être corrigé pour ne plus essayer de créer un OR sans signature.

3. **`terminer` exige un `EssaiRoutier` validé** — zéro exception. Si l'essai détecte une anomalie (`statut = anomalie_detectee`), `terminer` est quand même autorisé mais l'OR doit mentionner l'anomalie (via `mechanic_notes`).

4. **Le mécanicien n'a accès qu'à ses propres RDVs** — `GET /mecanicien/me/rdvs` filtre strictement par `mecanicien.userId = currentUser.id`. Le TenantFilter gère l'isolation atelier par dessus.

5. **`km_debut` de l'essai = `rdv.kilometrage`** posé à la réception. Si absent, le mécanicien doit le saisir manuellement avant validation.

6. **Statut `anomalie_detectee`** ≠ blocage : l'essai est considéré "valide" au sens workflow (`isValide()` retourne `true`) même si une anomalie est détectée. La distinction sert uniquement à l'audit et à la génération du rapport.

---

## 5. Fichiers à créer / modifier

| Action | Fichier |
|--------|---------|
| **CRÉER** | `src/Entity/EssaiRoutier.php` |
| **CRÉER** | `src/Controller/MecanicienController.php` |
| **CRÉER** | `migrations/VxxxxEspaceMecanicien.php` (migration Doctrine) |
| **MODIFIER** | `config/packages/workflow.yaml` |
| **MODIFIER** | `src/Entity/RendezVous.php` (+motifAnnulation, +commentaireAnnulation, +essaiRoutier relation) |
| **MODIFIER** | `src/Entity/OrdreReparation.php` (+mechanicNotes, +mechanicCheckup) |
| **MODIFIER** | `src/Entity/Mecanicien.php` (+index userId) |
| **MODIFIER** | `src/Controller/RendezVousController.php` (transitions pause/reprendre/terminer/no_show, flattenRdv enrichi) |
| **MODIFIER** | `src/EventListener/RdvWorkflowListener.php` (nouvelles transitions) |

