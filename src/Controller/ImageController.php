<?php

declare(strict_types=1);

namespace App\Controller;

use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\Server;
use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/_image', name: 'image_glide')]
    public function index(
        HttpClientInterface $httpClient,
        Server $glide,
        Request $request,
        string $secret,
    ): Response {
        $parameters = $request->query->all();

        try {
            SignatureFactory::create($secret)->validateRequest('', $parameters);
        } catch (SignatureException $e) {
            throw new BadRequestHttpException('Invalid signature');
        }

        // `static` n'est pas un paramètre Glide : il sert uniquement à forcer
        // le rendu de la première image des sources animées (vignettes).
        $static = $request->query->getBoolean('static');
        unset($parameters['static']);

        /** @var string $url */
        $url = $request->query->get('url');

        $cache = new FilesystemAdapter();

        $imageName = md5($url);
        $cacheItem = $cache->getItem($imageName);

        if ($this->isImageDownload($cacheItem, $url)) {
            $responseImage = $httpClient->request('GET', $url);

            if (404 === $responseImage->getStatusCode()) {
                throw $this->createNotFoundException();
            }

            if (file_put_contents(
                $this->getParameter('app.image_dir').'/'.$imageName,
                $responseImage->getContent()
            )) {
                $cacheItem->set($imageName);
                $cache->save($cacheItem);
            }
        }

        /** @var string $path */
        $path = $cacheItem->get();

        if (!$static && $this->isImageGif($url)) {
            return $this->rawImageResponse($path);
        }

        $glide->setResponseFactory(new SymfonyResponseFactory($request));

        try {
            /** @var StreamedResponse $response */
            $response = $glide->getImageResponse($path, $parameters);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            // Certaines sources animées (GIF/WebP) ne sont pas décodables par
            // GD : on renvoie le fichier d'origine plutôt qu'une page d'erreur.
            if ($static && $this->isImageGif($url)) {
                return $this->rawImageResponse($path);
            }

            throw new BadRequestHttpException('Invalid image');
        }
    }

    /**
     * Renvoie l'image téléchargée telle quelle (sources animées ou formats
     * que Glide ne sait pas décoder).
     */
    private function rawImageResponse(string $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse($this->getParameter('app.image_dir').'/'.$path);
        $response->setPublic();
        $response->setMaxAge(31536000);

        return $response;
    }

    /**
     * si l'image doit être télécharger.
     */
    private function isImageDownload(
        CacheItem $cacheItem,
        string $url,
    ): bool {
        return !$cacheItem->isHit() || !file_exists($this->getParameter('app.image_dir').'/'.md5($url));
    }

    private function isImageGif(string $url): bool
    {
        foreach (['/\.gif$/', '/\.webp$/'] as $regex) {
            if (preg_match($regex, $url)) {
                return true;
            }
        }

        return false;
    }
}
