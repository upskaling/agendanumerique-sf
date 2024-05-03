<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.EventRetrieval')]
interface EventRetrievalInterface
{
    /**
     * @return EventValidationDTO[]
     */
    public function retrieveEvents(): array;
}
