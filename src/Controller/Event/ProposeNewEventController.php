<?php

declare(strict_types=1);

namespace App\Controller\Event;

use App\DTO\EventValidationDTO;
use App\Form\ProposeNewType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProposeNewEventController extends AbstractController
{
    #[Route('/event/propose-new', name: 'app_event_propose_new')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $eventValidationDTO = new EventValidationDTO('propose-new');

        $form = $this->createForm(ProposeNewType::class, $eventValidationDTO);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $eventValidationDTO->toEntity();
            $entityManager->persist($event);
            $entityManager->flush();
            $this->addFlash('success', 'Votre événement a bien été proposé');

            return $this->redirectToRoute('app_index');
        }

        return $this->render('event/propose-new.html.twig', [
            'form' => $form,
        ]);
    }
}
