<?php

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\PropPatch;

use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\ValueObject\ListData;
use Admidio\Users\Entity\User;
use Admidio\Roles\Entity\Role;


/**
 * PDO CardDAV backend.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class AdmCarddavBackend extends AbstractBackend implements SyncSupport
{
    use AdmBackendFunctions;

    /**
     * Returns the list of addressbooks for a specific user.
     *
     * @param string $principalUri
     *
     * @return array
     */
    public function getAddressBooksForUser($principalUri)
    {
        global $gDb, $gProfileFields;

        $user = new User($gDb, $gProfileFields, $this->getUserId($principalUri));
        $user->checkRolesRight();
        $visibleRoleIds = $user->getRolesViewMemberships();

        $addressBooks = [];

        if ($visibleRoleIds) {
            foreach ($visibleRoleIds as $roleId) {
                $role = new Role($gDb, $roleId);
                $isEventRole = $role->getValue('cat_name_intern') === 'EVENTS';
                if ($isEventRole) {
                    continue;
                }
                $entry = [
                    'id' => $role->getValue('rol_id'),
                    'uri' => $role->getValue('rol_id'),
                    'principaluri' => $principalUri,
                    '{DAV:}displayname' => $role->getValue('rol_name'),
                    '{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => $role->getValue('rol_name'),
                    // '{http://calendarserver.org/ns/}getctag' => 'ctag',
                    // '{http://sabredav.org/ns}sync-token' => '0',
                ];
                $addressBooks[] = $entry;
            }
        }

        return $addressBooks;
    }

    /**
     * Updates properties for an address book.
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
     * @param string $addressBookId
     */
    public function updateAddressBook($addressBookId, PropPatch $propPatch)
    {
        throw new NotImplemented('Updating addressbooks is not yet supported');
    }

    /**
     * Creates a new address book.
     *
     * @param string $principalUri
     * @param string $url          just the 'basename' of the url
     *
     * @return int Last insert id
     */
    public function createAddressBook($principalUri, $url, array $properties)
    {
        throw new NotImplemented('Creating addressbooks is not yet supported');
    }

    /**
     * Deletes an entire addressbook and all its contents.
     *
     * @param int $addressBookId
     */
    public function deleteAddressBook($addressBookId)
    {
        throw new NotImplemented('Deleting addressbooks is not yet supported');
    }

    /**
     * Returns all cards for a specific addressbook id.
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp
     *
     * It's recommended to also return the following properties:
     *   * etag - A unique etag. This must change every time the card changes.
     *   * size - The size of the card in bytes.
     *
     * If these last two properties are provided, less time will be spent
     * calculating them. If they are specified, you can also omit carddata.
     * This may speed up certain requests, especially with large cards.
     *
     * @param mixed $addressbookId
     *
     * @return array
     */
    public function getCards($addressbookId)
    {
        global $gDb;

        $list = new ListConfiguration($gDb);
        $list->addColumn('mem_usr_id');

        $listData = new ListData();
        $listData->setDataByConfiguration($list, ['showRolesMembers' => [$addressbookId], 'showUserUUID' => true]);

        $members = $listData->getData();

        $result = [];

        foreach ($members as $member) {
            $usrUUID = $member['usr_uuid'];

            $result[] = $this->getCard($addressbookId, $usrUUID);
        }
        return $result;
    }

    /**
     * Returns a specific card.
     *
     * The same set of properties must be returned as with getCards. The only
     * exception is that 'carddata' is absolutely required.
     *
     * If the card does not exist, you must return false.
     *
     * @param mixed  $addressBookId
     * @param string $cardUri
     *
     * @return array | bool
     */
    public function getCard($addressBookId, $usrUUID)
    {
        global $gDb, $gProfileFields;

        $user = new User($gDb, $gProfileFields);
        $userExists = $user->readDataByUuid($usrUUID);

        if (!$userExists) {
            return false;
        }

        $card = [
            'carddata' => $user->getVCard(),
            'uri' => $user->getValue('usr_uuid'),
            'lastmodified' => new DateTime($user->getValue('usr_timestamp_change', 'c')),
        ];
        return $card;
    }

    /**
     * Returns a list of cards.
     *
     * This method should work identical to getCard, but instead return all the
     * cards in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param mixed $addressBookId
     *
     * @return array
     */
    public function getMultipleCards($addressBookId, array $uris)
    {
        $cards = [];
        foreach ($uris as $uri) {
            $cards[] = $this->getCard($addressBookId, $uri);
        }
        return $cards;
    }

    /**
     * Creates a new card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag is for the
     * newly created resource, and must be enclosed with double quotes (that
     * is, the string itself must contain the double quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed  $addressBookId
     * @param string $cardUri
     * @param string $cardData
     *
     * @return string|null
     */
    public function createCard($addressBookId, $cardUri, $cardData)
    {
        throw new NotImplemented('Creating cards is not yet supported');
    }

    /**
     * Updates a card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag should
     * match that of the updated resource, and must be enclosed with double
     * quotes (that is: the string itself must contain the actual quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed  $addressBookId
     * @param string $cardUri
     * @param string $cardData
     *
     * @return string|null
     */
    public function updateCard($addressBookId, $cardUri, $cardData)
    {
        throw new NotImplemented('Updating cards is not yet supported');
    }

    /**
     * Deletes a card.
     *
     * @param mixed  $addressBookId
     * @param string $cardUri
     *
     * @return bool
     */
    public function deleteCard($addressBookId, $cardUri)
    {
        throw new NotImplemented('Deleting cards is not yet supported');
    }

    /**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified address book.
     *
     * This function should return an array, such as the following:
     *
     * [
     *   'syncToken' => 'The current synctoken',
     *   'added'   => [
     *      'new.txt',
     *   ],
     *   'modified'   => [
     *      'updated.txt',
     *   ],
     *   'deleted' => [
     *      'foo.php.bak',
     *      'old.txt'
     *   ]
     * ];
     *
     * The returned syncToken property should reflect the *current* syncToken
     * of the addressbook, as reported in the {http://sabredav.org/ns}sync-token
     * property. This is needed here too, to ensure the operation is atomic.
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
     * @param string $addressBookId
     * @param string $syncToken
     * @param int    $syncLevel
     * @param int    $limit
     *
     * @return array|null
     */
    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null)
    {
        // // Current synctoken
        // $stmt = $this->pdo->prepare('SELECT synctoken FROM ' . $this->addressBooksTableName . ' WHERE id = ?');
        // $stmt->execute([$addressBookId]);
        // $currentToken = $stmt->fetchColumn(0);

        // if (is_null($currentToken)) {
        //     return null;
        // }

        // $result = [
        //     'syncToken' => $currentToken,
        //     'added' => [],
        //     'modified' => [],
        //     'deleted' => [],
        // ];

        // if ($syncToken) {
        //     $query = 'SELECT uri, operation FROM ' . $this->addressBookChangesTableName . ' WHERE synctoken >= ? AND synctoken < ? AND addressbookid = ? ORDER BY synctoken';
        //     if ($limit > 0) {
        //         $query .= ' LIMIT ' . (int) $limit;
        //     }

        //     // Fetching all changes
        //     $stmt = $this->pdo->prepare($query);
        //     $stmt->execute([$syncToken, $currentToken, $addressBookId]);

        //     $changes = [];

        //     // This loop ensures that any duplicates are overwritten, only the
        //     // last change on a node is relevant.
        //     while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        //         $changes[$row['uri']] = $row['operation'];
        //     }

        //     foreach ($changes as $uri => $operation) {
        //         switch ($operation) {
        //             case 1:
        //                 $result['added'][] = $uri;
        //                 break;
        //             case 2:
        //                 $result['modified'][] = $uri;
        //                 break;
        //             case 3:
        //                 $result['deleted'][] = $uri;
        //                 break;
        //         }
        //     }
        // } else {
        //     // No synctoken supplied, this is the initial sync.
        //     $query = 'SELECT uri FROM ' . $this->cardsTableName . ' WHERE addressbookid = ?';
        //     $stmt = $this->pdo->prepare($query);
        //     $stmt->execute([$addressBookId]);

        //     $result['added'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        // }

        // return $result;
        return [];
    }
}
