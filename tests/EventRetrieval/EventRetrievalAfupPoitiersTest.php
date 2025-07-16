<?php

declare(strict_types=1);

namespace App\Tests\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\Entity\PostalAddress;
use App\EventRetrieval\EventRetrievalAfupPoitiers;
use App\Repository\PostalAddressRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EventRetrievalAfupPoitiersTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    /** @var MockObject&PostalAddressRepository */
    private PostalAddressRepository $postalAddressRepository;
    private EventRetrievalAfupPoitiers $eventRetrievalAfupPoitiers;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->postalAddressRepository = $this->createMock(PostalAddressRepository::class);

        $this->eventRetrievalAfupPoitiers = new EventRetrievalAfupPoitiers(
            $this->httpClient,
            $this->logger,
            $this->postalAddressRepository
        );
    }

    public function testRetrieveEvents(): void
    {
        $htmlContent = file_get_contents(__DIR__.'/Fixtures/afup_poitiers_events.html');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($htmlContent);

        $this->httpClient->method('request')
            ->with('GET', 'https://www.meetup.com/afup-poitiers-php/events/')
            ->willReturn($response);

        $this->postalAddressRepository->method('findOneBy')
            ->willReturnCallback(function ($criteria) {
                /** @var array<string,string> $criteria */
                if ('HTAG' === $criteria['name']) {
                    return (new PostalAddress())
                        ->setName('HTAG');
                }

                return null;
            });

        $events = $this->eventRetrievalAfupPoitiers->retrieveEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EventValidationDTO::class, $events[0]);
        $this->assertSame('Super Apéro PHP - "Comment j’ai survécu dans l’écosystème tech"', $events[0]->getTitle());
        $this->assertSame('https://www.meetup.com/afup-poitiers-php/events/306320685/?eventOrigin=group_events_list', $events[0]->getLink());
        $this->assertSame('HTAG', $events[0]->getLocation()?->getName());
    }
}
