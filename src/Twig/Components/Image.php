<?php

declare(strict_types=1);

namespace App\Twig\Components;

use League\Glide\Signatures\SignatureFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Image
{
    public ?string $src = null;
    public ?int $width = null;
    public ?int $height = null;
    public ?string $format = null;

    /**
     * Mode de redimensionnement Glide (crop, max, fill...).
     * Par défaut, Glide conserve les proportions dans la boîte demandée.
     */
    public ?string $fit = null;

    /**
     * Largeurs supplémentaires (séparées par des virgules) pour générer un
     * attribut `srcset`. Exemple : "400,800,1200".
     */
    public ?string $widths = null;

    /**
     * Attribut `sizes` associé au `srcset`.
     */
    public ?string $sizes = null;

    /**
     * Force un rendu statique (première image) pour les sources animées
     * (GIF, WebP animés). Utile pour les vignettes.
     */
    public bool $static = false;

    public function __construct(
        private readonly string $secret,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getUrl(): string
    {
        return $this->buildUrl($this->width, $this->height);
    }

    public function getSrcset(): ?string
    {
        $widths = $this->getWidths();

        if ([] === $widths || !$this->isResizable()) {
            return null;
        }

        $srcset = [];

        foreach ($widths as $width) {
            $height = null;

            if (null !== $this->width && null !== $this->height && $this->width > 0) {
                $height = (int) round($width * $this->height / $this->width);
            }

            $srcset[] = $this->buildUrl($width, $height).' '.$width.'w';
        }

        return implode(', ', $srcset);
    }

    /**
     * @return array<string, string|int>
     */
    public function getDefaultAttributes(): array
    {
        $attributes = ['src' => $this->getUrl()];

        if (null !== $this->width) {
            $attributes['width'] = $this->width;
        }

        if (null !== $this->height) {
            $attributes['height'] = $this->height;
        }

        $srcset = $this->getSrcset();

        if (null !== $srcset) {
            $attributes['srcset'] = $srcset;
            $attributes['sizes'] = $this->sizes ?? '100vw';
        }

        return $attributes;
    }

    /**
     * @return list<int>
     */
    private function getWidths(): array
    {
        if (null === $this->widths || '' === $this->widths) {
            return [];
        }

        $widths = [];

        foreach (explode(',', $this->widths) as $width) {
            $width = (int) mb_trim($width);

            if ($width > 0) {
                $widths[] = $width;
            }
        }

        return $widths;
    }

    private function buildUrl(?int $width, ?int $height): string
    {
        $parameters = [];

        if ($this->static) {
            $parameters['static'] = 1;
        }

        if (null !== $width) {
            $parameters['w'] = $width;
        }

        if (null !== $height) {
            $parameters['h'] = $height;
        }

        if (null !== $this->format) {
            $parameters['fm'] = $this->format;
        }

        if (null !== $this->fit) {
            $parameters['fit'] = $this->fit;
        }

        $parameters['url'] = $this->src;
        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature('', $parameters);

        return $this->urlGenerator->generate('image_glide').'?'.http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Les formats animés sont renvoyés tels quels par le contrôleur quand on ne
     * demande pas explicitement un rendu statique : inutile de générer un
     * `srcset`, les URLs pointeraient toutes vers le même fichier.
     */
    private function isResizable(): bool
    {
        $path = parse_url((string) $this->src, \PHP_URL_PATH);

        return !\is_string($path) || 1 !== preg_match('/\.(gif|webp)$/i', $path);
    }
}
