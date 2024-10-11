<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal', name: 'app_legal')]
class LegalController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('legal/mentions_legales.html.twig');
    }
}
