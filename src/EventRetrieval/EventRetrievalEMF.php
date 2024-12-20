<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EventRetrievalEMF implements EventRetrievalInterface
{
    private const URI = 'https://emf.fr';
    private const NAME = 'emf';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    /**
     * @return iterable<string>
     */
    public function getUrl(): iterable
    {
        yield $this::URI.'/event/une-ia-massiste-pour-creer-mon-site-web/';
        yield $this::URI.'/event/creer-un-chatbot-avec-scratch/';
        yield $this::URI.'/event/mon-premier-jeu-video-pac-man/';
        yield $this::URI.'/event/mon-premier-jeu-video-la-bataille-des-planetes/';
        yield $this::URI.'/event/brickanoid-gare-aux-briques/';
        yield $this::URI.'/event/mon-premier-jeu-video-ninja-fruits/';
        yield $this::URI.'/event/mon-premier-jeu-video-les-fous-du-volant/';
        yield $this::URI.'/event/mon-premier-jeu-video-snake-le-serpent-qui-se-mord-la-queue/';
        yield $this::URI.'/event/je-debute-avec-chatgpt/';
    }

    public function retrieveEvents(): array
    {
        $responses = [];
        foreach ($this->getUrl() as $url) {
            $responses[] = $this->httpClient->request(
                'GET',
                $url
            );
        }

        $eventValidationDTOList = [];
        foreach ($responses as $response) {
            $eventValidationDTOList = array_merge($eventValidationDTOList, $this->loadEvent($response));
        }

        return $eventValidationDTOList;
    }

    private function convertDate(string $date): \DateTimeImmutable|false
    {
        $date = str_replace(
            ['jan.', 'fév.', 'mars', 'avril', 'mai', 'juin', 'juil', 'août', 'sep', 'oct.', 'nov.', 'déc.'],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            $date
        );

        $date = explode(
            ' -> ',
            $date
        );

        return \DateTimeImmutable::createFromFormat(
            'd m Y H \h i',
            $date[0]
        );
    }

    /**
     * @return EventValidationDTO[]
     */
    private function loadEvent(ResponseInterface $response): array
    {
        $organizer = 'emf';

        $content = $response->getContent();

        $crawler = new Crawler($content);

        // .ec3_iconlet.ec3_past
        // cherche si le bloc date est grisé, si oui, date dépassée donc pas d'evenet à gérer
        try {
            $date = $crawler->filter('.ec3_iconlet.ec3_past')->text();

            return [];
        } catch (\InvalidArgumentException $e) {
        }

        $event = new EventValidationDTO(self::NAME);

        $event->setOrganizer($organizer);

        $lieuName = 'Espace Mendès France';
        $location = $this->postalAddressRepository->findOneBy(['name' => $lieuName]);
        $event->setLocation($location);

        $title = $crawler->filter('h1.elementor-heading-title')->text();
        $event->setTitle($title);
        $slugger = new AsciiSlugger();
        $slug = $slugger->slug($organizer.'-'.$title)->lower()->toString();
        $event->setSlug($title);

        $url = $crawler->filter("meta[property='og:url']")->attr('content');
        if ($url) {
            $event->setLink($url);
        }

        $image = $crawler->filter("meta[property='og:image']")->attr('content', '');
        if ('' !== $image) {
            $event->setImage($image);
        }

        $description = $crawler->filter("meta[property='og:description']")->attr('content');
        if ($description) {
            $event->setDescription($description);
        }

        $dates = $crawler->filter('.ec3_schedule_date.ec3_schedule_next')->each(
            function ($node) {
                return $this->convertDate($node->text()); // "18 juin 2024 14 h 00 -> 16 h 00"
            }
        );

        $eventsList = [];
        foreach ($dates as $date) {
            $events = clone $event;

            $events->setStartAt($date);
            $events->setSlug($events->getSlug().'-'.$date->format('Y-m-d'));

            $eventsList[] = $events;
        }

        return $eventsList;
    }
}
