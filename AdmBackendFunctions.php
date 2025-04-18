<?php

use Admidio\Events\ValueObject\Participants;
use Admidio\Roles\Entity\Role;
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
        $role = new Role($gDb);
        $role->readDataByUuid($roleUuid);
        return $role->getValue('rol_id');
    }

    protected function mapParticipationStatus($admParticipationStatus): ParticipationStatus
    {
        switch ($admParticipationStatus) {
            case Participants::PARTICIPATION_YES:
                return ParticipationStatus::ACCEPTED();
            case Participants::PARTICIPATION_NO:
                return ParticipationStatus::DECLINED();
            case Participants::PARTICIPATION_MAYBE:
            case Participants::PARTICIPATION_UNKNOWN:
                return ParticipationStatus::TENTATIVE();
            default:
                throw new ErrorException("could not map " . $admParticipationStatus . " to iCal status");
        }
    }

    protected function getParticipantChangeDate($roleUuid): DateTime|null
    {
        global $gDb;
        $sql = 'SELECT log_timestamp_create
        FROM ' . TBL_LOG . '
        WHERE log_related_id = ? 
        ORDER BY log_timestamp_create desc
        LIMIT 1';
        $userStatement = $gDb->queryPrepared($sql, array($roleUuid));
        $changeDate = $userStatement->fetchColumn();
        if ($changeDate) {
            return new DateTime($changeDate);
        }
        return null;
    }


    // protected function getLastMembershipChangeDate(int $roleId): DateTime
    // {
    //     global $gDb;
    //     $sql = 'SELECT MAX(GREATEST(mem_timestamp_create, COALESCE(mem_timestamp_change, mem_timestamp_create))) AS latest_change
    //     FROM ' . TBL_MEMBERS . '
    //     WHERE mem_rol_id = ?';
    //     $userStatement = $gDb->queryPrepared($sql, array($roleId));
    //     return new DateTime($userStatement->fetchColumn());
    // }

    // protected function getLastCardChangeDate(int $roleId): DateTime
    // {
    //     global $gDb;
    //     $sql = 'SELECT MAX(usr_timestamp_change) AS latest_change
    //     FROM ' . TBL_USERS . '
    //     WHERE usr_id in (
    //         SELECT mem_usr_id FROM ' . TBL_MEMBERS . '
    //         WHERE mem_rol_id = ?
    //     )';
    //     $userStatement = $gDb->queryPrepared($sql, array($roleId));
    //     return new DateTime($userStatement->fetchColumn());
    // }

    // protected function getModifiedUsersInRoleSince(int $roleId, int $timestamp): array
    // {
    //     global $gDb;
    //     $date = (new DateTime())->setTimestamp($timestamp);
    //     $sql = 'SELECT usr_uuid
    //     FROM ' . TBL_USERS . '
    //     WHERE usr_id in (
    //         SELECT mem_usr_id FROM ' . TBL_MEMBERS . '
    //         WHERE mem_rol_id = ?
    //     )
    //     AND UNIX_TIMESTAMP(usr_timestamp_change) > ?';
    //     $userStatement = $gDb->queryPrepared($sql, array($roleId, $timestamp));
    //     $result = $userStatement->fetchAll();
    //     return array_column($result, 'usr_uuid');
    // }

    // protected function getAddedUsersToRoleSince(int $roleId, int $timestamp): array
    // {
    //     global $gDb;
    //     $date = (new DateTime())->setTimestamp($timestamp);
    //     $sql = 'SELECT usr_uuid
    //     FROM ' . TBL_USERS . '
    //     WHERE usr_id in (
    //         SELECT mem_usr_id FROM ' . TBL_MEMBERS . '
    //         WHERE mem_rol_id = ?
    //         AND UNIX_TIMESTAMP(mem_timestamp_create) > ?
    //         AND CURDATE() BETWEEN mem_begin AND mem_end
    //     )';
    //     $userStatement = $gDb->queryPrepared($sql, array($roleId, $timestamp));
    //     $result = $userStatement->fetchAll();
    //     return array_column($result, 'usr_uuid');
    // }

    // protected function getDeletedUsersFromRoleSince(int $roleId, int $timestamp): array
    // {
    //     global $gDb;
    //     $sql = 'SELECT usr_uuid
    //     FROM ' . TBL_USERS . '
    //     WHERE usr_id in (
    //         SELECT mem_usr_id FROM ' . TBL_MEMBERS . '
    //         WHERE mem_rol_id = ?
    //         AND UNIX_TIMESTAMP(mem_timestamp_change) > ?
    //         AND mem_end <= CURDATE()
    //     )';
    //     $userStatement = $gDb->queryPrepared($sql, array($roleId, $timestamp));
    //     $result = $userStatement->fetchAll();

    //     return array_column($result, 'usr_uuid');
    // }
}
