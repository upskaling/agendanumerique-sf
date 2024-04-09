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

    public function encodeJsonLd($event)
    {
        if (!$event instanceof Event) {
            throw new \InvalidArgumentException(sprintf('Expected an instance of %s, got %s', Event::class, $event::class));
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->getTitle(),
            'description' => $event->getDescription(),
            'startDate' => $event->getStartAt()->format('Y-m-d H:i:s'),
            // "location" => [
            //     "@type" => "Place",
            //     "name" => "",
            //     "address" => [
            //         "@type" => "PostalAddress",
            //         "addressLocality" => "",
            //         "streetAddress" => ""
            //     ]
            // ],
            'image' => $event->getImage(),
            'organizer' => $event->getOrganizer(),
            'url' => $event->getLink(),
        ]);
    }
}
