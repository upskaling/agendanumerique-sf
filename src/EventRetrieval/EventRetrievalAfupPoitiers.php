<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use App\Utils\Markdown;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventRetrievalAfupPoitiers implements EventRetrievalInterface
{
    private const URI = 'https://www.meetup.com/afup-poitiers-php/events/';
    private const NAME = 'afup-poitiers';
    private const ORGANIZER = 'Afup Poitiers';
    private const EVENT_TYPENAME = 'Event';

    /** @var array<string,array<string,mixed>> */
    private array $apolloState = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    /**
     * Retrieves all upcoming events from AFUP Poitiers meetup page.
     *
     * @throws EventRetrievalException When unable to fetch or parse events
     */
    public function retrieveEvents(): array
    {
        try {
            /** @var array<string,array<string,mixed>> $apolloState */
            $apolloState = $this->fetchApolloStateData();

            return $this->processApolloState($apolloState);
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('HTTP error while fetching events', ['exception' => $e]);
            throw new EventRetrievalException('Failed to fetch events data', 0, $e);
        } catch (\JsonException $e) {
            $this->logger->error('JSON parsing error', ['exception' => $e]);
            throw new EventRetrievalException('Failed to parse events data', 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during event retrieval', ['exception' => $e]);
            throw new EventRetrievalException('Unexpected error during event retrieval', 0, $e);
        }
    }

    /**
     * Fetches and extracts Apollo state data from the meetup page.
     *
     * @throws HttpExceptionInterface
     * @throws \JsonException
     *
     * @return array<mixed>
     */
    private function fetchApolloStateData(): array
    {
        $response = $this->httpClient->request('GET', self::URI);
        $content = $response->getContent();
        $crawler = new Crawler($content);

        $jsonData = $crawler->filter('#__NEXT_DATA__')->text();
        $decodedData = json_decode($jsonData, true, 512, \JSON_THROW_ON_ERROR);

        // Vérification en cascade avec des assertions de type
        if (!\is_array($decodedData)) {
            return [];
        }

        $props = $decodedData['props'] ?? [];
        if (!\is_array($props)) {
            return [];
        }

        $pageProps = $props['pageProps'] ?? [];
        if (!\is_array($pageProps)) {
            return [];
        }

        $apolloState = $pageProps['__APOLLO_STATE__'] ?? [];

        return \is_array($apolloState) ? $apolloState : [];
    }

    /**
     * Processes Apollo state data to extract events.
     *
     * @param array<string,array<string,mixed>> $apolloState
     *
     * @return list<EventValidationDTO>
     */
    private function processApolloState(array $apolloState): array
    {
        $eventDataArrays = $this->extractEventDataFromApolloState($apolloState);
        $this->apolloState = $apolloState;

        $events = [];

        foreach ($eventDataArrays as $eventData) {
            /** @var array<string,array<string,mixed>> $eventData */
            $event = $this->createEventFromData($eventData);

            // Filtrer les valeurs null
            if ($event instanceof EventValidationDTO) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Extracts event data from Apollo state.
     *
     * @param array<mixed> $apolloState
     *
     * @return list<array<mixed>>
     */
    private function extractEventDataFromApolloState(array $apolloState): array
    {
        $events = [];

        foreach ($apolloState as $key => $data) {
            if (str_contains($key, self::EVENT_TYPENAME) && \is_array($data)) {
                $events[] = $data;
            }
        }

        return $events;
    }

    /**
     * Creates EventValidationDTO from event data.
     *
     * @param array<string,array<string,mixed>> $eventData
     */
    private function createEventFromData(array $eventData): ?EventValidationDTO
    {
        try {
            $event = new EventValidationDTO(self::NAME);
            $event->setOrganizer(self::ORGANIZER);

            $this->setBasicEventInfo($event, $eventData);
            $this->setEventDescription($event, $eventData);
            $this->setEventImage($event, $eventData);
            $this->setEventLocation($event, $eventData);
            $this->setEventDateTime($event, $eventData);

            return $event;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to create event from data', [
                'exception' => $e,
                'eventData' => $eventData,
            ]);

            return null;
        }
    }

    /**
     * @param array<string,mixed> $eventData
     */
    private function setBasicEventInfo(EventValidationDTO $event, array $eventData): void
    {
        $event->setTitle($this->ensureString($eventData['title'] ?? ''));
        $event->setLink($this->ensureString($eventData['eventUrl'] ?? ''));
    }

    /**
     * @param array<string,mixed> $eventData
     */
    private function setEventDescription(EventValidationDTO $event, array $eventData): void
    {
        $markdown = new Markdown();
        $description = $markdown->toHtml($this->ensureString($eventData['description'] ?? ''));
        $event->setDescription($this->ensureString($description));
    }

    /**
     * @param array<string,array<string,mixed>> $eventData
     */
    private function setEventImage(EventValidationDTO $event, array $eventData): void
    {
        /** @var string|null $groupRef */
        $groupRef = $eventData['group']['__ref'] ?? null;
        if (!$groupRef) {
            return;
        }

        /** @var array<string,array<string,mixed>> $group */
        $group = $this->findInApolloState($groupRef);

        $photoRef = $group['keyGroupPhoto']['__ref'] ?? null;
        if (!\is_string($photoRef)) {
            return;
        }

        $photo = $this->findInApolloState($photoRef);
        if ($photo && isset($photo['highResUrl'])) {
            $event->setImage($this->ensureString($photo['highResUrl']));
        }
    }

    /**
     * @param array<string,array<string,mixed>> $eventData
     */
    private function setEventLocation(EventValidationDTO $event, array $eventData): void
    {
        $venueRef = $eventData['venue']['__ref'] ?? null;
        if (!\is_string($venueRef)) {
            return;
        }

        $venue = $this->findInApolloState($venueRef);
        if (!$venue || !isset($venue['name'])) {
            return;
        }

        $location = $this->postalAddressRepository->findOneBy(['name' => $venue['name']]);
        if ($location) {
            $event->setLocation($location);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $eventData
     */
    private function setEventDateTime(EventValidationDTO $event, array $eventData): void
    {
        if (!isset($eventData['dateTime'])) {
            return;
        }

        try {
            $event->setStartAt(new \DateTimeImmutable($this->ensureString($eventData['dateTime'])));
        } catch (\Exception $e) {
            $this->logger->warning('Invalid date format in event data', [
                'dateTime' => $eventData['dateTime'],
                'exception' => $e,
            ]);
        }
    }

    /**
     * Finds an entity in Apollo state by reference.
     *
     * @return array<string,mixed>|null
     */
    private function findInApolloState(string $reference): ?array
    {
        return $this->apolloState[$reference] ?? null;
    }

    /**
     * Ensures the value is a string.
     */
    private function ensureString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
