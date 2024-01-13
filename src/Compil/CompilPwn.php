<?php

declare(strict_types=1);

namespace App\Compil;

use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CompilPwn
{
    private $baseUrl = 'https://pwn-association.org';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
    ) {
    }

    public function compil(): void
    {
        $response = $this->httpClient->request(
            'GET',
            $this->baseUrl . '/tous-les-evenements-pwn/',
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        // .upcoming-events .event-inner>a
        $events = $crawler->filter('.upcoming-events .event-inner>a');
        $events->each(function (Crawler $event) {
            $link = $event->attr('href');
            $this->loadEvent($link);
        });

        $this->entityManager->flush();
    }

    private function loadEvent(string $link): void
    {
        $organizer = "pwn";
        $url = $this->baseUrl . $link;

        // si % $link % existe déjà dans la base de données, on ne fait rien
        if ($this->eventRepository->isLinkExist($link)) {
            return;
        }

        $response = $this->httpClient->request(
            'GET',
            $url
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);
        $event = new Event();

        $event->setOrganizer($organizer);

        // .event-title
        $title = $crawler->filter('.event-title')->text();
        $event->setTitle($title);

        $event->setLink($url);

        // slug
        $event->setSlugWithOrganizer($title);

        // meta[property='og:image']
        $image = $crawler->filter("meta[property='og:image']")->attr('content');
        $event->setImage($image);

        // .event-description
        $description = $crawler->filter('.event-description')->html();
        $event->setDescription($description);

        $event->setEndAt(
            new \DateTimeImmutable('now + 1 month')
        );
        $event->setStartAt(
            new \DateTimeImmutable('now')
        );


        $this->entityManager->persist($event);
    }
}
