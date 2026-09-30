<?php

declare(strict_types=1);

namespace App\Tests\EventRetrieval;

use App\DTO\EventValidationDTO;
use App\EventRetrieval\EventRetrievalPwn;
use App\Repository\PostalAddressRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EventRetrievalPwnTest extends TestCase
{
    /** @var MockObject&HttpClientInterface */
    private HttpClientInterface $httpClient;
    /** @var MockObject&PostalAddressRepository */
    private PostalAddressRepository $postalAddressRepository;
    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;
    private EventRetrievalPwn $eventRetrievalPwn;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->postalAddressRepository = $this->createMock(PostalAddressRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->eventRetrievalPwn = new EventRetrievalPwn(
            $this->httpClient,
            $this->logger,
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

    public function testRetrieveEventsContinuesWhenAnEventIsUnavailable(): void
    {
        $htmlContent = file_get_contents(__DIR__.'/Fixtures/pwn_events.html');

        $listResponse = $this->createMock(ResponseInterface::class);
        $listResponse->method('getContent')->willReturn($htmlContent);

        $eventResponse = $this->createMock(ResponseInterface::class);
        $eventResponse->method('getContent')->willReturn($this->getEventDetailHtml());

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($listResponse, $eventResponse): ResponseInterface {
                if (str_ends_with($url, '/tous-les-evenements-pwn/')) {
                    return $listResponse;
                }

                if (str_contains($url, '/evenements/rewrite-in-rust/')) {
                    return $eventResponse;
                }

                throw new ClientException(new MockResponse('', ['http_code' => 404]));
            });

        $this->logger->expects($this->atLeastOnce())->method('warning');

        $events = $this->eventRetrievalPwn->retrieveEvents();

        $this->assertCount(1, $events);
        $this->assertSame('Rewrite in RUST par Jérémy Lempereur', $events[0]->getTitle());
    }

    public function testRetrieveEventsDeduplicatesLinks(): void
    {
        $listResponse = $this->createMock(ResponseInterface::class);
        $listResponse->method('getContent')->willReturn(<<<'HTML'
            <div class="upcoming-events">
                <div class="event-inner"><a href="/evenements/rewrite-in-rust/"></a></div>
            </div>
            <div class="upcoming-events">
                <div class="event-inner"><a href="/evenements/rewrite-in-rust/"></a></div>
            </div>
            HTML);

        $eventResponse = $this->createMock(ResponseInterface::class);
        $eventResponse->method('getContent')->willReturn($this->getEventDetailHtml());

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($listResponse, $eventResponse);

        $events = $this->eventRetrievalPwn->retrieveEvents();

        $this->assertCount(1, $events);
        $this->assertSame('Rewrite in RUST par Jérémy Lempereur', $events[0]->getTitle());
    }

    private function getEventDetailHtml(): string|false
    {
        return file_get_contents(__DIR__.'/Fixtures/pwn_events_detail.html');
    }
}
