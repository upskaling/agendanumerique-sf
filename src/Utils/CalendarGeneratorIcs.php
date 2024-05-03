<?php

declare(strict_types=1);

namespace App\Utils;

use App\Entity\Event;
use Jsvrcek\ICS\CalendarExport;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Utility\Formatter;

class CalendarGeneratorIcs
{
    /**
     * @param Event[] $events
     */
    public function getCalendar(array $events): CalendarExport
    {
        $calendar = new Calendar();

        foreach ($events as $event) {
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

        return (new CalendarExport(new CalendarStream(), new Formatter()))
            ->addCalendar($calendar);
    }
}
