<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(
        EventRepository $eventRepository,
    ): Response {
        return $this->render('index/index.html.twig', [
            'events' => $eventRepository->findAll(),
        ]);
    }
}
