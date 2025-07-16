<?php

declare(strict_types=1);

namespace App\Tests\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\EventRetrieval\EventRetrievalEMF;
use App\Repository\PostalAddressRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EventRetrievalEMFTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;
    /** @var MockObject&PostalAddressRepository */
    private PostalAddressRepository $postalAddressRepository;
    private EventRetrievalEMF $eventRetrievalEMF;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->postalAddressRepository = $this->createMock(PostalAddressRepository::class);

        $this->eventRetrievalEMF = new EventRetrievalEMF(
            $this->httpClient,
            $this->postalAddressRepository
        );
    }

    public function testRetrieveEvents(): void
    {
        $htmlContent = file_get_contents(__DIR__.'/Fixtures/emf_events.html');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($htmlContent);

        $eventResponse = $this->createMock(ResponseInterface::class);
        $eventResponse->method('getContent')->willReturn($this->getEventDetailHtml());

        $this->httpClient
            ->method('request')
            ->willReturnOnConsecutiveCalls($response, $eventResponse);

        $events = $this->eventRetrievalEMF->retrieveEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EventValidationDTO::class, $events[0]);
        $this->assertSame('Créer une planche de BD à l’aide de l\'IA - Espace Mendès France', $events[0]->getTitle());
        $this->assertSame('https://emf.fr/event/je-cree-une-planche-de-bd-a-laide-dune-ia/', $events[0]->getLink());
        $this->assertNull($events[0]->getLocation()?->getName());
    }

    private function getEventDetailHtml(): string|false
    {
        return file_get_contents(__DIR__.'/Fixtures/emf_event_detail.html');
    }
}
