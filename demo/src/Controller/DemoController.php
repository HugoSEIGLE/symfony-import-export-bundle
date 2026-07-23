<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyImportType;
use Doctrine\ORM\EntityManagerInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\ExporterInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterTemplateInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

use function count;
use function sprintf;

final class DemoController extends AbstractController
{
    #[Route('/', name: 'app_demo', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('demo/index.html.twig', [
            'companies' => $entityManager->getRepository(Company::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/import', name: 'app_import', methods: ['POST'])]
    public function import(
        Request $request,
        ImporterInterface $importer,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('company-import', $request->request->getString('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToRoute('app_demo');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Choose a CSV or XLSX file to import.');

            return $this->redirectToRoute('app_demo');
        }

        $result = $importer->import($file, Company::class, CompanyImportType::class);
        if (!$result->isValid()) {
            foreach ($result->getErrors() as $error) {
                $field = null === $error->field ? '' : ' (' . $error->field . ')';
                $this->addFlash('error', 'Row ' . $error->row . $field . ': ' . $error->message);
            }

            return $this->redirectToRoute('app_demo');
        }

        foreach ($result->getCreatedEntities() as $company) {
            $entityManager->persist($company);
        }
        foreach ($result->getDeletedEntities() as $company) {
            $entityManager->remove($company);
        }
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Import complete: %d created, %d updated.',
            count($result->getCreatedEntities()),
            count($result->getUpdatedEntities()),
        ));

        return $this->redirectToRoute('app_demo');
    }

    #[Route('/template/{format}', name: 'app_template', requirements: ['format' => 'csv|xlsx'], methods: ['GET'])]
    public function template(string $format, ImporterTemplateInterface $templates): StreamedResponse
    {
        return $templates->getImportTemplate(Company::class, $format);
    }

    #[Route('/export/{format}', name: 'app_export', requirements: ['format' => 'csv|xlsx'], methods: ['GET'])]
    public function export(
        string $format,
        EntityManagerInterface $entityManager,
        ExporterInterface $exporter,
    ): StreamedResponse {
        $query = $entityManager->getRepository(Company::class)
            ->createQueryBuilder('company')
            ->orderBy('company.name', 'ASC')
            ->getQuery();

        return $exporter->export(
            $query,
            ['getName', 'getEmail', 'getIndustry', 'getEmployees', 'isActive'],
            'companies',
            $format,
        );
    }
}
