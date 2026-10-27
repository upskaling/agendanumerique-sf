<?php

declare(strict_types=1);

namespace App\Tests\Twig\Runtime;

use App\Entity\Event;
use App\Entity\PostalAddress;
use App\Twig\Runtime\AppJsonLdRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppJsonLdRuntimeTest extends TestCase
{
    private AppJsonLdRuntime $runtime;

    protected function setUp(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(
                static function (string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                    $slug = $parameters['slug'] ?? '';

                    if (!\is_string($slug)) {
                        throw new \InvalidArgumentException('The "slug" parameter must be a string.');
                    }

                    return 'https://agendanumerique.test/event/'.$slug;
                },
            );

        $this->runtime = new AppJsonLdRuntime($urlGenerator);
    }

    public function testEncodeJsonLdFollowsSchemaOrgBestPractices(): void
    {
        $event = $this->createEvent();
        $event->setTitle('Conférence <b>Symfony</b> & "PHP" — 1 < 2');
        $event->setImage('https://example.com/image.png');

        $json = $this->decode($this->runtime->encodeJsonLd($event));

        $this->assertSame('https://schema.org', $json['@context']);
        $this->assertSame('Event', $json['@type']);
        $this->assertSame('https://schema.org/EventScheduled', $json['eventStatus']);
        $this->assertSame('Conférence Symfony & "PHP" — 1 < 2', $json['name']);
        $this->assertSame('Une conférence sur Symfony', $json['description']);
        $this->assertSame('2026-11-05T18:30:00+01:00', $json['startDate']);
        $this->assertSame('2026-11-05T20:00:00+01:00', $json['endDate']);
        $this->assertSame('https://agendanumerique.test/event/conference-symfony', $json['url']);
        $this->assertSame('https://example.com/image.png', $json['image']);
        $this->assertSame(['https://example.com/event'], $json['sameAs']);
        $this->assertSame([
            '@type' => 'Organization',
            'name' => 'Symfony Community',
        ], $json['organizer']);
    }

    public function testLocationOnlyContainsKnownAddressFields(): void
    {
        $json = $this->decode($this->runtime->encodeJsonLd($this->createEvent()));

        $this->assertSame([
            '@type' => 'Place',
            'name' => 'Espace Mendès France',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '1 place de la Cathédrale',
                'addressLocality' => 'Poitiers',
                'postalCode' => '86000',
                'addressCountry' => 'FR',
            ],
        ], $json['location']);
    }

    public function testEndDateBeforeStartDateIsOmitted(): void
    {
        $event = $this->createEvent();
        $event->setEndAt(new \DateTimeImmutable('2026-11-05T17:00:00+01:00'));

        $json = $this->decode($this->runtime->encodeJsonLd($event));

        $this->assertArrayNotHasKey('endDate', $json);
    }

    public function testJsonLdCannotBreakOutOfScriptTag(): void
    {
        $event = $this->createEvent();
        $event->setDescription('Texte &lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;');

        $html = $this->runtime->encodeJsonLd($event);

        $this->assertSame(1, substr_count($html, '</script>'), 'The script tag must not be closed by the payload.');
        $this->assertStringNotContainsString('<script>alert', $html);

        $description = $this->decode($html)['description'] ?? null;
        $this->assertIsString($description);
        $this->assertStringContainsString('alert(1)', $description);
    }

    public function testEncodeEventListJsonLdBuildsAValidItemList(): void
    {
        $first = $this->createEvent();
        $first->setDescription(str_repeat('Un événement très intéressant. ', 20));

        $second = $this->createEvent();
        $second->setSlug('autre-evenement');
        $second->setTitle('Autre événement');
        $second->setLink('https://example.com/autre');

        $json = $this->decode($this->runtime->encodeEventListJsonLd([$first, $second], 'https://agendanumerique.test/'));

        $this->assertSame('ItemList', $json['@type']);
        $this->assertSame('https://agendanumerique.test/', $json['url']);
        $this->assertSame(2, $json['numberOfItems']);
        $this->assertSame('https://schema.org/ItemListOrderAscending', $json['itemListOrder']);

        $itemListElements = $json['itemListElement'] ?? null;
        $this->assertIsArray($itemListElements);
        $this->assertCount(2, $itemListElements);

        $firstElement = $itemListElements[0] ?? null;
        $this->assertIsArray($firstElement);
        $this->assertSame(1, $firstElement['position'] ?? null);

        $firstItem = $firstElement['item'] ?? null;
        $this->assertIsArray($firstItem);
        $this->assertSame('Event', $firstItem['@type'] ?? null);
        $this->assertArrayNotHasKey('@context', $firstItem);
        $this->assertSame('https://agendanumerique.test/event/conference-symfony', $firstItem['url'] ?? null);

        $description = $firstItem['description'] ?? null;
        $this->assertIsString($description);
        $this->assertLessThanOrEqual(160, mb_strlen($description));
        $this->assertStringEndsWith('…', $description);
    }

    private function createEvent(): Event
    {
        $location = new PostalAddress();
        $location->setName('Espace Mendès France');
        $location->setStreetAddress('1 place de la Cathédrale');
        $location->setAddressLocality('Poitiers');
        $location->setPostalCode('86000');
        $location->setAddressCountry('fr');

        $event = new Event();
        $event->setTitle('Conférence Symfony');
        $event->setSlug('conference-symfony');
        $event->setLink('https://example.com/event');
        $event->setDescription('Une conférence sur Symfony');
        $event->setOrganizer('Symfony Community');
        $event->setSource('website');
        $event->setStartAt(new \DateTimeImmutable('2026-11-05T18:30:00+01:00'));
        $event->setEndAt(new \DateTimeImmutable('2026-11-05T20:00:00+01:00'));
        $event->setLocation($location);

        return $event;
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $html): array
    {
        $result = preg_match('~<script type="application/ld\+json">(.*)</script>~s', $html, $matches);
        $this->assertSame(1, $result, 'A single JSON-LD script tag is expected.');

        $json = json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($json);

        return $json;
    }
}
