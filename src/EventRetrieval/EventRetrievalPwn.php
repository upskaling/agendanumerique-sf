<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventRetrievalPwn implements EventRetrievalInterface
{
    private const URI = 'https://pwn-association.org';
    private const NAME = 'pwn';
    private const ORGANIZER = 'pwn';

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
            self::URI.'/tous-les-evenements-pwn/',
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        $result = [];
        foreach ($this->extractEventLinks($crawler) as $link) {
            $event = $this->loadEventSafely($link);

            if (null !== $event) {
                $result[] = $event;
            }
        }

        return $result;
    }

    /**
     * Récupère les liens des événements listés sur la page des événements.
     *
     * La page peut lister deux fois le même événement (section "à venir" puis
     * "prochaine saison"), les liens sont donc dédupliqués.
     *
     * @return list<string>
     */
    private function extractEventLinks(Crawler $crawler): array
    {
        $links = $crawler->filter('.upcoming-events .event-inner>a')
            ->each(static fn (Crawler $event): ?string => $event->attr('href'));

        return array_values(array_unique(array_filter($links)));
    }

    /**
     * Charge un événement sans interrompre la récupération si la page est
     * indisponible (lien périmé renvoyant une 404, erreur réseau, ...).
     */
    private function loadEventSafely(string $link): ?EventValidationDTO
    {
        try {
            return $this->loadEvent($link);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Unable to load the pwn event "%s"', $link), ['exception' => $e]);
        }

        return null;
    }

    /**
     * Convertit une date au format français "dd mois yyyy à HH:mm" en objet DateTimeImmutable.
     *
     * @param string $date Format attendu : "dd mois yyyy à HH:mm"
     */
    private function convertDate(string $date): \DateTimeImmutable|false
    {
        // Tableau de correspondance des mois en français
        $monthsFr = [
            'janvier' => '01',
            'février' => '02',
            'mars' => '03',
            'avril' => '04',
            'mai' => '05',
            'juin' => '06',
            'juillet' => '07',
            'août' => '08',
            'septembre' => '09',
            'octobre' => '10',
            'novembre' => '11',
            'décembre' => '12',
        ];

        try {
            // Nettoie la chaîne d'entrée
            $date = mb_strtolower(mb_trim($date));

            // Parse le format "dd mois yyyy à HH:mm"
            if (!preg_match('/^(\d{2}) ([a-zéû]+) (\d{4}) à (\d{2}):(\d{2})$/', $date, $matches)) {
                return false;
            }

            [, $day, $month, $year, $hour, $minute] = $matches;

            // Vérifie si le mois existe dans notre tableau
            if (!isset($monthsFr[$month])) {
                return false;
            }

            // Construit la date au format Y-m-d H:i
            $dateStr = \sprintf(
                '%s-%s-%s %s:%s',
                $year,
                $monthsFr[$month],
                $day,
                $hour,
                $minute
            );

            // Crée l'objet DateTimeImmutable
            return new \DateTimeImmutable($dateStr);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function loadEvent(string $link): EventValidationDTO
    {
        $url = self::URI.$link;

        $response = $this->httpClient->request(
            'GET',
            $url
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);
        $event = new EventValidationDTO(self::NAME);

        $event->setOrganizer(self::ORGANIZER);

        // .event-title
        $title = $crawler->filter('.event-title')->text('');
        if ('' === $title) {
            throw new EventRetrievalException(\sprintf('No title found on "%s"', $url));
        }

        $event->setTitle($title);

        $event->setLink($url);

        $slugger = new AsciiSlugger();
        $slug = $slugger->slug(self::ORGANIZER.'-'.$title)->lower()->toString();
        $event->setSlug($slug);

        // meta[property='og:image']
        $image = $crawler->filter("meta[property='og:image']")->attr('content', '');
        if ($image) {
            $event->setImage($image);
        }

        // .event-description
        $description = $crawler->filter('.event-description');
        $event->setDescription($description->html());

        $lieu = $crawler->filter('.event-description > ul:nth-child(2) > li:nth-child(1) > a')->text('');
        $lieuName = 'La Taverne Du Geek';
        if (str_contains($lieu, $lieuName)) {
            $location = $this->postalAddressRepository->findOneBy(['name' => $lieuName]);
            $event->setLocation($location);
        }

        $dateText = $description->filter('ul:nth-child(2) > li:nth-child(2)')->text('');
        // ex: "Date et heure : mars 13, 2024 à 19:00 – mars 13, 2024 à 21:00"

        // on retire "Date et heure : "
        $dateText = str_replace('Date et heure : ', '', $dateText);
        // ex: "mars 13, 2024 à 19:00 – mars 13, 2024 à 21:00"
        // on explode
        $dateText = array_values(array_filter(explode(' – ', $dateText)));
        if ([] === $dateText) {
            throw new EventRetrievalException(\sprintf('No date found on "%s"', $url));
        }

        $startAt = $this->convertDate($dateText[0]);
        if ($startAt) {
            $event->setStartAt($startAt);
        }

        if (\count($dateText) > 1) {
            $endAt = $this->convertDate($dateText[1]);
            if ($endAt) {
                $event->setEndAt($endAt);
            }
        }

        return $event;
    }
}
