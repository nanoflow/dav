<?php

declare(strict_types=1);


use Admidio\Categories\Entity\Category;
use Admidio\Users\Entity\User;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Backend\SubscriptionSupport;
use Sabre\CalDAV\Backend\SchedulingSupport;
use Sabre\CalDAV\Backend\SharingSupport;
use Sabre\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\PropPatch;

/**
 * PDO CalDAV backend.
 *
 * This backend is used to store calendar-data in a PDO database, such as
 * sqlite or MySQL
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class AdmCalendarBackend extends AbstractBackend implements SyncSupport, SubscriptionSupport, SchedulingSupport, SharingSupport
{
    use AdmBackendFunctions;

    /**
     * We need to specify a max date, because we need to stop *somewhere*.
     *
     * On 32 bit system the maximum for a signed integer is 2147483647, so
     * MAX_DATE cannot be higher than date('Y-m-d', 2147483647) which results
     * in 2038-01-19 to avoid problems when the date is converted
     * to a unix timestamp.
     */
    const MAX_DATE = '2038-01-01';

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
                'share-access' => 2, // 1 = owner, 2 = readonly, 3 = readwrite
                'read-only' => true, // read-only is for backwards compatibility. Might go away in the future.
                // '{' . Plugin::NS_CALENDARSERVER . '}getctag' => "http:\/\/sabre.io\/ns\/sync\/3",
                // '{http://sabredav.org/ns}sync-token' => '3',
                '{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
                '{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp('opaque'),
                // 'share-resource-uri' => '/ns/share/' . $calendarUuid,
                // "{urn:ietf:params:xml:ns:caldav}calendar-description" => null,
                // "{urn:ietf:params:xml:ns:caldav}calendar-timezone" => null,
                '{http://apple.com/ns/ical/}calendar-order' => $calendarId,
                // "{http://apple.com/ns/ical/}calendar-color" => null,
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
        throw new NotImplemented('Adding calendars is not yet supported');
    }

    /**
     * Updates properties for a calendar.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documentation for more info and examples.
     *
     * @param mixed $calendarUuid
     */
    public function updateCalendar($calendarUuid, PropPatch $propPatch)
    {

    }

    /**
     * Delete a calendar and all it's objects.
     *
     * @param mixed $calendarUuid
     */
    public function deleteCalendar($calendarUuid)
    {
        throw new NotImplemented('Deleting calendars is not yet supported');
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
            $lastModified = $event['dat_timestamp_change'] ?? $event['dat_timestamp_create'];
            $result[] = [
                'id' => $event['dat_id'],
                'uri' => $event['dat_uuid'] . '.ics',
                'lastmodified' => (int) (new DateTime($lastModified))->getTimestamp(),
                'etag' => md5($lastModified),
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
        global $gDb;

        $calendar = new Category($gDb);
        $calendar->readDataByUuid($calendarUuid);

        $eventId = str_replace('.ics', '', $objectUri);
        $events = new ModuleEvents();
        $events->setCalendarNames(arrCalendarNames: [$calendar->getValue('cat_name')]);
        $events->setParameter('dat_uuid', $eventId);
        $count = $events->getDataSetCount();
        if ($count == 0) {
            return null;
        }
        if ($count > 1) {
            throw new Exception('Found more than one event with id ' . $eventId . '!');
        }
        $event = $events->getDataSet()['recordset'][0];
        $lastModified = $event['dat_timestamp_change'] ?? $event['dat_timestamp_create'];

        return [
            'id' => $event['dat_id'],
            'uri' => $event['dat_uuid'] . '.ics',
            'lastmodified' => (int) (new DateTime($lastModified))->getTimestamp(),
            'etag' => md5($lastModified),
            'calendardata' => strval($events->getICalContent()),
            'component' => strtolower('VEVENT'),
        ];
    }

    /**
     * Returns a list of calendar objects.
     *
     * This method should work identical to getCalendarObject, but instead
     * return all the calendar objects in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param mixed $calendarUuid
     *
     * @return array
     */
    public function getMultipleCalendarObjects($calendarUuid, array $uris)
    {
        $result = [];
        // TODO maybe this can be optimized to get all objects in one database call.
        foreach ($uris as $uri) {
            $result[] = $this->getCalendarObject($calendarUuid, $uri);
        }

        return $result;
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
        throw new NotImplemented('Creating calendar objects is not yet supported');
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
        throw new NotImplemented('Updating calendar objects is not yet supported');
    }

    /**
     * Parses some information from calendar objects, used for optimized
     * calendar-queries.
     *
     * Returns an array with the following keys:
     *   * etag - An md5 checksum of the object without the quotes.
     *   * size - Size of the object in bytes
     *   * componentType - VEVENT, VTODO or VJOURNAL
     *   * firstOccurence
     *   * lastOccurence
     *   * uid - value of the UID property
     *
     * @param string $calendarData
     *
     * @return array
     */
    protected function getDenormalizedData($calendarData)
    {
        throw new NotImplemented('Getting denormalized data is not yet supported');
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
        throw new NotImplemented('Deleting calendar objects is not yet supported');
    }

    /**
     * Performs a calendar-query on the contents of this calendar.
     *
     * The calendar-query is defined in RFC4791 : CalDAV. Using the
     * calendar-query it is possible for a client to request a specific set of
     * object, based on contents of iCalendar properties, date-ranges and
     * iCalendar component types (VTODO, VEVENT).
     *
     * This method should just return a list of (relative) urls that match this
     * query.
     *
     * The list of filters are specified as an array. The exact array is
     * documented by \Sabre\CalDAV\CalendarQueryParser.
     *
     * Note that it is extremely likely that getCalendarObject for every path
     * returned from this method will be called almost immediately after. You
     * may want to anticipate this to speed up these requests.
     *
     * This method provides a default implementation, which parses *all* the
     * iCalendar objects in the specified calendar.
     *
     * This default may well be good enough for personal use, and calendars
     * that aren't very large. But if you anticipate high usage, big calendars
     * or high loads, you are strongly advised to optimize certain paths.
     *
     * The best way to do so is override this method and to optimize
     * specifically for 'common filters'.
     *
     * Requests that are extremely common are:
     *   * requests for just VEVENTS
     *   * requests for just VTODO
     *   * requests with a time-range-filter on a VEVENT.
     *
     * ..and combinations of these requests. It may not be worth it to try to
     * handle every possible situation and just rely on the (relatively
     * easy to use) CalendarQueryValidator to handle the rest.
     *
     * Note that especially time-range-filters may be difficult to parse. A
     * time-range filter specified on a VEVENT must for instance also handle
     * recurrence rules correctly.
     * A good example of how to interpret all these filters can also simply
     * be found in \Sabre\CalDAV\CalendarQueryFilter. This class is as correct
     * as possible, so it gives you a good idea on what type of stuff you need
     * to think of.
     *
     * This specific implementation (for the PDO) backend optimizes filters on
     * specific components, and VEVENT time-ranges.
     *
     * @param mixed $calendarUuid
     *
     * @return array
     */
    public function calendarQuery($calendarUuid, array $filters)
    {
        return [];
    }

    /**
     * Searches through all of a users calendars and calendar objects to find
     * an object with a specific UID.
     *
     * This method should return the path to this object, relative to the
     * calendar home, so this path usually only contains two parts:
     *
     * calendarpath/objectpath.ics
     *
     * If the uid is not found, return null.
     *
     * This method should only consider * objects that the principal owns, so
     * any calendars owned by other principals that also appear in this
     * collection should be ignored.
     *
     * @param string $principalUri
     * @param string $uid
     *
     * @return string|null
     */
    public function getCalendarObjectByUID($principalUri, $uid)
    {
        throw new NotImplemented('Getting calendar objects by UID is not yet supported');
    }

    /**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified calendar.
     *
     * This function should return an array, such as the following:
     *
     * [
     *   'syncToken' => 'The current synctoken',
     *   'added'   => [
     *      'new.txt',
     *   ],
     *   'modified'   => [
     *      'modified.txt',
     *   ],
     *   'deleted' => [
     *      'foo.php.bak',
     *      'old.txt'
     *   ]
     * ];
     *
     * The returned syncToken property should reflect the *current* syncToken
     * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
     * property this is needed here too, to ensure the operation is atomic.
     *
     * If the $syncToken argument is specified as null, this is an initial
     * sync, and all members should be reported.
     *
     * The modified property is an array of nodenames that have changed since
     * the last token.
     *
     * The deleted property is an array with nodenames, that have been deleted
     * from collection.
     *
     * The $syncLevel argument is basically the 'depth' of the report. If it's
     * 1, you only have to report changes that happened only directly in
     * immediate descendants. If it's 2, it should also include changes from
     * the nodes below the child collections. (grandchildren)
     *
     * The $limit argument allows a client to specify how many results should
     * be returned at most. If the limit is not specified, it should be treated
     * as infinite.
     *
     * If the limit (infinite or not) is higher than you're willing to return,
     * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
     *
     * If the syncToken is expired (due to data cleanup) or unknown, you must
     * return null.
     *
     * The limit is 'suggestive'. You are free to ignore it.
     *
     * @param mixed  $calendarUuid
     * @param string $syncToken
     * @param int    $syncLevel
     * @param int    $limit
     *
     * @return array|null
     */
    public function getChangesForCalendar($calendarUuid, $syncToken, $syncLevel, $limit = null)
    {
        return null;
    }

    /**
     * Adds a change record to the calendarchanges table.
     *
     * @param mixed  $calendarUuid
     * @param string $objectUri
     * @param int    $operation  1 = add, 2 = modify, 3 = delete
     */
    protected function addChange($calendarUuid, $objectUri, $operation)
    {
        throw new NotImplemented('Adding changes is not yet supported');
    }

    /**
     * Returns a list of subscriptions for a principal.
     *
     * Every subscription is an array with the following keys:
     *  * id, a unique id that will be used by other functions to modify the
     *    subscription. This can be the same as the uri or a database key.
     *  * uri. This is just the 'base uri' or 'filename' of the subscription.
     *  * principaluri. The owner of the subscription. Almost always the same as
     *    principalUri passed to this method.
     *  * source. Url to the actual feed
     *
     * Furthermore, all the subscription info must be returned too:
     *
     * 1. {DAV:}displayname
     * 2. {http://apple.com/ns/ical/}refreshrate
     * 3. {http://calendarserver.org/ns/}subscribed-strip-todos (omit if todos
     *    should not be stripped).
     * 4. {http://calendarserver.org/ns/}subscribed-strip-alarms (omit if alarms
     *    should not be stripped).
     * 5. {http://calendarserver.org/ns/}subscribed-strip-attachments (omit if
     *    attachments should not be stripped).
     * 7. {http://apple.com/ns/ical/}calendar-color
     * 8. {http://apple.com/ns/ical/}calendar-order
     * 9. {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
     *    (should just be an instance of
     *    Sabre\CalDAV\Property\SupportedCalendarComponentSet, with a bunch of
     *    default components).
     *
     * @param string $principalUri
     *
     * @return array
     */
    public function getSubscriptionsForUser($principalUri)
    {
        return [];
    }

    /**
     * Creates a new subscription for a principal.
     *
     * If the creation was a success, an id must be returned that can be used to reference
     * this subscription in other methods, such as updateSubscription.
     *
     * @param string $principalUri
     * @param string $uri
     *
     * @return mixed
     */
    public function createSubscription($principalUri, $uri, array $properties)
    {
        throw new NotImplemented('Creating subscriptions is not yet supported');
    }

    /**
     * Updates a subscription.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documentation for more info and examples.
     *
     * @param mixed $subscriptionId
     */
    public function updateSubscription($subscriptionId, PropPatch $propPatch)
    {
        throw new NotImplemented('Updating subscriptions is not yet supported');
    }

    /**
     * Deletes a subscription.
     *
     * @param mixed $subscriptionId
     */
    public function deleteSubscription($subscriptionId)
    {
        throw new NotImplemented('Deleting subscriptions is not yet supported');
    }

    /**
     * Returns a single scheduling object.
     *
     * The returned array should contain the following elements:
     *   * uri - A unique basename for the object. This will be used to
     *           construct a full uri.
     *   * calendardata - The iCalendar object
     *   * lastmodified - The last modification date. Can be an int for a unix
     *                    timestamp, or a PHP DateTime object.
     *   * etag - A unique token that must change if the object changed.
     *   * size - The size of the object, in bytes.
     *
     * @param string $principalUri
     * @param string $objectUri
     *
     * @return array|null
     */
    public function getSchedulingObject($principalUri, $objectUri)
    {
        return null;
    }

    /**
     * Returns all scheduling objects for the inbox collection.
     *
     * These objects should be returned as an array. Every item in the array
     * should follow the same structure as returned from getSchedulingObject.
     *
     * The main difference is that 'calendardata' is optional.
     *
     * @param string $principalUri
     *
     * @return array
     */
    public function getSchedulingObjects($principalUri)
    {
        return [];
    }

    /**
     * Deletes a scheduling object.
     *
     * @param string $principalUri
     * @param string $objectUri
     */
    public function deleteSchedulingObject($principalUri, $objectUri)
    {
        throw new NotImplemented('Deleting scheduling object is not yet supported');
    }

    /**
     * Creates a new scheduling object. This should land in a users' inbox.
     *
     * @param string          $principalUri
     * @param string          $objectUri
     * @param string|resource $objectData
     */
    public function createSchedulingObject($principalUri, $objectUri, $objectData)
    {
        throw new NotImplemented('Creating scheduling objects is not yet supported');
    }

    /**
     * Updates the list of shares.
     *
     * @param mixed                           $calendarUuid
     * @param \Sabre\DAV\Xml\Element\Sharee[] $sharees
     */
    public function updateInvites($calendarUuid, array $sharees)
    {
    }

    /**
     * Returns the list of people whom a calendar is shared with.
     *
     * Every item in the returned list must be a Sharee object with at
     * least the following properties set:
     *   $href
     *   $shareAccess
     *   $inviteStatus
     *
     * and optionally:
     *   $properties
     *
     * @param mixed $calendarUuid
     *
     * @return \Sabre\DAV\Xml\Element\Sharee[]
     */
    public function getInvites($calendarUuid)
    {
        return [];
    }

    /**
     * Publishes a calendar.
     *
     * @param mixed $calendarUuid
     * @param bool  $value
     */
    public function setPublishStatus($calendarUuid, $value)
    {
        throw new DAV\Exception\NotImplemented('Not implemented');
    }
}
