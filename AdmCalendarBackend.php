<?php

declare(strict_types=1);


use Admidio\Categories\Entity\Category;
use Admidio\Events\ValueObject\Participants;
use Admidio\Roles\Entity\Role;
use Admidio\Events\Entity\Event;

use Admidio\Users\Entity\User;
use Eluceo\iCal\Domain\Entity\Attendee;
use Eluceo\iCal\Domain\ValueObject\EmailAddress;
use Eluceo\iCal\Domain\ValueObject\Organizer;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Plugin;

use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\NotImplemented;


/**
 * Admidio CalDAV backend.
 *
 * This backend is used to connect Sabre DAV to Admidio.
 */
class AdmCalendarBackend extends AbstractBackend
{
    use AdmBackendFunctions;

    /**
     * Returns a list of calendars for a principal.
     *
     * Every project is an array with the following keys:
     *  * id, a unique id that will be used by other functions to modify the
     *    calendar. This can be the same as the uri or a database key.
     *  * uri. This is just the 'base uri' or 'filename' of the calendar.
     *  * principaluri. The owner of the calendar. Almost always the same as
     *    principalUri passed to this method.
     *
     * Furthermore it can contain webdav properties in clark notation. A very
     * common one is '{DAV:}displayname'.
     *
     * Many clients also require:
     * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
     * For this property, you can just return an instance of
     * Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet.
     *
     * If you return {http://sabredav.org/ns}read-only and set the value to 1,
     * ACL will automatically be put in read-only mode.
     *
     * @param string $principalUri
     *
     * @return array
     */
    public function getCalendarsForUser($principalUri)
    {
        global $gDb;

        $user = new User($gDb, userId: $this->getUserId($principalUri));
        $user->checkRolesRight();
        $visibleCalendarIds = $user->getAllVisibleCategories('EVT');

        $calendars = [];
        foreach ($visibleCalendarIds as $calendarId) {
            $calendar = new Category($gDb, $calendarId);
            $calendarUuid = $calendar->getValue('cat_uuid');
            $calendarName = $calendar->getValue('cat_name');

            $calendars[] = [
                'id' => $calendarUuid,
                'uri' => $calendarUuid,
                "{DAV:}displayname" => $calendarName,
                'principaluri' => $principalUri,
                '{http://sabredav.org/ns}read-only' => 1,
                '{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
                '{http://apple.com/ns/ical/}calendar-order' => $calendarId,
            ];
        }
        return $calendars;
    }

    /**
     * Creates a new calendar for a principal.
     *
     * If the creation was a success, an id must be returned that can be used
     * to reference this calendar in other methods, such as updateCalendar.
     *
     * @param string $principalUri
     * @param string $calendarUri
     *
     * @return string
     */
    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new NotImplemented('Adding calendars is not supported');
    }

    /**
     * Delete a calendar and all it's objects.
     *
     * @param mixed $calendarUuid
     */
    public function deleteCalendar($calendarUuid)
    {
        throw new NotImplemented('Deleting calendars is not supported');
    }

    /**
     * Returns all calendar objects within a calendar.
     *
     * Every item contains an array with the following keys:
     *   * calendardata - The iCalendar-compatible calendar data
     *   * uri - a unique key which will be used to construct the uri. This can
     *     be any arbitrary string, but making sure it ends with '.ics' is a
     *     good idea. This is only the basename, or filename, not the full
     *     path.
     *   * lastmodified - a timestamp of the last modification time
     *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
     *   '  "abcdef"')
     *   * size - The size of the calendar objects, in bytes.
     *   * component - optional, a string containing the type of object, such
     *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
     *     the Content-Type header.
     *
     * Note that the etag is optional, but it's highly encouraged to return for
     * speed reasons.
     *
     * The calendardata is also optional. If it's not returned
     * 'getCalendarObject' will be called later, which *is* expected to return
     * calendardata.
     *
     * If neither etag or size are specified, the calendardata will be
     * used/fetched to determine these numbers. If both are specified the
     * amount of times this is needed is reduced by a great degree.
     *
     * @param mixed $calendarUuid
     *
     * @return array
     */
    public function getCalendarObjects($calendarUuid)
    {
        global $gDb;

        $calendar = new Category($gDb);
        $calendar->readDataByUuid($calendarUuid);
        $events = new ModuleEvents();
        $events->setCalendarNames(arrCalendarNames: [$calendar->getValue('cat_name')]);
        $eventsResult = $events->getDataSet();

        $result = [];
        foreach ($eventsResult['recordset'] as $event) {
            $lastModified = new DateTime($event['dat_timestamp_change'] ?? $event['dat_timestamp_create']);
            if ($event['dat_rol_id']) {
                $role = new Role($gDb, $event['dat_rol_id']);
                $attendeesLastModified = $this->getParticipantChangeDate($role->getValue('rol_uuid'));
                if ($attendeesLastModified > $lastModified) {
                    $lastModified = $attendeesLastModified;
                }
            }

            $result[] = [
                'id' => $event['dat_id'],
                'uri' => $event['dat_uuid'] . '.ics',
                'lastmodified' => $lastModified->getTimestamp(),
                'component' => strtolower('VEVENT'),
            ];
        }
        return $result;
    }

    /**
     * Returns information from a single calendar object, based on it's object
     * uri.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * The returned array must have the same keys as getCalendarObjects. The
     * 'calendardata' object is required here though, while it's not required
     * for getCalendarObjects.
     *
     * This method must return null if the object did not exist.
     *
     * @param mixed  $calendarUuid
     * @param string $objectUri
     *
     * @return array|null
     */
    public function getCalendarObject($calendarUuid, $objectUri)
    {
        global $gDb, $gCurrentUser;

        $calendar = new Category($gDb);
        $calendar->readDataByUuid($calendarUuid);

        $eventUuid = str_replace('.ics', '', $objectUri);

        $event = new Event($gDb);
        $event->readDataByUuid($eventUuid);

        $lastModified = new DateTime($event->getValue('dat_timestamp_change') ?? $event->getValue('dat_timestamp_create'));

        $iCalEvent = new Eluceo\iCal\Domain\Entity\Event(new Eluceo\iCal\Domain\ValueObject\UniqueIdentifier($eventUuid));
        $iCalEvent->setSummary($event->getValue('dat_headline'));
        $iCalEvent->setDescription(strip_tags($event->getValue('dat_description')));
        $iCalEvent->setLocation(new \Eluceo\iCal\Domain\ValueObject\Location($event->getValue('dat_location')));

        $iCalEvent->touch(new Eluceo\iCal\Domain\ValueObject\Timestamp(new DateTimeImmutable($lastModified->format('c'))));

        if ($event->getValue('dat_all_day')) {
            if ($event->getValue('dat_begin', 'Y-m-d') === $event->getValue('dat_end', 'Y-m-d')) {
                $iCalEvent->setOccurrence(new \Eluceo\iCal\Domain\ValueObject\SingleDay(
                    new \Eluceo\iCal\Domain\ValueObject\Date(new DateTimeImmutable($event->getValue('dat_begin', 'Y-m-d')))
                ));
            } else {
                $iCalEvent->setOccurrence(new \Eluceo\iCal\Domain\ValueObject\MultiDay(
                    new \Eluceo\iCal\Domain\ValueObject\Date(new DateTimeImmutable($event->getValue('dat_begin', 'Y-m-d'))),
                    new \Eluceo\iCal\Domain\ValueObject\Date(new DateTimeImmutable($event->getValue('dat_end', 'Y-m-d')))
                ));
            }
        } else {
            $iCalEvent->setOccurrence(new \Eluceo\iCal\Domain\ValueObject\TimeSpan(
                new \Eluceo\iCal\Domain\ValueObject\DateTime(new DateTimeImmutable($event->getValue('dat_begin', 'Y-m-d H:i:s')), false),
                new \Eluceo\iCal\Domain\ValueObject\DateTime(new DateTimeImmutable($event->getValue('dat_end', 'Y-m-d H:i:s')), false)
            ));
        }
        if ($event->allowedToParticipate()) {
            $roleId = (int) $event->getValue('dat_rol_id');
            if ($gCurrentUser->hasRightViewRole($roleId)) {
                $participants = new Participants($gDb, $roleId);
                $participantsArray = $participants->getParticipantsArray();

                $attendees = [];
                foreach ($participantsArray as $participant) {
                    $user = new User($gDb, userId: $participant['usrId']);
                    $email = new EmailAddress($user->getValue('EMAIL') ?: 'unknown@example.com');
                    $attendee = new Attendee($email);
                    $attendee->setParticipationStatus($this->mapParticipationStatus($participant["approved"]));
                    $displayName = trim($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
                    $attendee->setDisplayName($displayName);
                    $attendees[] = $attendee;
                    if ($participant["leader"]) {
                        $organizer = new Organizer($email);
                        $iCalEvent->setOrganizer($organizer); // iCal only allows one organizer per event
                    }
                }
                $iCalEvent->setAttendees($attendees);
            }
            $attendeesLastModified = $this->getParticipantChangeDate($roleId);
            if ($attendeesLastModified > $lastModified) {
                $lastModified = $attendeesLastModified;
            }
        }

        $calendar = new Eluceo\iCal\Domain\Entity\Calendar([$iCalEvent]);
        $componentFactory = new Eluceo\iCal\Presentation\Factory\CalendarFactory();
        $calendarData = strval($componentFactory->createCalendar($calendar));

        return [
            'id' => $event->getValue('dat_id'),
            'uri' => $event->getValue('dat_uuid') . '.ics',
            'lastmodified' => (int) $lastModified->getTimestamp(),
            'calendardata' => $calendarData,
            'component' => strtolower('VEVENT'),
        ];
    }

    /**
     * Creates a new calendar object.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed  $calendarUuid
     * @param string $objectUri
     * @param string $calendarData
     *
     * @return string|null
     */
    public function createCalendarObject($calendarUuid, $objectUri, $calendarData)
    {
        throw new NotImplemented(message: 'Creating calendar objects is not supported');
    }

    /**
     * Updates an existing calendarobject, based on it's uri.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed  $calendarUuid
     * @param string $objectUri
     * @param string $calendarData
     *
     * @return string|null
     */
    public function updateCalendarObject($calendarUuid, $objectUri, $calendarData)
    {
        throw new NotImplemented(message: 'Updating calendar objects is not supported');
    }

    /**
     * Deletes an existing calendar object.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * @param mixed  $calendarUuid
     * @param string $objectUri
     */
    public function deleteCalendarObject($calendarUuid, $objectUri)
    {
        throw new NotImplemented('Deleting calendar objects is not supported');
    }
}