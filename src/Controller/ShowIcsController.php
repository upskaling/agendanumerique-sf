<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Utils\IcsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShowIcsController extends AbstractController
{
    #[Route('/event/{id}/ics', name: 'app_event_show_ics', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[Route('/event/{slug}/ics', name: 'app_event_show_slug_ics', methods: ['GET'])]
    public function showIcs(
        Event $event,
        IcsService $icsService,
    ): Response {
        $calendarExport = $icsService->getCalendar([$event]);

        return new Response(
            $calendarExport->getStream(),
            Response::HTTP_OK,
            [
                'content-type' => 'text/calendar',
                'content-disposition' => 'attachment; filename="'.$event->getSlug().'.ics"',
            ]
        );
    }

    #[Route('/event.ics', name: 'app_event_ics', methods: ['GET'])]
    public function eventIcs(
        EventRepository $eventRepository,
        IcsService $icsService
    ): Response {
        $calendarExport = $icsService->getCalendar(
            $eventRepository->findLatest()
        );

        return new Response(
            $calendarExport->getStream(),
            Response::HTTP_OK,
            [
                'content-type' => 'text/calendar',
                'content-disposition' => 'attachment; filename="agendanumerique.ics"',
            ]
        );
    }
}
