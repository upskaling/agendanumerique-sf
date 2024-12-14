<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\EventValidationDTO;
use App\Entity\Event;
use App\EventRetrieval\EventCompilationService;
use App\Repository\EventRepository;
use App\Validator\EventValidationDTOSlugUnique;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:compil',
    description: 'To fetch events',
)]
class CompilCommand extends Command
{
    public function __construct(
        private readonly EventCompilationService $eventCompilationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $eventValidationDTOs = $this->eventCompilationService->collectEvents();
            $this->processEvents($eventValidationDTOs, $io);

            $this->entityManager->flush();
            $io->success('Event compilation completed successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Event compilation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @param iterable<EventValidationDTO> $eventValidationDTOs
     */
    private function processEvents(iterable $eventValidationDTOs, SymfonyStyle $io): void
    {
        foreach ($eventValidationDTOs as $eventValidationDTO) {
            $this->validateAndPersistEvent($eventValidationDTO, $io);
        }
    }

    private function validateAndPersistEvent(EventValidationDTO $eventValidationDTO, SymfonyStyle $io): void
    {
        $validationErrors = $this->validator->validate($eventValidationDTO);
        if ($validationErrors->count() > 0) {
            $this->handleEventValidationErrors($eventValidationDTO, $validationErrors, $io);

            return;
        }

        $this->createOrUpdateEvent($eventValidationDTO, $io);
    }

    private function handleEventValidationErrors(
        EventValidationDTO $eventValidationDTO,
        ConstraintViolationListInterface $validationErrors,
        SymfonyStyle $io,
    ): void {
        $firstError = $validationErrors->get(0);

        if ($this->isSlugUniqueConstraintViolation($firstError)) {
            $this->handleDuplicateSlug($eventValidationDTO, $io);
        }
    }

    private function isSlugUniqueConstraintViolation(ConstraintViolationInterface $error): bool
    {
        return $error->getConstraint() instanceof EventValidationDTOSlugUnique;
    }

    private function handleDuplicateSlug(EventValidationDTO $eventValidationDTO, SymfonyStyle $io): void
    {
        $existingEvent = $this->eventRepository->findOneBy(['slug' => $eventValidationDTO->getSlug()]);

        if ($existingEvent) {
            $this->updateExistingEvent($existingEvent, $eventValidationDTO);
            $io->note(\sprintf('Event "%s" already exists. Updated existing event.', $existingEvent->getTitle()));
        }
    }

    private function createOrUpdateEvent(EventValidationDTO $eventValidationDTO, SymfonyStyle $io): void
    {
        $event = $eventValidationDTO->toEntity();
        $entityValidationErrors = $this->validator->validate($event);

        if (0 === $entityValidationErrors->count()) {
            $event->setPublished(new \DateTimeImmutable());
            $this->entityManager->persist($event);

            $io->success(\sprintf('Event "%s" has been created', $event->getTitle()));
        }
    }

    private function updateExistingEvent(Event $existingEvent, EventValidationDTO $newEventData): void
    {
        if (null !== $newEventData->getTitle()) {
            $existingEvent->setTitle($newEventData->getTitle());
        }

        if (null !== $newEventData->getDescription()) {
            $existingEvent->setDescription($newEventData->getDescription());
        }

        if (null !== $newEventData->getStartAt()) {
            $existingEvent->setStartAt($newEventData->getStartAt());
        }

        if (null !== $newEventData->getEndAt()) {
            $existingEvent->setEndAt($newEventData->getEndAt());
        }

        if (null !== $newEventData->getImage()) {
            $existingEvent->setImage($newEventData->getImage());
        }

        $this->validateUpdatedEvent($existingEvent);
        $this->entityManager->persist($existingEvent);
    }

    private function validateUpdatedEvent(Event $event): void
    {
        $validationErrors = $this->validator->validate($event);
        if ($validationErrors->count() > 0) {
            throw new \InvalidArgumentException((string) $validationErrors);
        }
    }
}
