<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class EventCompilationService
{
    /**
     * @param iterable<EventRetrievalInterface> $eventRetrievals
     */
    public function __construct(
        #[AutowireIterator('app.EventRetrieval')]
        private readonly iterable $eventRetrievals,
    ) {
    }

    /**
     * @return iterable<EventValidationDTO>
     */
    public function collectEvents(): iterable
    {
        foreach ($this->eventRetrievals as $eventRetrieval) {
            yield from $this->collectEventsFromEventRetrieval($eventRetrieval);
        }
    }

    /**
     * @return iterable<EventValidationDTO>
     */
    private function collectEventsFromEventRetrieval(EventRetrievalInterface $eventRetrieval): iterable
    {
        foreach ($eventRetrieval->retrieveEvents() as $eventValidationDTO) {
            yield $eventValidationDTO;
        }
    }
}
