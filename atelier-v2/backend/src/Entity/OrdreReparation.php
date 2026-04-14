<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'ordres_reparation')]
#[ApiResource(
    shortName: 'ordres-reparation',
    normalizationContext: ['groups' => ['ordre:read']],
    denormalizationContext: ['groups' => ['ordre:write']],
    operations: [
        new GetCollection(uriTemplate: '/ordres-reparation'),
        new Get(uriTemplate: '/ordres-reparation/{id}'),
        new Post(uriTemplate: '/ordres-reparation'),
        new Put(uriTemplate: '/ordres-reparation/{id}'),
        new Delete(uriTemplate: '/ordres-reparation/{id}'),
    ],
)]
class OrdreReparation
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['ordre:read', 'rdv:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'ordresReparation')] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private RendezVous $rendezVous;

    #[ORM\Column(length: 50)]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $numeroOr;

    #[ORM\Column(length: 50, options: ['default' => 'initial'])]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $typeOr = 'initial';

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?int $kilometrage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $etatVehicule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $travaux = null;

    #[ORM\ManyToOne(targetEntity: DemandeTravauxSupp::class)] #[ORM\JoinColumn(name: 'demande_travaux_supp_id', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?DemandeTravauxSupp $demandeTravauxSupp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signatureClient = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['ordre:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getNumeroOr(): string { return $this->numeroOr; }
    public function setNumeroOr(string $v): static { $this->numeroOr = $v; return $this; }
    public function getTypeOr(): string { return $this->typeOr; }
    public function setTypeOr(string $v): static { $this->typeOr = $v; return $this; }
    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $v): static { $this->kilometrage = $v; return $this; }
    public function getEtatVehicule(): ?string { return $this->etatVehicule; }
    public function setEtatVehicule(?string $v): static { $this->etatVehicule = $v; return $this; }
    public function getTravaux(): ?string { return $this->travaux; }
    public function setTravaux(?string $v): static { $this->travaux = $v; return $this; }
    public function getDemandeTravauxSupp(): ?DemandeTravauxSupp { return $this->demandeTravauxSupp; }
    public function setDemandeTravauxSupp(?DemandeTravauxSupp $v): static { $this->demandeTravauxSupp = $v; return $this; }
    public function getSignatureClient(): ?string { return $this->signatureClient; }
    public function setSignatureClient(?string $v): static { $this->signatureClient = $v; return $this; }
}
