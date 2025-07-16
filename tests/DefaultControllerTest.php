<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    /**
     * @dataProvider getPublicUrls
     */
    public function testPublicUrls(string $url = ''): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful(\sprintf('The %s public URL loads correctly.', $url));
    }

    /**
     * @return \Generator<array{string}>
     */
    public function getPublicUrls(): \Generator
    {
        yield ['/'];
        yield ['/event.ics'];
        yield ['/event/feed.xml'];
    }
}
