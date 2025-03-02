<?php

/**
 * Provides common Admidio functions.
 */

$rootPath = dirname(__DIR__, 2);
require_once($rootPath . '/adm_program/system/common.php');

trait AdmBackendFunctions
{
    protected function getUserId($username)
    {
        global $gDb;
        $sql = 'SELECT usr_id
        FROM ' . TBL_USERS . '
        WHERE UPPER(usr_login_name) = UPPER(?)';
        $userStatement = $gDb->queryPrepared($sql, array($username));
        return $userStatement->fetchColumn();
    }
}
