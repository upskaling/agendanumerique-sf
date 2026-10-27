<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventShowTest extends WebTestCase
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

    public function testCanonicalUrlUsesAbsoluteSlugUrl(): void
    {
        $event = $this->createEvent('Événement canonique', 'evenement-canonique');

        $this->client->request('GET', '/event/'.$event->getId());

        $this->assertResponseIsSuccessful();

        $canonical = $this->client->getCrawler()->filter('link[rel="canonical"]');

        $this->assertCount(1, $canonical);
        $this->assertMatchesRegularExpression(
            '#^https?://[^/]+/event/evenement-canonique$#',
            (string) $canonical->attr('href')
        );
    }

    private function createEvent(string $title, string $slug): Event
    {
        $event = new Event();
        $event->setTitle($title);
        $event->setSlug($slug);
        $event->setLink('https://example.com/'.$slug);
        $event->setDescription('Description de '.$title);
        $event->setOrganizer('Organisateur de test');
        $event->setSource('cobaltpoitiers');
        $event->setStartAt(new \DateTimeImmutable('+1 day'));
        $event->setPublished(new \DateTimeImmutable('-1 hour'));

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }
}
