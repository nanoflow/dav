<?php

declare(strict_types=1);

use Admidio\Roles\Entity\Role;
use Sabre\DAV;
use Sabre\DAVACL\PrincipalBackend\AbstractBackend;
use Sabre\DAVACL\PrincipalBackend\CreatePrincipalSupport;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\MkCol;

use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\ValueObject\ListData;
use Admidio\Users\Entity\User;

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');
/**
 * PDO principal backend.
 *
 * This backend assumes all principals are in a single collection. The default collection
 * is 'principals/', but this can be overridden.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class AdmPrincipalBackend extends AbstractBackend implements CreatePrincipalSupport
{
    use AdmBackendFunctions;

    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
     *     field that's actually injected in a number of other properties. If
     *     you have an email address, use this property.
     *
     * @param string $prefixPath
     *
     * @return array
     */

    /**
     * A list of additional fields to support.
     *
     * @var array
     */

    public function getPrincipalsByPrefix($prefixPath)
    {
        global $gDb;

        $list = new ListConfiguration($gDb);
        $list->addColumn('usr_login_name');

        $listData = new ListData();
        $listData->setDataByConfiguration($list, []);

        $members = $listData->getData();

        $principals = [];
        foreach ($members as $member) {
            if (!$member['usr_login_name']) {
                continue;
            }
            $principals[] = $this->getPrincipalByPath($prefixPath . '/' . $member['usr_login_name']);
        }
        return $principals;
    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from
     * getPrincipalsByPrefix.
     *
     * @param string $path
     *
     * @return array
     */
    public function getPrincipalByPath($path)
    {
        global $gDb;

        $usrLoginName = explode(separator: '/', string: $path)[1]; // TODO review this

        $user = new User($gDb, userId: $this->getUserId($usrLoginName));

        $principal = [
            'id' => $user->getValue('usr_uuid'),
            'uri' => 'principals/' . $user->getValue('usr_login_name'),
            '{DAV:}displayname' => $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'),
            '{http://sabredav.org/ns}email-address' => $user->getValue('EMAIL'),
        ];

        return $principal;
    }

    /**
     * Updates one ore more webdav properties on a principal.
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
     * @param string $path
     */
    public function updatePrincipal($path, DAV\PropPatch $propPatch)
    {
        throw new NotImplemented('Updating principals is not yet supported');
    }

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
     *
     * By default, if multiple properties are submitted to this method, the
     * various properties should be combined with 'AND'. If $test is set to
     * 'anyof', it should be combined using 'OR'.
     *
     * This method should simply return an array with full principal uri's.
     *
     * If somebody attempted to search on a property the backend does not
     * support, you should simply return 0 results.
     *
     * You can also just return 0 results if you choose to not support
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param string $test
     *
     * @return array
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        throw new NotImplemented('Searching principals is not yet supported');
    }

    /**
     * Finds a principal by its URI.
     *
     * This method may receive any type of uri, but mailto: addresses will be
     * the most common.
     *
     * Implementation of this API is optional. It is currently used by the
     * CalDAV system to find principals based on their email addresses. If this
     * API is not implemented, some features may not work correctly.
     *
     * This method must return a relative principal path, or null, if the
     * principal was not found or you refuse to find it.
     *
     * @param string $uri
     * @param string $principalPrefix
     *
     * @return string|null
     */
    public function findByUri($uri, $principalPrefix)
    {
        throw new NotImplemented('Finding principals by uri is not yet supported');
    }

    /**
     * Returns the list of members for a group-principal.
     *
     * @param string $principal
     *
     * @return array
     */
    public function getGroupMemberSet($principal)
    {
        return [];
    }

    /**
     * Returns the list of groups a principal is a member of.
     *
     * @param string $principal
     *
     * @return array
     */
    public function getGroupMembership($principal)
    {
        // global $gDb;

        // $user = new User($gDb, userId: $this->getUserId($principal));
        // $roleIds = $user->getRoleMemberships();
        $roles = [];
        // foreach ($roleIds as $roleId) {
        //     $role = new Role($gDb, $roleId);
        //     $roles[] = $role->readableName();
        // }
        return $roles;


    }

    /**
     * Updates the list of group members for a group principal.
     *
     * The principals should be passed as a list of uri's.
     *
     * @param string $principal
     */
    public function setGroupMemberSet($principal, array $members)
    {
        throw new NotImplemented('Updating group members is not yet supported');
    }

    /**
     * Creates a new principal.
     *
     * This method receives a full path for the new principal. The mkCol object
     * contains any additional webdav properties specified during the creation
     * of the principal.
     *
     * @param string $path
     */
    public function createPrincipal($path, MkCol $mkCol)
    {
        throw new NotImplemented('Creating principals is not yet supported');
    }
}
