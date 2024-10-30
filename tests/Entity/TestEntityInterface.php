<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Entity;

use Doctrine\Common\Collections\Collection;

interface TestEntityInterface
{
    public function getId(): ?int;

    public function setId(int $id): void;

    public function getName(): ?string;

    public function setName(string $name): void;

    public function getEmail(): ?string;

    public function setEmail(string $email): void;

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(\DateTimeInterface $createdAt): void;

    public function getUpdatedAt(): ?\DateTimeInterface;

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void;

    public function getPrice(): ?float;

    public function setPrice(float $price): void;

    public function getTags(): array;

    public function setTags(array $tags): void;

    public function isActive(): bool;

    public function setActive(bool $isActive): void;

    public function getRelatedEntities(): Collection;

    public function addRelatedEntity(TestEntityInterface $entity): void;

    public function removeRelatedEntity(TestEntityInterface $entity): void;
}
