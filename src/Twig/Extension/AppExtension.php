<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\AppJsonLdRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            // new TwigFilter('encodeJsonLd', [AppRuntime::class, 'doSomething']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('encodeJsonLd', [AppJsonLdRuntime::class, 'encodeJsonLd']),
        ];
    }
}
