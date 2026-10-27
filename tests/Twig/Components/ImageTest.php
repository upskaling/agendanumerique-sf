<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Twig\Components\Image;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ImageTest extends TestCase
{
    public function testDefaultAttributesContainSizeAndSignedUrl(): void
    {
        $image = $this->createImage('https://example.com/image.jpg');
        $image->width = 200;
        $image->height = 100;
        $image->format = 'webp';
        $image->fit = 'crop';

        $attributes = $image->getDefaultAttributes();

        $this->assertSame(200, $attributes['width']);
        $this->assertSame(100, $attributes['height']);
        $this->assertStringStartsWith('/_image?', (string) $attributes['src']);
        $this->assertStringContainsString('w=200', (string) $attributes['src']);
        $this->assertStringContainsString('h=100', (string) $attributes['src']);
        $this->assertStringContainsString('fit=crop', (string) $attributes['src']);
        $this->assertStringContainsString('fm=webp', (string) $attributes['src']);
        $this->assertArrayNotHasKey('srcset', $attributes);
    }

    public function testSrcsetKeepsAspectRatioAndHasOneSignaturePerWidth(): void
    {
        $image = $this->createImage('https://example.com/image.jpg');
        $image->width = 1200;
        $image->height = 800;
        $image->format = 'webp';
        $image->widths = '400, 800, 1200';
        $image->sizes = '(min-width: 640px) 600px, 100vw';

        $attributes = $image->getDefaultAttributes();
        $srcset = (string) $attributes['srcset'];

        $this->assertSame('(min-width: 640px) 600px, 100vw', $attributes['sizes']);
        $this->assertCount(3, explode(', ', $srcset));

        $this->assertMatchesRegularExpression('#w=400&h=267&.*400w#', $srcset);
        $this->assertMatchesRegularExpression('#w=800&h=533&.*800w#', $srcset);
        $this->assertMatchesRegularExpression('#w=1200&h=800&.*1200w#', $srcset);

        $signatures = [];

        foreach (explode(', ', $srcset) as $entry) {
            preg_match('#[?&]s=([a-f0-9]+)#', $entry, $matches);
            $signatures[] = $matches[1] ?? '';
        }

        $this->assertCount(3, array_unique($signatures), 'Chaque largeur doit avoir sa propre signature.');
    }

    public function testRenderedAnimatedSourcesDoNotGetSrcset(): void
    {
        foreach (['https://example.com/anim.gif', 'https://example.com/anim.webp'] as $url) {
            $image = $this->createImage($url);
            $image->width = 200;
            $image->height = 100;
            $image->widths = '200,400';
            $image->static = true;

            $this->assertNull($image->getSrcset(), $url.' ne doit pas générer de srcset.');
        }
    }

    public function testStaticFlagIsAddedToTheSignedUrl(): void
    {
        $image = $this->createImage('https://example.com/anim.gif');
        $image->width = 200;
        $image->height = 100;
        $image->static = true;

        $this->assertStringContainsString('static=1', $image->getUrl());
    }

    private function createImage(string $src): Image
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturn('/_image');

        $image = new Image('secret', $urlGenerator);
        $image->src = $src;

        return $image;
    }
}
