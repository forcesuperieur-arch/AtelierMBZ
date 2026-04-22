<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name: 'audit_logs')]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    security: "is_granted('ROLE_SUPER_ADMIN')",
    order: ['createdAt' => 'DESC'],
    paginationItemsPerPage: 50,
    paginationMaximumItemsPerPage: 200,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'username' => 'partial',
    'action' => 'exact',
    'entityType' => 'exact',
    'ipAddress' => 'partial',
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'action', 'entityType', 'username'])]
class AuditLog
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(nullable: true)] private ?int $userId = null;
    #[ORM\Column(length: 100, nullable: true)] private ?string $username = null;
    #[ORM\Column(length: 100)] private string $action;
    #[ORM\Column(length: 100, nullable: true)] private ?string $entityType = null;
    #[ORM\Column(nullable: true)] private ?int $entityId = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $details = null;
    #[ORM\Column(length: 50, nullable: true)] private ?string $ipAddress = null;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $v): static { $this->userId = $v; return $this; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $v): static { $this->username = $v; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $v): static { $this->entityType = $v; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $v): static { $this->entityId = $v; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $v): static { $this->details = $v; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): static { $this->ipAddress = $v; return $this; }
}
