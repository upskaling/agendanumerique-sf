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

        /** @var string $url */
        $url = $request->get('url');

        $cache = new FilesystemAdapter();

        $cacheItem = $cache->getItem($url);

        if ($this->isImageDownload($cacheItem, $url)) {
            $imageName = md5($url);

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

        if ($this->isImageGif($url)) {
            return new BinaryFileResponse(
                $this->getParameter('app.image_dir').'/'.$cacheItem->get()
            );
        }

        $glide->setResponseFactory(new SymfonyResponseFactory($request));

        try {
            /** @var string $path */
            $path = $cacheItem->get();

            /** @var StreamedResponse $response */
            $response = $glide->getImageResponse($path, $parameters);

            return $response;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            throw new BadRequestHttpException('Invalid image');
        }
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
