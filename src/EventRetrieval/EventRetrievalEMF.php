<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventRetrievalEMF implements EventRetrievalInterface
{
    private const URI = 'https://emf.fr';
    private const NAME = 'emf';
    private const ORGANIZER = 'emf';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    public function retrieveEvents(): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::URI.'/events/?s=&date=&posts_per_page=20&tax=discipline[numerique]'
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        /** @var EventValidationDTO[] $result */
        $result = $crawler->filter('.elementor-46496>.elementor-element> .elementor-element.elementor-element-c3baf3a.e-con-full.e-flex.e-con.e-child')
            ->each(function (Crawler $crawler) {
                return $this->loadEvent($crawler);
            });

        return $result;
    }

    public function loadEvent(Crawler $crawler): ?EventValidationDTO
    {
        $eventUrl = $this->extractEventUrl($crawler);
        if (null === $eventUrl) {
            return null;
        }

        $eventCrawler = $this->fetchEventPage($eventUrl);
        $eventData = $this->extractJsonLdData($eventCrawler);

        if (null === $eventData) {
            return null;
        }

        return $this->createEventDTO($eventData, $eventCrawler);
    }

    private function extractEventUrl(Crawler $crawler): ?string
    {
        return $crawler->filter('a')->attr('href');
    }

    private function fetchEventPage(string $url): Crawler
    {
        $response = $this->httpClient->request('GET', $url);

        return new Crawler($response->getContent());
    }

    /**
     * @return array<string,mixed>|null
     */
    private function extractJsonLdData(Crawler $crawler): ?array
    {
        $jsonLD = $crawler->filter('script[type="application/ld+json"]')->text();
        /** @var array<string,mixed>|null $data */
        $data = json_decode($jsonLD, true);

        /** @var array<string,mixed>|null $graph */
        $graph = ((array) ($data['@graph'] ?? []))[0] ?? null;

        return $graph;
    }

    /**
     * @param array<string,mixed> $eventData
     */
    private function createEventDTO(array $eventData, Crawler $eventCrawler): EventValidationDTO
    {
        $event = new EventValidationDTO(self::NAME);
        $event->setOrganizer(self::ORGANIZER);

        $this->setEventLocation($event);
        $this->setEventBasicInfo($event, $eventData);
        $this->setEventDateTime($event, $eventCrawler);

        return $event;
    }

    private function setEventLocation(EventValidationDTO $event): void
    {
        $location = $this->postalAddressRepository->findOneBy(['name' => 'Espace Mendès France']);
        $event->setLocation($location);
    }

    /**
     * @param array<string,mixed> $eventData
     */
    private function setEventBasicInfo(EventValidationDTO $event, array $eventData): void
    {
        if (isset($eventData['name']) && \is_string($eventData['name'])) {
            $event->setTitle($this->cleanTextContent($eventData['name']));
        }

        if (isset($eventData['url']) && \is_string($eventData['url'])) {
            $event->setLink($eventData['url']);
        }

        if (isset($eventData['description']) && \is_string($eventData['description'])) {
            $event->setDescription($eventData['description']);
        }

        if (isset($eventData['thumbnailUrl']) && \is_string($eventData['thumbnailUrl'])) {
            $event->setImage($eventData['thumbnailUrl']);
        }
    }

    private function setEventDateTime(EventValidationDTO $event, Crawler $crawler): void
    {
        $startAtText = $crawler->filter('.elementor-element.elementor-element-c2a5359 .elementor-heading-title')->text();
        $startDate = $this->convertDate($startAtText);

        if (false !== $startDate) {
            $event->setStartAt($startDate);
            $this->setEventSlug($event, $startDate);
        }
    }

    private function setEventSlug(EventValidationDTO $event, \DateTimeImmutable $date): void
    {
        $slugger = new AsciiSlugger();
        $slug = $slugger->slug(\sprintf(
            '%s-%s-%s',
            self::ORGANIZER,
            $event->getTitle(),
            $date->format('Y-m-d')
        ))->lower();

        $event->setSlug($slug->toString());
    }

    private function cleanTextContent(string $text): string
    {
        $decodedText = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $cleanText = strip_tags($decodedText);

        return mb_trim($cleanText);
    }

    private function convertDate(string $date): \DateTimeImmutable|false
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR', // Langue et région
            \IntlDateFormatter::FULL, // Format pour la date complète
            \IntlDateFormatter::SHORT // Format pour l'heure
        );

        $formatter->setPattern("EEEE d MMMM yyyy 'à' HH'h'mm");

        $timestamp = $formatter->parse($date);

        if (false === $timestamp) {
            return false;
        }

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }
}
