<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyImportType;
use Doctrine\ORM\EntityManagerInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImportError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;
use function count;

final class CompanyImportController extends AbstractController
{
    #[Route('/companies/import', name: 'company_import', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ImporterInterface $importer,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'A CSV or XLSX file is required.'], 400);
        }

        $result = $importer->import($file, Company::class, CompanyImportType::class);
        if (!$result->isValid()) {
            return $this->json([
                'errors' => array_map(static fn (ImportError $error): array => [
                    'row' => $error->row,
                    'field' => $error->field,
                    'message' => $error->message,
                    'value' => $error->value,
                ], $result->getErrors()),
            ], 422);
        }

        $entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($result): void {
            foreach ($result->getCreatedEntities() as $company) {
                $entityManager->persist($company);
            }
        });

        return $this->json([
            'created' => count($result->getCreatedEntities()),
            'updated' => count($result->getUpdatedEntities()),
        ]);
    }
}
