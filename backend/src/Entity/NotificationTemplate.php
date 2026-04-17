<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_templates')]
#[ORM\UniqueConstraint(name: 'uniq_template_atelier_code_channel', columns: ['atelier_id', 'code', 'channel'])]
class NotificationTemplate
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $atelierId;

    #[ORM\Column(length: 100)]
    private string $code; // rdv_confirme | demande_complementaire | ...

    #[ORM\Column(length: 20)]
    private string $channel; // email | sms | push

    #[ORM\Column(length: 200)]
    private string $libelle;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $sujet = null;

    #[ORM\Column(type: 'text')]
    private string $corps; // Twig pour email, string interpolation pour SMS

    #[ORM\Column(type: 'json')]
    private array $variables = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): int { return $this->atelierId; }
    public function setAtelierId(int $v): static { $this->atelierId = $v; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): static { $this->code = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $v): static { $this->libelle = $v; return $this; }
    public function getSujet(): ?string { return $this->sujet; }
    public function setSujet(?string $v): static { $this->sujet = $v; return $this; }
    public function getCorps(): string { return $this->corps; }
    public function setCorps(string $v): static { $this->corps = $v; return $this; }
    public function getVariables(): array { return $this->variables; }
    public function setVariables(array $v): static { $this->variables = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }

    /**
     * Render the template body with given variables (simple string interpolation for SMS, Twig-like for email)
     */
    public function render(array $data): string
    {
        $body = $this->corps;
        foreach ($data as $key => $value) {
            $body = str_replace('{{ ' . $key . ' }}', (string) $value, $body);
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }
        return $body;
    }

    public function renderSubject(array $data): ?string
    {
        if ($this->sujet === null) {
            return null;
        }
        $subject = $this->sujet;
        foreach ($data as $key => $value) {
            $subject = str_replace('{{ ' . $key . ' }}', (string) $value, $subject);
            $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
        }
        return $subject;
    }
}
