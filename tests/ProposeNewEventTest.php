<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Event;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProposeNewEventTest extends WebTestCase
{
    protected function tearDown(): void
    {
        $enytyManager = self::getContainer()->get('doctrine')->getManager();
        $eventRepository = $enytyManager->getRepository(Event::class);
        $event = $eventRepository->findOneBy(['slug' => '1-foo']);
        if ($event) {
            $enytyManager->remove($event);
        }
        $enytyManager->flush();
        parent::tearDown();
    }

    public function testProposeNewEvent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/event/propose-new');
        $this->assertResponseIsSuccessful();
    }

    public function testProposeNewEventWithInvalidData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/event/propose-new');
        $client->submitForm('Enregistrer', [
            'propose_new[title]' => 'foo',
            'propose_new[link]' => 'foo',
            'propose_new[description]' => 'foo',
            'propose_new[startAt]' => '2021-01-01 11:00:00',
            'propose_new[endAt]' => '2021-01-01 10:00:00',
            'propose_new[organizer]' => '1',
            'propose_new[image]' => 'foo',
            'propose_new[location]' => '1',
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testProposeNewEventWithValidData(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $client->request('GET', '/event/propose-new');
        $client->submitForm('Enregistrer', [
            'propose_new[title]' => 'foo',
            'propose_new[link]' => 'https://example.com',
            'propose_new[description]' => 'foo',
            'propose_new[startAt]' => '2021-01-01 10:00:00',
            'propose_new[endAt]' => '2021-01-01 11:00:00',
            'propose_new[organizer]' => '1',
            'propose_new[image]' => 'https://example.com/image.png',
            'propose_new[location]' => '1',
        ]);

        $this->assertResponseStatusCodeSame(302);
    }
}
