<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Event;
use Twig\Extension\RuntimeExtensionInterface;

class AppJsonLdRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function encodeJsonLd(Event $event): string
    {
        // https://schema.org/docs/documents.html
        $json = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
        ];

        $name = $event->getTitle();
        if ($name) {
            $json['name'] = $name;
        }

        $description = $event->getDescription();
        if ($description) {
            $json['description'] = strip_tags($description);
        }

        $startDate = $event->getStartAt();
        if ($startDate) {
            $json['startDate'] = $startDate->format(\DateTimeInterface::ATOM);
        }

        $endDate = $event->getEndAt();
        if ($endDate) {
            $json['endDate'] = $endDate->format(\DateTimeInterface::ATOM);
        }

        $location = $event->getLocation();
        if ($location) {
            $json['location'] = [
                '@type' => 'Place',
                'name' => $location->getName(),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $location->getStreetAddress(),
                    'addressLocality' => $location->getAddressLocality(),
                    'postalCode' => $location->getPostalCode(),
                    'addressRegion' => $location->getAddressRegion(),
                    'addressCountry' => $location->getAddressCountry(),
                ],
            ];
        }

        $organizer = $event->getOrganizer();
        if ($organizer) {
            $json['organizer'] = [
                '@type' => 'Organization',
                'name' => $organizer,
                'url' => $event->getLink(),
            ];
        }

        $url = $event->getLink();
        if ($url) {
            $json['url'] = $url;
        }

        $image = $event->getImage();
        if ($image) {
            $json['image'] = $image;
        }

        $result = json_encode($json, \JSON_PRETTY_PRINT);
        if (false === $result) {
            throw new \RuntimeException('Unable to encode JSON-LD');
        }

        return "<script type=\"application/ld+json\">{$result}</script>";
    }

    /**
     * Génère une liste d'événements en JSON-LD (EventList).
     *
     * @param Event[] $events
     */
    public function encodeEventListJsonLd(
        array $events,
        string $pageUrl,
        string $pageTitle = 'Agenda du numérique à Poitiers et ses environs',
        string $pageDescription = 'Liste des événements numériques à Poitiers',
    ): string {
        $itemListElements = [];
        foreach ($events as $index => $event) {
            $eventJson = [
                '@type' => 'Event',
                'name' => $event->getTitle(),
                'description' => mb_substr(strip_tags($event->getDescription() ?? ''), 0, 160),
                'url' => $event->getLink(),
            ];

            if ($event->getStartAt()) {
                $eventJson['startDate'] = $event->getStartAt()->format(\DateTimeInterface::ATOM);
            }

            if ($event->getEndAt()) {
                $eventJson['endDate'] = $event->getEndAt()->format(\DateTimeInterface::ATOM);
            }

            if ($event->getImage()) {
                $eventJson['image'] = $event->getImage();
            }

            if ($event->getLocation()) {
                $location = $event->getLocation();
                $eventJson['location'] = [
                    '@type' => 'Place',
                    'name' => $location->getName(),
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $location->getAddressLocality(),
                        'postalCode' => $location->getPostalCode(),
                        'addressCountry' => $location->getAddressCountry(),
                    ],
                ];
            }

            if ($event->getOrganizer()) {
                $eventJson['organizer'] = [
                    '@type' => 'Organization',
                    'name' => $event->getOrganizer(),
                ];
            }

            $itemListElements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => $eventJson,
            ];
        }

        $json = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $pageUrl,
            'itemListElement' => $itemListElements,
        ];

        $result = json_encode($json, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        if (false === $result) {
            throw new \RuntimeException('Unable to encode JSON-LD for event list');
        }

        return "<script type=\"application/ld+json\">{$result}</script>";
    }
}
