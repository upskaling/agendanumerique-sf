<?php

declare(strict_types=1);

namespace App\Compil;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\PostalAddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CompilEMF implements CompilInterface
{
    private const URI = 'https://emf.fr';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly ValidatorInterface $validation,
        private readonly LoggerInterface $logger,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    public function getUrl(): iterable
    {
        yield 'https://emf.fr/ec3_event/une-ia-massiste-pour-creer-mon-site-web/';
        yield 'https://emf.fr/ec3_event/creer-un-chatbot-avec-scratch/';
        yield 'https://emf.fr/ec3_event/mon-premier-jeu-video-pac-man/';
        yield 'https://emf.fr/ec3_event/mon-premier-jeu-video-la-bataille-des-planetes/';
        yield 'https://emf.fr/ec3_event/brickanoid-gare-aux-briques/';
        yield 'https://emf.fr/ec3_event/mon-premier-jeu-video-ninja-fruits/';
        yield 'https://emf.fr/ec3_event/la-chasse-aux-aliens-commence/';
        yield 'https://emf.fr/ec3_event/mon-premier-jeu-video-les-fous-du-volant/';
        yield 'https://emf.fr/ec3_event/mon-premier-jeu-video-snake-le-serpent-qui-se-mord-la-queue/';
        yield 'https://emf.fr/ec3_event/je-debute-avec-chatgpt/';
    }

    public function compil(): void
    {
        $responses = [];
        foreach ($this->getUrl() as $url) {
            $responses[] = $this->httpClient->request(
                'GET',
                $url
            );
        }

        foreach ($responses as $response) {
            $this->loadEvent($response);
        }
        $this->entityManager->flush();
    }

    private function convertDate($date)
    {
        $date = str_replace(
            ['jan.', 'fév.', 'mars', 'avril', 'mai', 'juin', 'juil', 'aou', 'sep', 'oct.', 'nov.', 'déc.'],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            $date
        );

        $date = explode(
            ' -> ',
            $date
        );

        return \DateTimeImmutable::createFromFormat(
            'd m Y H \h i',
            $date[0]
        );
    }

    private function loadEvent($response): void
    {
        $organizer = 'emf';

        $content = $response->getContent();

        $crawler = new Crawler($content);

        // .ec3_iconlet.ec3_past
        // cherche si le bloc date est grisé, si oui, date dépassée donc pas d'evenet à gérer
        try {
            $date = $crawler->filter('.ec3_iconlet.ec3_past')->text();

            return;
        } catch (\InvalidArgumentException $e) {
        }

        $event = new Event();

        $event->setOrganizer($organizer);

        $lieu = $crawler->filter('.info_lieu b')->text();
        $lieuName = 'Espace Mendès France';
        if (str_contains($lieu, $lieuName)) {
            $location = $this->postalAddressRepository->findOneBy(['name' => $lieuName]);
            $event->setLocation($location);
        }

        $title = $crawler->filter('.hero-title-inside-text h1')->text();
        $event->setTitle($title);
        $event->setSlugWithOrganizer($title);

        $url = $crawler->filter("meta[property='og:url']")->attr('content');
        $event->setLink($url);

        $image = $crawler->filter("meta[property='og:image']")->attr('content');

        $event->setImage($image);

        $description = $crawler->filter("meta[property='og:description']")->attr('content');
        $event->setDescription($description);

        $dates = $crawler->filter('.ec3_schedule_date.ec3_schedule_next')->each(
            function ($node) {
                return $this->convertDate($node->text()); // "18 juin 2024 14 h 00 -> 16 h 00"
            }
        );

        foreach ($dates as $date) {
            $events = clone $event;

            $events->setStartAt($date);
            $events->setSlug($events->getSlug().'-'.$date->format('Y-m-d'));

            $errors = $this->validation->validate($events);

            if (0 === \count($errors)) {
                $this->entityManager->persist($events);
            }
        }
    }
}
