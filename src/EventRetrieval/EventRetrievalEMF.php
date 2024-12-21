<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use IntlDateFormatter;
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

        $response = $crawler->filter('.elementor-46496>.elementor-element> .elementor-element.elementor-element-c3baf3a.e-con-full.e-flex.e-con.e-child')
            ->each(function (Crawler $crawler) {
                return $this->loadEvent($crawler);
            });

        return $response;
    }

    private function convertDate(string $date): \DateTimeImmutable|false
    {
        // Configurez l'analyseur de dates avec IntlDateFormatter
        $formatter = new \IntlDateFormatter(
            'fr_FR', // Langue et région
            \IntlDateFormatter::FULL, // Format pour la date complète
            \IntlDateFormatter::SHORT // Format pour l'heure
        );

        // Ajustez le format pour correspondre exactement à votre chaîne
        $formatter->setPattern("EEEE d MMMM yyyy 'à' HH'h'mm");

        $timestamp = $formatter->parse($date);

        if (false === $timestamp) {
            return false;
        }

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function loadEvent(Crawler $crawler): ?EventValidationDTO
    {
        $url = $crawler->filter('a')->attr('href');

        if (null === $url) {
            return null;
        }

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();
        $crawler = new Crawler($content);

        $jsonLD = $crawler->filter('script[type="application/ld+json"]')->text();

        /** @var array<string, mixed>|null $data */
        $data = json_decode($jsonLD, true);
        /** @var array<string, mixed>|null $graph */
        $graph = ((array) ($data['@graph'] ?? []))[0] ?? null;

        if (null === $graph) {
            return null;
        }

        $event = new EventValidationDTO(self::NAME);
        $event->setOrganizer(self::ORGANIZER);
        $lieuName = 'Espace Mendès France';
        $location = $this->postalAddressRepository->findOneBy(['name' => $lieuName]);
        $event->setLocation($location);

        $name = $graph['name'] ?? null;
        if (\is_string($name)) {
            $event->setTitle($name);
        }

        $url = $graph['url'] ?? null;
        if (\is_string($url)) {
            $event->setLink($url);
        }

        $description = $graph['description'] ?? null;
        if (\is_string($description)) {
            $event->setDescription($description);
        }

        $thumbnailUrl = $graph['thumbnailUrl'] ?? null;
        if (\is_string($thumbnailUrl)) {
            $event->setImage($thumbnailUrl);
        }

        $startAt = $crawler->filter('.elementor-element.elementor-element-c2a5359 .elementor-heading-title')->text();
        $date = $this->convertDate($startAt);
        if (false !== $date) {
            $event->setStartAt($date);
            $slugger = new AsciiSlugger();
            $slug = $slugger->slug(self::ORGANIZER.'-'.$name.'-'.$date->format('Y-m-d'))->lower()->toString();
            $event->setSlug($slug);
        }

        return $event;
    }
}
