<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class TestEntity implements TestEntityInterface
{
    private ?int $id = null;
    private ?string $name = null;
    private ?string $email = null;
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $updatedAt = null;
    private ?float $price = null;
    private array $tags = [];
    private bool $isActive = true;
    private Collection $relatedEntities;

    public function __construct()
    {
        $this->relatedEntities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getRelatedEntities(): Collection
    {
        return $this->relatedEntities;
    }

    public function addRelatedEntity(TestEntityInterface $entity): void
    {
        if (!$this->relatedEntities->contains($entity)) {
            $this->relatedEntities->add($entity);
        }
    }

    public function removeRelatedEntity(TestEntityInterface $entity): void
    {
        $this->relatedEntities->removeElement($entity);
    }
}
