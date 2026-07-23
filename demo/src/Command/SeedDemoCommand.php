<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed-demo', description: 'Loads the demo companies when the table is empty.')]
final class SeedDemoCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (0 < $this->entityManager->getRepository(Company::class)->count([])) {
            $output->writeln('Demo companies already exist.');

            return Command::SUCCESS;
        }

        $companies = [
            ['Acme Studio', 'hello@acme.test', 'Design', 24, true],
            ['Northstar Labs', 'team@northstar.test', 'Software', 86, true],
            ['Greenhouse Co.', 'contact@greenhouse.test', 'Agriculture', 41, true],
            ['Paper & Pixel', 'studio@paperpixel.test', 'Media', 12, false],
            ['Lumen Works', 'hello@lumen.test', 'Energy', 63, true],
        ];

        foreach ($companies as [$name, $email, $industry, $employees, $active]) {
            $company = (new Company())
                ->setName($name)
                ->setEmail($email)
                ->setIndustry($industry)
                ->setEmployees($employees)
                ->setActive($active);
            $this->entityManager->persist($company);
        }

        $this->entityManager->flush();
        $output->writeln('Loaded 5 demo companies.');

        return Command::SUCCESS;
    }
}
