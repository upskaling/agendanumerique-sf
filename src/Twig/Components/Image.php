<?php

declare(strict_types=1);

namespace App\Twig\Components;

use League\Glide\Signatures\SignatureFactory;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Image
{
    public ?string $src = null;
    public ?int $width = null;
    public ?int $height = null;
    public ?string $format = null;

    public function __construct(
        private string $secret,
    ) {
    }

    public function getParameterUrl()
    {
        $parameters = [];

        if (null !== $this->width) {
            $parameters['w'] = $this->width;
        }

        if (null !== $this->height) {
            $parameters['h'] = $this->height;
        }

        if (null !== $this->format) {
            $parameters['fm'] = $this->format;
        }

        $parameters['url'] = $this->src;

        $parameters['s'] = SignatureFactory::create($this->secret)->generateSignature('', $parameters);

        return $parameters;
    }
}
