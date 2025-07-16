<?php

declare(strict_types=1);

namespace App\Tests\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\EventRetrieval\EventRetrievalPwn;
use App\Repository\PostalAddressRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EventRetrievalPwnTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;
    /** @var MockObject&PostalAddressRepository */
    private PostalAddressRepository $postalAddressRepository;
    private EventRetrievalPwn $eventRetrievalPwn;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->postalAddressRepository = $this->createMock(PostalAddressRepository::class);

        $this->eventRetrievalPwn = new EventRetrievalPwn(
            $this->httpClient,
            $this->postalAddressRepository
        );
    }

    public function testRetrieveEvents(): void
    {
        $htmlContent = file_get_contents(__DIR__.'/Fixtures/pwn_events.html');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($htmlContent);

        $eventResponse = $this->createMock(ResponseInterface::class);
        $eventResponse->method('getContent')->willReturn($this->getEventDetailHtml());

        $this->httpClient
            ->method('request')
            ->willReturnOnConsecutiveCalls($response, $eventResponse, $eventResponse, $eventResponse, $eventResponse);

        $events = $this->eventRetrievalPwn->retrieveEvents();

        $this->assertCount(4, $events);
        $this->assertInstanceOf(EventValidationDTO::class, $events[0]);
        $this->assertSame('Rewrite in RUST par Jérémy Lempereur', $events[0]->getTitle());
        $this->assertSame('https://pwn-association.org/evenements/rewrite-in-rust/', $events[0]->getLink());
        $this->assertNull($events[0]->getLocation()?->getName());
    }

    private function getEventDetailHtml(): string|false
    {
        return file_get_contents(__DIR__.'/Fixtures/pwn_events_detail.html');
    }
}
