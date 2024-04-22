<?php

declare(strict_types=1);

namespace App\Compil;

use App\Entity\Event;
use App\Repository\PostalAddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CompilCobaltPoitiers implements CompilInterface
{
    private const URI = 'https://www.cobaltpoitiers.fr';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ValidatorInterface $validation,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly PostalAddressRepository $postalAddressRepository,
    ) {
    }

    public function compil(): void
    {
        $response = $this->httpClient->request(
            'GET',
            self::URI.'/agenda_1550.html'
        );

        $content = $response->getContent();

        $crawler = new Crawler($content);

        // .agenda.elem
        $crawler->filter('.agenda.elem')
            ->each(function (Crawler $crawler) {
                $this->loadEvent($crawler);
            });

        $this->entityManager->flush();
    }

    private function loadEvent(Crawler $crawler): void
    {
        $event = new Event();
        $organizer = $crawler->filter('span:contains("Organisateur :")')->text();
        $organizer = explode('Organisateur : ', $organizer)[1];
        $event->setOrganizer($organizer);

        $title = $crawler->filter('p')->text();
        $event->setTitle($title);

        $event->setLink(self::URI.'/agenda_1550.html');

        try {
            $image = $crawler->filter('div.img img')->attr('src');
            $event->setImage(self::URI.$image);
        } catch (\InvalidArgumentException $e) {
            $this->logger->debug(
                'Image not found',
                [
                    'exception' => $e,
                ]
            );
        }

        $event->setSlugWithOrganizer($title);

        $description = $crawler->filter('.inscription')->html();
        $event->setDescription($description);

        $lieu = $crawler->filter('span:contains("Lieu :")')->text();
        $lieu = explode('Lieu : ', $lieu)[1];
        if ('Cobalt' === $lieu) {
            $location = $this->postalAddressRepository->findOneBy(['name' => $organizer]);
            $event->setLocation($location);
        }

        $date = $crawler->filter('h3')->text();

        $texte = $crawler->filter('span:contains("Heure")')->text();
        preg_match('/Heure\s*:\s*([\dh]+)/', $texte, $matches);
        $heure = isset($matches[1]) ? $matches[1] : '';

        $date = \DateTimeImmutable::createFromFormat(
            'd/m H\hi',
            $date.' '.$heure
        );

        if ($date) {
            $event->setStartAt($date);
        }

        // $event->setEndAt();

        $errors = $this->validation->validate($event);

        if (0 === \count($errors)) {
            $this->entityManager->persist($event);
        }
    }
}
