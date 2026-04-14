<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'rappels_email')]
#[ApiResource(
    normalizationContext: ['groups' => ['rappel:read']],
)]
class RappelEmail
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['rappel:read'])] private ?int $id = null;
    #[ORM\Column] private int $atelierId;
    #[ORM\ManyToOne(targetEntity: RendezVous::class)] #[ORM\JoinColumn(name: 'rdv_id', nullable: false)] #[Groups(['rappel:read'])] private RendezVous $rdv;
    #[ORM\ManyToOne(targetEntity: Client::class)] #[ORM\JoinColumn(name: 'client_id', nullable: false)] #[Groups(['rappel:read'])] private Client $client;
    #[ORM\Column(length: 50)] #[Groups(['rappel:read'])] private string $typeRappel;
    #[ORM\Column(length: 200)] #[Groups(['rappel:read'])] private string $destinataire;
    #[ORM\Column(length: 500, nullable: true)] #[Groups(['rappel:read'])] private ?string $sujet = null;
    #[ORM\Column(length: 30, options: ['default' => 'programme'])] #[Groups(['rappel:read'])] private string $statut = 'programme';
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['rappel:read'])] private ?string $erreur = null;
    #[ORM\Column(type: 'datetime')] #[Groups(['rappel:read'])] private \DateTimeInterface $dateEnvoiPrevu;
    #[ORM\Column(type: 'datetime', nullable: true)] #[Groups(['rappel:read'])] private ?\DateTimeInterface $dateEnvoiReel = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['rappel:read'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getRdv(): RendezVous { return $this->rdv; }
    public function setRdv(RendezVous $v): static { $this->rdv = $v; return $this; }
    public function getClient(): Client { return $this->client; }
    public function setClient(Client $v): static { $this->client = $v; return $this; }
    public function getTypeRappel(): string { return $this->typeRappel; }
    public function setTypeRappel(string $v): static { $this->typeRappel = $v; return $this; }
    public function getDestinataire(): string { return $this->destinataire; }
    public function setDestinataire(string $v): static { $this->destinataire = $v; return $this; }
    public function getSujet(): ?string { return $this->sujet; }
    public function setSujet(?string $v): static { $this->sujet = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getErreur(): ?string { return $this->erreur; }
    public function setErreur(?string $v): static { $this->erreur = $v; return $this; }
    public function getDateEnvoiPrevu(): \DateTimeInterface { return $this->dateEnvoiPrevu; }
    public function setDateEnvoiPrevu(\DateTimeInterface $v): static { $this->dateEnvoiPrevu = $v; return $this; }
    public function getDateEnvoiReel(): ?\DateTimeInterface { return $this->dateEnvoiReel; }
    public function setDateEnvoiReel(?\DateTimeInterface $v): static { $this->dateEnvoiReel = $v; return $this; }
}
