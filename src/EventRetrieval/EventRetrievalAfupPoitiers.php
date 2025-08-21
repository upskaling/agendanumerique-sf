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
     *
     * @return array<EventValidationDTO>
     */
    public function retrieveEvents(): array
    {
        try {
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
     * @return array<string, mixed>
     */
    private function fetchApolloStateData(): array
    {
        $response = $this->httpClient->request('GET', self::URI);
        $content = $response->getContent();
        $crawler = new Crawler($content);

        $jsonData = $crawler->filter('#__NEXT_DATA__')->text();
        $decodedData = json_decode($jsonData, true, 512, \JSON_THROW_ON_ERROR);

        return $decodedData['props']['pageProps']['__APOLLO_STATE__'] ?? [];
    }

    /**
     * Processes Apollo state data to extract events.
     *
     * @param array<string, mixed> $apolloState
     *
     * @return array<EventValidationDTO>
     */
    private function processApolloState(array $apolloState): array
    {
        return array_values(
            array_map(
                fn (array $eventData) => $this->createEventFromData($eventData, $apolloState),
                $this->extractEventDataFromApolloState($apolloState)
            )
        );
    }

    /**
     * Extracts event data from Apollo state.
     *
     * @param array<string, mixed> $apolloState
     *
     * @return array<array>
     */
    private function extractEventDataFromApolloState(array $apolloState): array
    {
        return array_filter(
            $apolloState,
            fn (array $data) => ($data['__typename'] ?? null) === self::EVENT_TYPENAME
        );
    }

    /**
     * Creates EventValidationDTO from event data.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $apolloState
     */
    private function createEventFromData(array $eventData, array $apolloState): ?EventValidationDTO
    {
        try {
            $event = new EventValidationDTO(self::NAME);
            $event->setOrganizer(self::ORGANIZER);

            $this->setBasicEventInfo($event, $eventData);
            $this->setEventDescription($event, $eventData);
            $this->setEventImage($event, $eventData, $apolloState);
            $this->setEventLocation($event, $eventData, $apolloState);
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

    private function setBasicEventInfo(EventValidationDTO $event, array $eventData): void
    {
        $event->setTitle($eventData['title'] ?? '');
        $event->setLink($eventData['eventUrl'] ?? '');
    }

    private function setEventDescription(EventValidationDTO $event, array $eventData): void
    {
        $markdown = new Markdown();
        $description = $markdown->toHtml($eventData['description'] ?? '');
        $event->setDescription($description);
    }

    private function setEventImage(EventValidationDTO $event, array $eventData, array $apolloState): void
    {
        $groupRef = $eventData['group']['__ref'] ?? null;
        if (!$groupRef) {
            return;
        }

        $group = $this->findInApolloState($groupRef, $apolloState);
        $photoRef = $group['keyGroupPhoto']['__ref'] ?? null;
        if (!$photoRef) {
            return;
        }

        $photo = $this->findInApolloState($photoRef, $apolloState);
        if ($photo && isset($photo['highResUrl'])) {
            $event->setImage($photo['highResUrl']);
        }
    }

    private function setEventLocation(EventValidationDTO $event, array $eventData, array $apolloState): void
    {
        $venueRef = $eventData['venue']['__ref'] ?? null;
        if (!$venueRef) {
            return;
        }

        $venue = $this->findInApolloState($venueRef, $apolloState);
        if (!$venue || !isset($venue['name'])) {
            return;
        }

        $location = $this->postalAddressRepository->findOneBy(['name' => $venue['name']]);
        if ($location) {
            $event->setLocation($location);
        }
    }

    private function setEventDateTime(EventValidationDTO $event, array $eventData): void
    {
        if (!isset($eventData['dateTime'])) {
            return;
        }

        try {
            $event->setStartAt(new \DateTimeImmutable($eventData['dateTime']));
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
     * @return array<string, mixed>|null
     */
    private function findInApolloState(string $reference, array $apolloState): ?array
    {
        return $apolloState[$reference] ?? null;
    }
}
