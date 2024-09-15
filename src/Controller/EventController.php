<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/event')]
class EventController extends AbstractController
{
    #[Route('/', name: 'app_event_index', methods: ['GET'])]
    public function index(
        EventRepository $eventRepository,
    ): Response {
        return $this->render('event/index.html.twig', [
            'events' => $eventRepository->findAll(),
        ]);
    }

    #[Route('/feed.xml', name: 'app_event_feed', methods: ['GET'])]
    public function indexFeed(
        Request $request,
        EventRepository $eventRepository,
    ): Response {
        /** @var int[] $selection */
        $selection = $request->get('selection');

        return $this->render(
            'event/index.xml.twig',
            [
                'events' => $eventRepository->findLatest($selection),
            ],
            new Response(headers: ['Content-Type' => 'application/xml'])
        );
    }

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            return $this->redirectToRoute('app_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[Route('/{slug}', name: 'app_event_show_slug', methods: ['GET'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_delete', methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        /** @var string|null $token */
        $token = $request->request->get('_token');

        if ($this->isCsrfTokenValid('delete'.$event->getId(), $token)) {
            $entityManager->remove($event);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_event_index', [], Response::HTTP_SEE_OTHER);
    }
}
