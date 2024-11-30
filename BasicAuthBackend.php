<?php

/**
 * This is an authentication backend that uses a database to manage passwords.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */

$rootPath = dirname(__DIR__, 2);
require_once($rootPath . '/adm_program/system/common.php');

class BasicAuthBackend extends Sabre\DAV\Auth\Backend\AbstractBasic
{
    /**
     * Validates a username and password.
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function validateUserPass($username, $password)
    {
        global $gDb, $gProfileFields;
        $user = new User($gDb, $gProfileFields, $this->getUserId($username));
        if ($user->checkLogin($password)) {
            return true;
        }
        return false;
    }

    private function getUserId($username)
    {
        global $gDb;
        $sql = 'SELECT usr_id
        FROM ' . TBL_USERS . '
        WHERE UPPER(usr_login_name) = UPPER(?)';
        $userStatement = $gDb->queryPrepared($sql, array($username));
        return $userStatement->fetchColumn();
    }
}
