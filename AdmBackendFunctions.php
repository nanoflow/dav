<?php

use Eluceo\iCal\Domain\Enum\ParticipationStatus;

/**
 * Provides common Admidio functions.
 */

$rootPath = dirname(__DIR__, 2);
require_once($rootPath . '/adm_program/system/common.php');

trait AdmBackendFunctions
{
    protected function getUserId(string $username): int
    {
        global $gDb;
        $sql = 'SELECT usr_id
        FROM ' . TBL_USERS . '
        WHERE UPPER(usr_login_name) = UPPER(?)';
        $userStatement = $gDb->queryPrepared($sql, array($username));
        return $userStatement->fetchColumn();
    }

    protected function getRoleId(string $roleUuid): int
    {
        global $gDb;
        $role = new TableRoles($gDb);
        $role->readDataByUuid($roleUuid);
        return $role->getValue('rol_id');
    }

    protected function mapParticipationStatus($admParticipationStatus): ParticipationStatus
    {
        switch ($admParticipationStatus) {
            case ModuleEvents::MEMBER_APPROVAL_STATE_ATTEND:
                return ParticipationStatus::ACCEPTED();
            case ModuleEvents::MEMBER_APPROVAL_STATE_REFUSED:
                return ParticipationStatus::DECLINED();
            case ModuleEvents::MEMBER_APPROVAL_STATE_INVITED:
            case ModuleEvents::MEMBER_APPROVAL_STATE_TENTATIVE:
                return ParticipationStatus::TENTATIVE();
            default:
                throw new ErrorException("could not map " . $admParticipationStatus . " to iCal status");
        }
    }

    protected function getParticipantChangeDate($roleUuid): DateTime|null
    {
        global $gDb;

        $role = new TableRoles($gDb);
        $role->readDataByUuid($roleUuid);

        global $gDb;
        $sql = 'SELECT COALESCE(mem_timestamp_change,mem_timestamp_create ) AS latest_change
        FROM ' . TBL_MEMBERS . '
        WHERE mem_rol_id = ?
        ORDER BY latest_change desc
        LIMIT 1';
        $userStatement = $gDb->queryPrepared($sql, array($role->getValue('rol_id')));
        $changeDate = $userStatement->fetchColumn();
        if ($changeDate) {
            return new DateTime($changeDate);
        }
        return null;
    }
}
