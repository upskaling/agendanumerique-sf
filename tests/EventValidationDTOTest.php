<?php

declare(strict_types=1);

namespace App\Tests;

use App\DTO\EventValidationDTO;
use App\Entity\Event;
use App\Entity\PostalAddress;
use PHPUnit\Framework\TestCase;

class EventValidationDTOTest extends TestCase
{
    public function testEventValidationDTOToEntity(): void
    {
        // Initialisation des propriétés de EventValidationDTO
        $dto = new EventValidationDTO('propose-new');
        $dto->setTitle('AFUP Day poitiers');
        $dto->setOrganizer('AFUP');
        $dto->setLink('https://afup.org/');
        $dto->setDescription('AFUP Day poitiers');
        $startAt = new \DateTimeImmutable('2023-01-01');
        $dto->setStartAt($startAt);
        $endAt = new \DateTimeImmutable('2023-01-02');
        $dto->setEndAt($endAt);
        $dto->setImage('https://afup.org/');
        $dto->setLocation($this->createMock(PostalAddress::class));

        // Appel de la méthode toEntity
        $event = $dto->toEntity();

        // Vérifications des propriétés de Event
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('AFUP Day poitiers', $event->getTitle());
        $this->assertSame('AFUP', $event->getOrganizer());
        $this->assertSame('https://afup.org/', $event->getLink());
        $this->assertSame('AFUP Day poitiers', $event->getDescription());
        $this->assertSame($startAt, $event->getStartAt());
        $this->assertSame($endAt, $event->getEndAt());
        $this->assertSame('https://afup.org/', $event->getImage());
        $this->assertSame($dto->getLocation(), $event->getLocation());
        $this->assertSame('afup-afup-day-poitiers', $event->getSlug());
    }

    // test avec des valeurs nulles
    public function testEventValidationDTOToEntityWithSlug(): void
    {
        // Initialisation des propriétés de EventValidationDTO
        $dto = new EventValidationDTO('propose-new');

        // Appel de la méthode toEntity
        $event = $dto->toEntity();

        // Vérifications des propriétés de Event
        $this->assertInstanceOf(Event::class, $event);
    }
}
