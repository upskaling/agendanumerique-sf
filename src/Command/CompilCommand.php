<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\EventValidationDTO;
use App\EventRetrieval\EventRetrievalInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:compil',
    description: 'To fetch events',
)]
class CompilCommand extends Command
{
    /**
     * @param iterable<EventRetrievalInterface> $compils
     */
    public function __construct(
        #[TaggedIterator('app.EventRetrieval')]
        private readonly iterable $compils,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validation,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var EventValidationDTO[] $eventValidationDTOList */
        $eventValidationDTOList = [];
        foreach ($this->compils as $compil) {
            $eventValidationDTOList = array_merge($eventValidationDTOList, $compil->retrieveEvents());
        }

        /** @var EventValidationDTO $eventValidationDTO */
        foreach ($eventValidationDTOList as $eventValidationDTO) {
            $errors = $this->validation->validate($eventValidationDTO);

            if (0 !== \count($errors)) {
                continue;
            }

            $event = $eventValidationDTO->toEntity();
            // vérifiez que le slug et unique

            $errors = $this->validation->validate($event);

            if (0 !== \count($errors)) {
                continue;
            }
            $event->setPublished(new \DateTimeImmutable());

            $this->entityManager->persist($event);

            $io->success(sprintf(
                'Event "%s" has been created',
                $event->getTitle(),
            ));
        }
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
