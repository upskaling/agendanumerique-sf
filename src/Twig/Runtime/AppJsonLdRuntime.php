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
            $json['startDate'] = $startDate->format('Y-m-d H:i:s');
        }

        $endDate = $event->getEndAt();
        if ($endDate) {
            $json['endDate'] = $endDate->format('Y-m-d H:i:s');
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
}
