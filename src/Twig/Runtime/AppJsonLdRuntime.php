<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Event;
use App\Entity\PostalAddress;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class AppJsonLdRuntime implements RuntimeExtensionInterface
{
    /**
     * Flags used to safely embed JSON-LD inside a <script> tag.
     *
     * HTML characters are escaped so that a value containing "</script>" can
     * never break out of the script element (XSS / invalid JSON-LD).
     */
    private const JSON_FLAGS = \JSON_UNESCAPED_SLASHES
        | \JSON_UNESCAPED_UNICODE
        | \JSON_HEX_TAG
        | \JSON_HEX_AMP
        | \JSON_HEX_APOS
        | \JSON_HEX_QUOT;

    private const LIST_DESCRIPTION_MAX_LENGTH = 160;

    private const CURRENCY = 'EUR';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function encodeJsonLd(Event $event): string
    {
        return $this->render([
            '@context' => 'https://schema.org',
            ...$this->buildEvent($event),
        ]);
    }

    /**
     * Génère une liste d'événements en JSON-LD (ItemList).
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

        foreach (array_values($events) as $index => $event) {
            $itemListElements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => $this->buildEvent($event, self::LIST_DESCRIPTION_MAX_LENGTH),
            ];
        }

        return $this->render([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $pageUrl,
            'numberOfItems' => \count($itemListElements),
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $itemListElements,
        ]);
    }

    /**
     * Construit la représentation schema.org d'un événement.
     *
     * Seules les données réellement connues sont exposées : les propriétés
     * vides sont omises plutôt que sérialisées avec une valeur null ou "".
     *
     * @return array<string, mixed>
     */
    private function buildEvent(Event $event, ?int $descriptionMaxLength = null): array
    {
        $json = [
            '@type' => 'Event',
            'eventStatus' => 'https://schema.org/EventScheduled',
        ];

        if (null !== $name = $this->plainText($event->getTitle())) {
            $json['name'] = $name;
        }

        if (null !== $description = $this->plainText($event->getDescription(), $descriptionMaxLength)) {
            $json['description'] = $description;
        }

        $startAt = $event->getStartAt();
        if (null !== $startAt) {
            $json['startDate'] = $startAt->format(\DateTimeInterface::ATOM);
        }

        $endAt = $event->getEndAt();
        if (null !== $endAt && (null === $startAt || $endAt > $startAt)) {
            $json['endDate'] = $endAt->format(\DateTimeInterface::ATOM);
        } elseif (null === $endAt && null !== $startAt) {
            // Un événement sans heure de fin est considéré comme ponctuel.
            $json['endDate'] = $startAt->format(\DateTimeInterface::ATOM);
        }

        if (null !== $url = $this->eventUrl($event)) {
            $json['url'] = $url;
        }

        if (null !== $location = $event->getLocation()) {
            $json['location'] = $this->buildLocation($location);
        }

        if (null !== $organizer = $this->plainText($event->getOrganizer())) {
            $json['organizer'] = [
                '@type' => 'Organization',
                'name' => $organizer,
            ];

            $organizerUrl = $event->getOrganizerUrl();
            if (null !== $organizerUrl && '' !== $organizerUrl) {
                $json['organizer']['url'] = $organizerUrl;
            }
        }

        if (null !== $performer = $this->plainText($event->getPerformer())) {
            $json['performer'] = [
                '@type' => 'Person',
                'name' => $performer,
            ];
        }

        if (null !== $price = $event->getPrice()) {
            $offer = [
                '@type' => 'Offer',
                'price' => $this->formatPrice($price),
                'priceCurrency' => self::CURRENCY,
                'availability' => 'https://schema.org/InStock',
            ];

            if (isset($json['url'])) {
                $offer['url'] = $json['url'];
            }

            $json['offers'] = $offer;
        }

        if (null !== $image = $this->plainText($event->getImage())) {
            $json['image'] = $image;
        }

        // L'URL d'origine reste utile pour relier la fiche à sa source.
        $sourceUrl = $event->getLink();
        if (null !== $sourceUrl && '' !== $sourceUrl && $sourceUrl !== ($json['url'] ?? null)) {
            $json['sameAs'] = [$sourceUrl];
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLocation(PostalAddress $location): array
    {
        $place = [
            '@type' => 'Place',
        ];

        if (null !== $name = $this->plainText($location->getName())) {
            $place['name'] = $name;
        }

        $country = $this->plainText($location->getAddressCountry());
        if (null !== $country && 2 === mb_strlen($country)) {
            $country = mb_strtoupper($country);
        }

        $address = [
            '@type' => 'PostalAddress',
            'streetAddress' => $this->plainText($location->getStreetAddress()),
            'addressLocality' => $this->plainText($location->getAddressLocality()),
            'postalCode' => $this->plainText($location->getPostalCode()),
            'addressRegion' => $this->plainText($location->getAddressRegion()),
            'addressCountry' => $country,
        ];
        $address = array_filter($address, static fn (mixed $value): bool => null !== $value);

        if (\count($address) > 1) {
            $place['address'] = $address;
        }

        return $place;
    }

    /**
     * URL canonique de la fiche sur le site, avec repli sur le lien source.
     */
    private function eventUrl(Event $event): ?string
    {
        $slug = $event->getSlug();
        if (null !== $slug && '' !== $slug) {
            return $this->urlGenerator->generate(
                'app_event_show_slug',
                ['slug' => $slug],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $event->getLink();
    }

    /**
     * Formate un prix sans zéros inutiles pour schema.org ("15", "12.5", "0").
     */
    private function formatPrice(float $price): string
    {
        $formatted = number_format($price, 2, '.', '');

        return mb_rtrim(mb_rtrim($formatted, '0'), '.') ?: '0';
    }

    /**
     * Convertit un contenu HTML en texte brut normalisé pour JSON-LD.
     */
    private function plainText(?string $value, ?int $maxLength = null): ?string
    {
        if (null === $value) {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = mb_trim((string) preg_replace('/\s+/u', ' ', $text));

        if ('' === $text) {
            return null;
        }

        if (null !== $maxLength && mb_strlen($text) > $maxLength) {
            $text = mb_rtrim(mb_substr($text, 0, $maxLength - 1));

            // Évite de couper un mot en plein milieu.
            $lastSpace = mb_strrpos($text, ' ');
            if (false !== $lastSpace && $lastSpace > (int) ($maxLength / 2)) {
                $text = mb_substr($text, 0, $lastSpace);
            }

            $text .= '…';
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function render(array $json): string
    {
        $encoded = json_encode($json, self::JSON_FLAGS);

        if (false === $encoded) {
            throw new \RuntimeException('Unable to encode JSON-LD: '.json_last_error_msg());
        }

        return \sprintf('<script type="application/ld+json">%s</script>', $encoded);
    }
}
