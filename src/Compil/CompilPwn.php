<?php

declare(strict_types=1);

namespace App\Compil;

use App\Entity\Event;
use App\Repository\EventRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CompilPwn
{
    private $baseUrl = 'https://pwn-association.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly ValidatorInterface $validation,
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

    private function convertDate($date)
    {
        $date = str_replace(
            ['janvier', 'fevrier', 'mars', 'avr.', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            $date
        );
        return \DateTimeImmutable::createFromFormat(
            'n j, Y à H:i',
            $date
        );
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
        $description = $crawler->filter('.event-description');
        $event->setDescription($description->html());

        $dateText = $description->filter('ul:nth-child(2) > li:nth-child(2)')->text();
        // ex: "Date et heure : mars 13, 2024 à 19:00 – mars 13, 2024 à 21:00"

        // on retire "Date et heure : "
        $dateText = str_replace("Date et heure : ", "", $dateText);
        // ex: "mars 13, 2024 à 19:00 – mars 13, 2024 à 21:00"
        // on explode
        $dateText = explode(" – ", $dateText);
        dump($dateText);

        $event->setEndAt(
            $this->convertDate($dateText[0])
        );
        $event->setStartAt(
            $this->convertDate($dateText[1])
        );

        // validation
        $errors = $this->validation->validate($event);

        if (count($errors) === 0) {
            $this->entityManager->persist($event);
        }
    }
}
