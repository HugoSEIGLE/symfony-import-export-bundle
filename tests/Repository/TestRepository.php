<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class TestRepository extends EntityRepository implements TestRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }
}
