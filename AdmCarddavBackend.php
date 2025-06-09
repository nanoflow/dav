<?php

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\PropPatch;

use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\ValueObject\ListData;
use Admidio\Users\Entity\User;
use Admidio\Roles\Entity\Role;


/**
 * Admidio CardDAV backend.
 */
class AdmCarddavBackend extends AbstractBackend
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
        global $gDb;

        $usrLoginName = str_replace('principals/', '', $principalUri);
        $user = new User($gDb, userId: $this->getUserId($usrLoginName));
        $user->checkRolesRight();
        $visibleRoleUuids = $user->getRolesViewMemberships();

        $addressBooks = [];

        if ($visibleRoleUuids) {
            foreach ($visibleRoleUuids as $roleUuid) {
                $role = new Role($gDb);
                $role->readDataByUuid($roleUuid);
                $isEventRole = $role->getValue('cat_name_intern') === 'EVENTS';
                if ($isEventRole) {
                    continue;
                }
                $roleUuid = $role->getValue('rol_uuid');

                $addressBook = [
                    'id' => $roleUuid,
                    'uri' => $roleUuid,
                    'principaluri' => $principalUri,
                    '{DAV:}displayname' => $role->getValue('rol_name'),
                    '{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => $role->getValue('rol_description'),
                ];
                $addressBooks[] = $addressBook;
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
     * @param string $addressBookUuid
     */
    public function updateAddressBook($addressBookUuid, PropPatch $propPatch)
    {
        throw new NotImplemented('Updating addressbooks is not supported');
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
        throw new NotImplemented('Creating addressbooks is not supported');
    }

    /**
     * Deletes an entire addressbook and all its contents.
     *
     * @param int $addressBookUuid
     */
    public function deleteAddressBook($addressBookUuid)
    {
        throw new NotImplemented('Deleting addressbooks is not supported');
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
     * @param mixed $addressbookUuid
     *
     * @return array
     */
    public function getCards($addressbookUuid)
    {
        global $gDb;

        $list = new ListConfiguration($gDb);
        $list->addColumn('mem_usr_id');

        $listData = new ListData();
        $listData->setDataByConfiguration($list, ['showRolesMembers' => [$addressbookUuid], 'showUserUUID' => true]);

        $members = $listData->getData();

        $result = [];

        foreach ($members as $member) {
            $usrUuid = $member['usr_uuid'];
            $card = $this->getCard($addressbookUuid, $usrUuid);
            if ($card) {
                $result[] = $card;
            }
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
     * @param mixed  $addressBookUuid
     * @param string $cardUri
     *
     * @return array | bool
     */
    public function getCard($addressBookUuid, $cardUri)
    {
        global $gDb;

        $usrUUID = str_replace('.vcf', '', $cardUri);

        $user = new User($gDb);
        $userExists = $user->readDataByUuid($usrUUID);
        $user->getRoleMemberships();
        $userHasRole = $user->isMemberOfRole($this->getRoleId($addressBookUuid));

        if (!$userExists or !$userHasRole) {
            return false;
        }

        $carddata = $user->getVCard();
        $lastModified = $user->getValue('usr_timestamp_change') ?: $user->getValue('usr_timestamp_create');

        $card = [
            'carddata' => $carddata,
            'uri' => $user->getValue('usr_uuid') . '.vcf',
            'lastmodified' => strtotime($lastModified)
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
     * @param mixed $addressBookUuid
     *
     * @return array
     */
    public function getMultipleCards($addressBookUuid, array $uris)
    {
        $cards = [];
        foreach ($uris as $uri) {
            $cards[] = $this->getCard($addressBookUuid, $uri);
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
     * @param mixed  $addressBookUuid
     * @param string $cardUri
     * @param string $cardData
     *
     * @return string|null
     */
    public function createCard($addressBookUuid, $cardUri, $cardData)
    {
        throw new NotImplemented('Creating cards is not supported');
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
     * @param mixed  $addressBookUuid
     * @param string $cardUri
     * @param string $cardData
     *
     * @return string|null
     */
    public function updateCard($addressBookUuid, $cardUri, $cardData)
    {
        throw new NotImplemented('Updating cards is not yet supported');
    }

    /**
     * Deletes a card.
     *
     * @param mixed  $addressBookUuid
     * @param string $cardUri
     *
     * @return bool
     */
    public function deleteCard($addressBookUuid, $cardUri)
    {
        throw new NotImplemented('Deleting cards is not supported');
    }
}