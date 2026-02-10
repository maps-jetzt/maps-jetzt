<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: '"user"')]
#[ORM\HasLifecycleCallbacks]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private int $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 64, unique: true)]
    private string $apiKey;

    #[ORM\Column()]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Heatmap> */
    #[ORM\OneToMany(targetEntity: Heatmap::class, mappedBy: 'user')]
    private Collection $heatmaps;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->heatmaps = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Heatmap> */
    public function getHeatmaps(): Collection
    {
        return $this->heatmaps;
    }
}
