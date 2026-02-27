<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Event;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    /** @var array{title: string, slug: string, link: string, description: string, organizer: string, source: string} */
    private array $validEventData;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->validator = $container->get(ValidatorInterface::class);

        $this->validEventData = [
            'title' => 'Conférence Symfony',
            'slug' => 'conference-symfony',
            'link' => 'https://example.com/event',
            'description' => 'Une conférence sur Symfony',
            'organizer' => 'Symfony Community',
            'source' => 'website',
        ];
    }

    private function createEvent(\DateTimeImmutable $startAt, ?\DateTimeImmutable $endAt): Event
    {
        $event = new Event();
        $event->setStartAt($startAt);
        $event->setEndAt($endAt);
        $event->setTitle($this->validEventData['title']);
        $event->setSlug($this->validEventData['slug']);
        $event->setLink($this->validEventData['link']);
        $event->setDescription($this->validEventData['description']);
        $event->setOrganizer($this->validEventData['organizer']);
        $event->setSource($this->validEventData['source']);

        return $event;
    }

    private function assertViolationCount(int $expectedCount, Event $event): void
    {
        $errors = $this->validator->validate($event);
        $this->assertInstanceOf(ConstraintViolationList::class, $errors);
        $this->assertCount($expectedCount, $errors);
    }

    public function testValidEventWithDatesInOrder(): void
    {
        $event = $this->createEvent(
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            new \DateTimeImmutable('2025-01-01 11:00:00')
        );

        $this->assertViolationCount(0, $event);
    }

    public function testEventWithEndDateBeforeStartDateIsInvalid(): void
    {
        $event = $this->createEvent(
            new \DateTimeImmutable('2025-01-01 11:00:00'),
            new \DateTimeImmutable('2025-01-01 10:00:00')
        );

        $this->assertViolationCount(1, $event);
    }

    public function testEventWithNullEndDateIsValid(): void
    {
        $event = $this->createEvent(
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            null
        );

        $this->assertViolationCount(0, $event);
    }

    public function testEventWithEndDateExactlyAtSameTimeIsInvalid(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        $event = $this->createEvent($startDate, $startDate);

        $this->assertViolationCount(1, $event);
    }

    /**
     * @return array<string, array{\DateTimeImmutable, \DateTimeImmutable|null}>
     */
    public static function provideValidDateScenarios(): array
    {
        return [
            'end date after start date' => [
                new \DateTimeImmutable('2025-01-01 10:00:00'),
                new \DateTimeImmutable('2025-01-01 11:00:00'),
            ],
            'end date null' => [
                new \DateTimeImmutable('2025-01-01 10:00:00'),
                null,
            ],
            'same day different time' => [
                new \DateTimeImmutable('2025-01-01 10:00:00'),
                new \DateTimeImmutable('2025-01-01 18:00:00'),
            ],
            'different days' => [
                new \DateTimeImmutable('2025-01-01 10:00:00'),
                new \DateTimeImmutable('2025-01-02 10:00:00'),
            ],
        ];
    }

    /**
     * @return array<string, array{\DateTimeImmutable, \DateTimeImmutable|null}>
     */
    public static function provideInvalidDateScenarios(): array
    {
        return [
            'end date before start date' => [
                new \DateTimeImmutable('2025-01-01 11:00:00'),
                new \DateTimeImmutable('2025-01-01 10:00:00'),
            ],
            'end date equals start date' => [
                new \DateTimeImmutable('2025-01-01 10:00:00'),
                new \DateTimeImmutable('2025-01-01 10:00:00'),
            ],
            'end date far before start date' => [
                new \DateTimeImmutable('2025-01-02 10:00:00'),
                new \DateTimeImmutable('2025-01-01 10:00:00'),
            ],
            'end date one second before start date' => [
                new \DateTimeImmutable('2025-01-01 10:00:01'),
                new \DateTimeImmutable('2025-01-01 10:00:00'),
            ],
        ];
    }

    /**
     * @dataProvider provideValidDateScenarios
     */
    public function testValidDateScenarios(\DateTimeImmutable $startAt, ?\DateTimeImmutable $endAt): void
    {
        $event = $this->createEvent($startAt, $endAt);
        $this->assertViolationCount(0, $event);
    }

    /**
     * @dataProvider provideInvalidDateScenarios
     */
    public function testInvalidDateScenarios(\DateTimeImmutable $startAt, ?\DateTimeImmutable $endAt): void
    {
        $event = $this->createEvent($startAt, $endAt);
        $this->assertViolationCount(1, $event);
    }
}
