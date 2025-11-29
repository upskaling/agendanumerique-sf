<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(
        EventRepository $eventRepository,
        Request $request,
    ): Response {
        /** @var int[] $selection */
        $selection = $request->get('selection');

        return $this->render('index/index.html.twig', [
            'events' => $eventRepository->findLatest($selection),
        ]);
    }
}
