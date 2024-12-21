<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventRetrievalAfupPoitiers implements EventRetrievalInterface
{
    private const URI = 'https://www.meetup.com/afup-poitiers-php/events/';
    private const NAME = 'afup-poitiers';
    private const ORGANIZER = 'Afup Poitiers';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    public function retrieveEvents(): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::URI
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        return $crawler->filter('ul.w-full > li')
            ->each(function (Crawler $crawler) {
                return $this->loadEvent($crawler);
            });
    }

    private function loadEvent(Crawler $crawler): EventValidationDTO
    {
        $event = new EventValidationDTO(self::NAME);
        $event->setOrganizer(self::ORGANIZER);

        $title = $crawler->filter('div:nth-child(1) > span:nth-child(2)')->text();
        $event->setTitle($title);

        $link = $crawler->filter('div:nth-child(1) > a:nth-child(1)')->attr('href');
        if (null !== $link) {
            $event->setLink($link);
        }

        try {
            $image = $crawler->filter('#event-group-link > div:nth-child(1) > div:nth-child(1) > img:nth-child(1)')->attr('src');
            $event->setImage($image);
        } catch (\InvalidArgumentException $e) {
            $this->logger->debug(
                'Image not found',
                [
                    'exception' => $e,
                ]
            );
        }

        $slugger = new AsciiSlugger();
        $slug = $slugger->slug(self::ORGANIZER.'-'.$title)->lower()->toString();
        $event->setSlug($slug);

        $description = $crawler->filter('div.md\:block > div')->html();
        $event->setDescription($description);

        $lieu = $crawler->filter('div:nth-child(1) > div:nth-child(3) > span:nth-child(2)')->text();

        $location = match ($lieu) {
            'Cobalt, Poitiers' => $this->postalAddressRepository->findOneBy(['name' => 'Cobalt']),
            'HTAG' => $this->postalAddressRepository->findOneBy(['name' => 'HTAG']),
            default => null,
        };
        if ($location) {
            $event->setLocation($location);
        }

        $date = $crawler->filter('div:nth-child(1) > time:nth-child(1)')->text();

        // "Thu, Sep 19, 2024, 7:00 PM CEST"
        $date = \DateTimeImmutable::createFromFormat(
            'D, M d, Y, g:i A T',
            $date
        );

        if ($date) {
            $event->setStartAt($date);
        }

        return $event;
    }
}
