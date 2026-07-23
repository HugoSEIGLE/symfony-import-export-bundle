<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Company;
use Doctrine\Persistence\ManagerRegistry;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyExportController
{
    #[Route('/companies/export', name: 'company_export', methods: ['GET'])]
    public function __invoke(ManagerRegistry $doctrine, ExporterInterface $exporter): StreamedResponse
    {
        $query = $doctrine->getRepository(Company::class)
            ->createQueryBuilder('company')
            ->orderBy('company.name', 'ASC')
            ->getQuery();

        return $exporter->export(
            $query,
            ['getName', 'getEmail', 'isActive'],
            'companies',
            ExporterInterface::CSV,
        );
    }
}
