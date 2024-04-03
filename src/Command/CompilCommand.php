<?php

declare(strict_types=1);

namespace App\Command;

use App\Compil\CompilService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compil',
    description: 'Add a short description for your command',
)]
class CompilCommand extends Command
{
    public function __construct(
        private CompilService $compilService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->compilService->compil();

        return Command::SUCCESS;
    }
}
