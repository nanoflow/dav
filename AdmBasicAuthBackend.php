<?php

include 'AdmBackendFunctions.php';

/**
 * This is an Admidio authentication backend for Sabre DAV.
 */

$rootPath = dirname(__DIR__, 2);

class AdmBasicAuthBackend extends Sabre\DAV\Auth\Backend\AbstractBasic
{
    use AdmBackendFunctions;

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
        global $gDb, $gCurrentUser, $gCurrentUserId, $gCurrentUserUUID;
        $user = new User($gDb, null, $this->getUserId($username));

        if ($user->checkLogin($password)) {
            $gCurrentUser = $user;
            $gCurrentUserId = $gCurrentUser->getValue('usr_id');
            $gCurrentUserUUID = $gCurrentUser->getValue('usr_uuid');
            return true;
        }
        return false;
    }
}
