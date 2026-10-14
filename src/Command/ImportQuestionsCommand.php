<?php

namespace App\Command;

use App\Service\QuestionBankImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:questions:import', description: 'Load topics and questions from content/*.yaml into the database')]
final class ImportQuestionsCommand
{
    public function __construct(private readonly QuestionBankImporter $importer)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $result = $this->importer->import();
        $io->success(sprintf('Topics: %d, questions: %d, deactivated: %d', $result['topics'], $result['questions'], $result['deactivated']));

        return Command::SUCCESS;
    }
}
