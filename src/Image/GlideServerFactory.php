<?php

declare(strict_types=1);

namespace App\Image;

use Intervention\Image\Drivers\Gd\Driver;
use League\Glide\Server;
use League\Glide\ServerFactory;

/**
 * Construit le serveur Glide utilisé par `/_image`.
 *
 * Le décodage des animations est désactivé : les GIF et WebP animés sont
 * servis tels quels par le contrôleur (pour conserver l'animation), sauf
 * quand une vignette statique est demandée (`?static=1`). Dans ce cas Glide
 * ne décode que la première image, ce qui évite de télécharger plusieurs
 * mégaoctets pour une carte de la page d'accueil.
 */
final class GlideServerFactory
{
    public static function create(string $source, string $cache): Server
    {
        $driver = new Driver();
        $driver->config()->decodeAnimation = false;

        return ServerFactory::create([
            'source' => $source,
            'cache' => $cache,
            'driver' => $driver,
        ]);
    }
}
