<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use Jsvrcek\ICS\CalendarExport;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Utility\Formatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShowIcsController extends AbstractController
{
    #[Route('/event.ics', name: 'app_event_ics', methods: ['GET'])]
    public function eventIcs(
        EventRepository $eventRepository,
    ): Response {
        $calendar = new Calendar();

        foreach ($eventRepository->findLatest() as $event) {
            $calendarEvent = new CalendarEvent();

            $title = $event->getTitle();
            if (null !== $title) {
                $calendarEvent->setSummary($title);
            }

            $description = $event->getDescription();
            if (null !== $description) {
                $calendarEvent->setDescription(strip_tags($description));
            }

            $url = $event->getLink();
            if (null !== $url) {
                $calendarEvent->setUrl($url);
            }

            $uuid = $event->getUuid();
            if (null !== $uuid) {
                $calendarEvent->setUid($uuid->toRfc4122());
            }

            $startAt = $event->getStartAt();
            if (null !== $startAt) {
                $calendarEvent->setStart(\DateTime::createFromImmutable($startAt));
            }

            if (null !== $event->getEndAt()) {
                $calendarEvent->setEnd(\DateTime::createFromImmutable($event->getEndAt()));
            }

            $calendar->addEvent($calendarEvent);
        }

        $calendarExport = (new CalendarExport(new CalendarStream(), new Formatter()))
            ->addCalendar($calendar);

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
