<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Entity\Event;
use App\Utils\CalendarGeneratorIcs;
use Jsvrcek\ICS\CalendarExport;
use PHPUnit\Framework\TestCase;

class CalendarGeneratorIcsTest extends TestCase
{
    private CalendarGeneratorIcs $calendarGenerator;

    protected function setUp(): void
    {
        $this->calendarGenerator = new CalendarGeneratorIcs();
    }

    public function testGetCalendarWithEmptyArray(): void
    {
        $result = $this->calendarGenerator->getCalendar([]);

        $this->assertInstanceOf(CalendarExport::class, $result);
    }

    public function testGetCalendarWithOneEvent(): void
    {
        $event = $this->createMockEvent(
            'Test Event',
            'https://example.com/event',
            'Description of the event',
            new \DateTimeImmutable('2025-06-15 14:00:00'),
            new \DateTimeImmutable('2025-06-15 16:00:00')
        );

        $result = $this->calendarGenerator->getCalendar([$event]);

        $this->assertInstanceOf(CalendarExport::class, $result);
        $output = $result->getStream();
        $this->assertStringContainsString('Test Event', $output);
    }

    public function testGetCalendarWithMultipleEvents(): void
    {
        $event1 = $this->createMockEvent(
            'Event 1',
            'https://example.com/event1',
            'First event',
            new \DateTimeImmutable('2025-06-15 14:00:00'),
            new \DateTimeImmutable('2025-06-15 16:00:00')
        );

        $event2 = $this->createMockEvent(
            'Event 2',
            'https://example.com/event2',
            'Second event',
            new \DateTimeImmutable('2025-06-20 10:00:00'),
            new \DateTimeImmutable('2025-06-20 12:00:00')
        );

        $result = $this->calendarGenerator->getCalendar([$event1, $event2]);

        $this->assertInstanceOf(CalendarExport::class, $result);
        $output = $result->getStream();
        $this->assertStringContainsString('Event 1', $output);
        $this->assertStringContainsString('Event 2', $output);
    }

    public function testGetCalendarStripsHtmlTagsFromDescription(): void
    {
        $event = $this->createMockEvent(
            'Test Event',
            'https://example.com/event',
            '<p>Description with <strong>HTML</strong> tags</p>',
            new \DateTimeImmutable('2025-06-15 14:00:00'),
            new \DateTimeImmutable('2025-06-15 16:00:00')
        );

        $result = $this->calendarGenerator->getCalendar([$event]);

        $output = $result->getStream();
        $this->assertStringNotContainsString('<p>', $output);
        $this->assertStringNotContainsString('<strong>', $output);
        $this->assertStringContainsString('Description with HTML tags', $output);
    }

    public function testGetCalendarWithEmptyTitle(): void
    {
        $event = $this->createMockEvent(
            '',
            'https://example.com/event',
            'Description',
            new \DateTimeImmutable('2025-06-15 14:00:00'),
            new \DateTimeImmutable('2025-06-15 16:00:00')
        );

        $result = $this->calendarGenerator->getCalendar([$event]);

        $this->assertInstanceOf(CalendarExport::class, $result);
    }

    public function testGetCalendarWithEmptyDescription(): void
    {
        $event = $this->createMockEvent(
            'Test Event',
            'https://example.com/event',
            '',
            new \DateTimeImmutable('2025-06-15 14:00:00'),
            new \DateTimeImmutable('2025-06-15 16:00:00')
        );

        $result = $this->calendarGenerator->getCalendar([$event]);

        $this->assertInstanceOf(CalendarExport::class, $result);
    }

    public function testGetCalendarWithNullEndAt(): void
    {
        $event = $this->createMockEvent(
            'Test Event',
            'https://example.com/event',
            'Description',
            new \DateTimeImmutable('2025-06-15 14:00:00'),
            null
        );

        $result = $this->calendarGenerator->getCalendar([$event]);

        $this->assertInstanceOf(CalendarExport::class, $result);
    }

    private function createMockEvent(
        ?string $title,
        ?string $link,
        ?string $description,
        ?\DateTimeImmutable $startAt,
        ?\DateTimeImmutable $endAt
    ): Event {
        $event = new Event();

        $event->setTitle($title ?? 'Default Title');
        $event->setLink($link ?? 'https://example.com');
        $event->setDescription($description ?? 'Default Description');
        $event->setSlug('test-event');
        $event->setOrganizer('Test Organizer');
        $event->setSource('test');

        if (null !== $startAt) {
            $event->setStartAt($startAt);
        }
        if (null !== $endAt) {
            $event->setEndAt($endAt);
        }

        return $event;
    }
}
