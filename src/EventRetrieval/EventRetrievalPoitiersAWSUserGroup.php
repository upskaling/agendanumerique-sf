<?php

declare(strict_types=1);

namespace App\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Repository\PostalAddressRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventRetrievalPoitiersAWSUserGroup implements EventRetrievalInterface
{
    private const URI = 'https://www.meetup.com/fr-FR/poitiers-aws-user-group/events/';
    private const NAME = 'poitiers-aws-user-group';
    private const ORGANIZER = 'Poitiers AWS User Group';

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
            self::URI
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        /** @var EventValidationDTO[] $result */
        $result = $crawler->filter('ul.w-full > li')
            ->each(function (Crawler $crawler) {
                return $this->loadEvent($crawler);
            });

        return $result;
    }

    private function loadEvent(Crawler $crawler): EventValidationDTO
    {
        $event = new EventValidationDTO(self::NAME);
        $event->setOrganizer(self::ORGANIZER);

        $title = $crawler->filter('div:nth-child(1) > span:nth-child(2)')->text();
        $event->setTitle($title);

        $link = $crawler->filter('div:nth-child(1) > a:nth-child(1)')->attr('href');
        if (null !== $link) {
            $event->setLink($link);
        }

        try {
            $image = $crawler->filter('div img')->attr('src');
            $event->setImage($image);
        } catch (\InvalidArgumentException $e) {
            $this->logger->debug(
                'Image not found',
                [
                    'exception' => $e,
                ]
            );
        }

        $slugger = new AsciiSlugger();
        $slug = $slugger->slug(self::ORGANIZER.'-'.$title)->lower()->toString();
        $event->setSlug($slug);

        $description = $crawler->filter('div.md\:block > div')->html();
        $event->setDescription($description);

        $lieu = $crawler->filter('div:nth-child(1) > div:nth-child(3) > span:nth-child(2)')->text();
        if (false !== mb_strpos($lieu, 'Cobalt')) {
            $location = $this->postalAddressRepository->findOneBy(['name' => 'Cobalt']);
            $event->setLocation($location);
        }

        $date = $crawler->filter('div:nth-child(1) > time')->text();

        // "jeu. 14 nov. 2024, 19:00 CET"
        $date = $this->convertDate($date);

        if ($date) {
            $event->setStartAt($date);
        }

        return $event;
    }

    private function convertDate(string $date): \DateTimeImmutable|false
    {
        $date = str_replace(
            ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
            ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'],
            $date
        );

        $date = preg_replace('/^\w+\. /', '', $date);
        if (null === $date) {
            return false;
        }

        return \DateTimeImmutable::createFromFormat(
            'd m Y, H:i T',
            $date
        );
    }
}
