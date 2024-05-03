<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Event;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get('validator');
    }

    // testez la contrainte des dates
    public function testDate(): void
    {
        $event = (new Event())
            ->setStartAt(new \DateTimeImmutable('2021-01-01 10:00:00'))
            ->setEndAt(new \DateTimeImmutable('2021-01-01 11:00:00'))
            ->setSlugWithOrganizer('foo')
            ->setLink('foo');

        $errors = $this->validator->validate($event);

        $this->assertCount(0, $errors);
    }

    // testez l'inverse des dates
    public function testDateInverse(): void
    {
        $event = (new Event())
            ->setStartAt(new \DateTimeImmutable('2021-01-01 11:00:00'))
            ->setEndAt(new \DateTimeImmutable('2021-01-01 10:00:00'))
            ->setSlugWithOrganizer('foo')
            ->setLink('foo');

        $errors = $this->validator->validate($event);

        $this->assertCount(1, $errors);
    }
}
