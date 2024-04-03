<?php

declare(strict_types=1);

namespace App\Compil;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
    }

    public function compil(): void
    {
        $response = $this->httpClient->request(
            'GET',
            self::URI . "/agenda_1550.html"
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

    private function loadEvent(Crawler $crawler)
    {
        $organizer = 'cobaltpoitiers';


        $event = new Event();
        $event->setOrganizer($organizer);

        $title = $crawler->filter('p')->text();
        $event->setTitle($title);

        $event->setLink(self::URI . "/agenda_1550.html");

        $event->setSlugWithOrganizer($title);

        $description = $crawler->filter('.inscription')->html();
        $event->setDescription($description);

        $date = $crawler->filter('h3')->text();

        $date = \DateTimeImmutable::createFromFormat(
            'd/m',
            $date
        );
        $event->setStartAt($date);

        // $event->setEndAt();

        $errors = $this->validation->validate($event);

        if (0 === \count($errors)) {
            $this->entityManager->persist($event);
        }
    }
}
