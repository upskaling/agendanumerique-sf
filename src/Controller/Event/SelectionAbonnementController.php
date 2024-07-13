<?php

declare(strict_types=1);

namespace App\Controller\Event;

use App\Form\Model\SelectionAbonnementDTO;
use App\Form\SelectionAbonnementType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SelectionAbonnementController extends AbstractController
{
    #[Route('/event/selection-abonnement', name: 'app_event_selection_abonnement')]
    public function index(
        Request $request,
    ): Response {
        $selectionAbonnementDTO = new SelectionAbonnementDTO();

        $form = $this->createForm(
            SelectionAbonnementType::class,
            $selectionAbonnementDTO,
            [
                'method' => 'GET',
                'csrf_protection' => false,
            ]
        );

        $form->handleRequest($request);

        return $this->render('event/selection_abonnement/index.html.twig', [
            'form' => $form,
            'selection_abonnement_dto' => $selectionAbonnementDTO,
        ]);
    }
}
