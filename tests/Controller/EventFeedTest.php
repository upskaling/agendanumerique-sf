<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventFeedTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    public function testFeedContainsUpcomingPublishedEvents(): void
    {
        $this->createEvent('Événement à venir', 'feed-evenement-a-venir', 'cobaltpoitiers');
        $this->createEvent('Événement passé', 'feed-evenement-passe', 'cobaltpoitiers', '-1 day');
        $this->createEvent('Événement non publié', 'feed-evenement-non-publie', 'cobaltpoitiers', '+1 day', false);

        $this->client->request('GET', '/event/feed.xml');

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith(
            'application/xml',
            (string) $this->client->getResponse()->headers->get('Content-Type')
        );

        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Événement à venir', $content);
        $this->assertStringContainsString('/event/feed-evenement-a-venir', $content);
        $this->assertStringNotContainsString('Événement passé', $content);
        $this->assertStringNotContainsString('Événement non publié', $content);
        $this->assertSame(1, $this->countFeedItems($content));
    }

    public function testFeedFiltersEventsBySelection(): void
    {
        $this->createEvent('Événement Cobalt', 'feed-selection-cobalt', 'cobaltpoitiers');
        $this->createEvent('Événement PWN', 'feed-selection-pwn', 'pwn');

        $this->client->request('GET', '/event/feed.xml?selection[]=2');

        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Événement PWN', $content);
        $this->assertStringNotContainsString('Événement Cobalt', $content);
        $this->assertSame(1, $this->countFeedItems($content));
    }

    public function testFeedReturnsAllSourcesWhenSelectionIsNull(): void
    {
        $this->createEvent('Événement Cobalt', 'feed-null-selection-cobalt', 'cobaltpoitiers');
        $this->createEvent('Événement PWN', 'feed-null-selection-pwn', 'pwn');

        $this->client->request('GET', '/event/feed.xml');

        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countFeedItems((string) $this->client->getResponse()->getContent()));
    }

    public function testFeedIsValidRss(): void
    {
        $this->createEvent('Événement à venir', 'feed-rss-valide', 'afup-poitiers');

        $this->client->request('GET', '/event/feed.xml');

        $this->assertResponseIsSuccessful();

        $previous = libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $loaded = $document->loadXML((string) $this->client->getResponse()->getContent());
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertTrue($loaded, 'The feed must contain valid XML.');
        $this->assertSame([], $errors, 'The feed must not contain XML errors.');
        $this->assertSame('rss', $document->documentElement?->nodeName);
        $this->assertSame(1, $document->getElementsByTagName('item')->length);
    }

    private function createEvent(
        string $title,
        string $slug,
        string $source,
        string $startAt = '+1 day',
        bool $published = true,
    ): Event {
        $event = new Event();
        $event->setTitle($title);
        $event->setSlug($slug);
        $event->setLink('https://example.com/'.$slug);
        $event->setDescription('Description de '.$title);
        $event->setOrganizer('Organisateur de test');
        $event->setSource($source);
        $event->setStartAt(new \DateTimeImmutable($startAt));

        if ($published) {
            $event->setPublished(new \DateTimeImmutable('-1 hour'));
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    private function countFeedItems(string $content): int
    {
        $document = new \DOMDocument();
        $document->loadXML($content);

        return $document->getElementsByTagName('item')->length;
    }
}
