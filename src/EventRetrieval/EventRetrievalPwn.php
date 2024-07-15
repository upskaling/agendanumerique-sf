<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventRetrievalPwn implements EventRetrievalInterface
{
    private const URI = 'https://pwn-association.org';
    private const NAME = 'pwn';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    public function retrieveEvents(): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::URI.'/tous-les-evenements-pwn/',
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        // .upcoming-events .event-inner>a
        return $crawler->filter('.upcoming-events .event-inner>a')
            ->each(function (Crawler $event) {
                $link = $event->attr('href');
                if (null !== $link) {
                    return $this->loadEvent($link);
                }
            });
    }

    private function convertDate(string $date): \DateTimeImmutable|false
    {
        $date = str_replace(
            ['jan.', 'fév.', 'mars', 'avr.', 'mai', 'juin', 'juil', 'aou', 'sep', 'oct.', 'nov.', 'déc.'],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            $date
        );

        return \DateTimeImmutable::createFromFormat(
            'n j, Y à H:i',
            $date
        );
    }

    private function loadEvent(string $link): EventValidationDTO
    {
        $organizer = 'pwn';
        $url = self::URI.$link;

        $response = $this->httpClient->request(
            'GET',
            $url
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);
        $event = new EventValidationDTO(self::NAME);

        $event->setOrganizer($organizer);

        // .event-title
        $title = $crawler->filter('.event-title')->text();
        $event->setTitle($title);

        $event->setLink($url);

        // slug
        $event->setSlugWithOrganizer($title);

        // meta[property='og:image']
        $image = $crawler->filter("meta[property='og:image']")->attr('content');
        if ($image) {
            $event->setImage($image);
        }

        // .event-description
        $description = $crawler->filter('.event-description');
        $event->setDescription($description->html());

        $lieu = $crawler->filter('.event-description > ul:nth-child(2) > li:nth-child(1) > a')->text();
        $lieuName = 'La Taverne Du Geek';
        if (str_contains($lieu, $lieuName)) {
            $location = $this->postalAddressRepository->findOneBy(['name' => $lieuName]);
            $event->setLocation($location);
        }

        $dateText = $description->filter('ul:nth-child(2) > li:nth-child(2)')->text();
        // ex: "Date et heure : mars 13, 2024 à 19:00 – mars 13, 2024 à 21:00"

        // on retire "Date et heure : "
        $dateText = str_replace('Date et heure : ', '', $dateText);
        // ex: "mars 13, 2024 à 19:00 – mars 13, 2024 à 21:00"
        // on explode
        $dateText = explode(' – ', $dateText);

        $startAt = $this->convertDate($dateText[0]);
        if ($startAt) {
            $event->setStartAt($startAt);
        }

        $endAt = $this->convertDate($dateText[1]);
        if ($endAt) {
            $event->setEndAt($endAt);
        }

        return $event;
    }
}
