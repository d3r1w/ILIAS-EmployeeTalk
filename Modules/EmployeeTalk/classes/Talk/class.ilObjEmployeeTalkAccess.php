<?php
declare(strict_types=1);

use ILIAS\EmployeeTalk\UI\ControlFlowCommand;
use ILIAS\DI\Container;
use ILIAS\Modules\EmployeeTalk\Talk\EmployeeTalkPositionAccessLevel;
use ILIAS\Modules\EmployeeTalk\TalkSeries\Repository\IliasDBEmployeeTalkSeriesRepository;
use ILIAS\MyStaff\ilMyStaffAccess;
use OrgUnit\User\ilOrgUnitUser;

final class ilObjEmployeeTalkAccess extends ilObjectAccess
{
    /**
     * @var self $instance
     */
    private static $instance = null;

    /**
     * @var ilOrgUnitUserAssignmentQueries $ua
     */
    private $ua;
    /**
     * @var ilOrgUnitGlobalSettings $set
     */
    private $set;
    /**
     * @var IlOrgUnitPositionAccess $orgUnitAccess
     */
    private $orgUnitAccess;
    /**
     * @var Container $container
     */
    private $container;
    /**
     * @var ilOrgUnitObjectTypePositionSetting $talkPositionSettings
     */
    private $talkPositionSettings;
    /**
     * @var IliasDBEmployeeTalkSeriesRepository $seriesSettingsRepository
     */
    private $seriesSettingsRepository;

    public static function getInstance(): self {
        if (is_null(self::$instance)) {
            self::$instance = new ilObjEmployeeTalkAccess();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->container = $GLOBALS['DIC'];

        $this->set = ilOrgUnitGlobalSettings::getInstance();
        $this->ua = ilOrgUnitUserAssignmentQueries::getInstance();
        $this->orgUnitAccess = new ilOrgUnitPositionAccess();
        $this->talkPositionSettings = $this->set->getObjectPositionSettingsByType(ilObjEmployeeTalk::TYPE);
        $this->seriesSettingsRepository = new IliasDBEmployeeTalkSeriesRepository($this->container->user(), $this->container->database());
    }

    /**
     * get commands
     *
     * this method returns an array of all possible commands/permission combinations
     *
     * example:
     * $commands = array
     *    (
     *        array('permission' => 'read', 'cmd' => 'view', 'lang_var' => 'show'),
     *        array('permission' => 'write', 'cmd' => 'edit', 'lang_var' => 'edit'),
     *    );
     */
    public static function _getCommands() : array
    {
        $commands = [
            [
                'permission' => 'read',
                'cmd' => ControlFlowCommand::DEFAULT,
                'lang_var' => 'show',
                'default' => true,
            ]
        ];

        return $commands;
    }

    public static function _isOffline($a_obj_id)
    {
        return false;
    }

    /**
     * @param string $a_target check whether goto script will succeed
     *
     * @return bool
     */
    public static function _checkGoto($a_target) : bool
    {
        global $DIC;

        $t_arr = explode('_', $a_target);
        if ($t_arr[0] !== 'etal' || ((int) $t_arr[1]) <= 0) {
            return false;
        }
        if ($DIC->access()->checkAccess('read', '', $t_arr[1])) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the user is allowed to create a new talks series.
     * If no user is given only the position right is checked, which can be used
     * to display create or new buttons based on the general position rights of the user.
     *
     * If the user is given, only positions with an authority over the given user are used to
     * check the position rights.
     *
     * @param ilObjUser|null $talkParticipant   The talk participant which should get invited into the new talk.
     * @return bool                             True if the user has creation rights otherwise false.
     */
    public function canCreate(?ilObjUser $talkParticipant = null): bool {
        try {
            $currentUserId = $this->getCurrentUsersId();

            // Root has always full access
            if ($currentUserId === 6) {
                return true;
            }

            // Talks are never editable if the position rights are not active, because the talks don't use RBAC
            if (!$this->talkPositionSettings->isActive()) {
                return false;
            }

            $positions = $this->ua->getPositionsOfUserId($currentUserId);

            // If we don't have a user just check if the current user has the right in any position to create a new talk
            if ($talkParticipant === null) {
                foreach ($positions as $position) {
                    // Check if the position has any relevant position rights
                    $permissionSet = ilOrgUnitPermissionQueries::getTemplateSetForContextName(ilObjEmployeeTalk::TYPE, $position->getId());
                    $isAbleToExecuteOperation = array_reduce($permissionSet->getOperations(), function (bool $prev, ilOrgUnitOperation $it) {
                        return $prev || $it->getOperationString() === EmployeeTalkPositionAccessLevel::CREATE;
                    }, false);

                    // If the position has no rights check the next one
                    if (!$isAbleToExecuteOperation) {
                        continue;
                    }

                    return true;
                }

                // The current user was not in a position with create etal position rights
                return false;
            }

            // Validate authority and position rights over the given participant
            return $this->hasAuthorityAndOperationPermissionOverUser($talkParticipant, EmployeeTalkPositionAccessLevel::CREATE);
        }
        catch (ilException $ex) {
            return false;
        }
    }

    public function canRead(int $refId): bool {
        return $this->isPermittedToExecuteOperation($refId, EmployeeTalkPositionAccessLevel::VIEW);
    }

    public function canEditTalkLockStatus(int $refId): bool {
        $currentUserId = $this->getCurrentUsersId();

        // Root has always full access
        if ($currentUserId === 6) {
            return true;
        }

        $talk = new ilObjEmployeeTalk($refId);
        return intval($talk->getOwner()) === $currentUserId;
    }

    /**
     * @param int $refId
     * @return bool
     */
    public function canEdit(int $refId): bool {
        return $this->isPermittedToExecuteOperation($refId, EmployeeTalkPositionAccessLevel::EDIT);
    }

    /**
     * @param int $refId
     * @return bool
     */
    public function canDelete(int $refId): bool {
        return $this->isPermittedToExecuteOperation($refId, EmployeeTalkPositionAccessLevel::CREATE);
    }

    private function isPermittedToExecuteOperation(int $refId, string $operation): bool {
        $currentUserId = $this->getCurrentUsersId();

        // Root has always full access
        if ($currentUserId === 6) {
            return true;
        }

        // Talks are never editable if the position rights are not active, because the talks don't use RBAC
        if (!$this->talkPositionSettings->isActive()) {
            return false;
        }

        $talk = new ilObjEmployeeTalk($refId);
        $series = $talk->getParent();
        $hasAuthority = $this->hasAuthorityAndOperationPermissionOverUser(new ilObjUser($talk->getData()->getEmployee()), $operation);
        $data = $talk->getData();
        $seriesSettings = $this->seriesSettingsRepository->readEmployeeTalkSerieSettings($series->getId());
        $canExecuteOperation = $this->orgUnitAccess->checkPositionAccess($operation, $refId);
        $isOwner = intval($talk->getOwner()) === $currentUserId;

        if ($isOwner) {
            return true;
        }

        // The Employee can never edit their own talks
        if ($currentUserId === $data->getEmployee() && $operation !== EmployeeTalkPositionAccessLevel::VIEW) {
            return false;
        }

        //Only owner can edit talks with enabled write lock
        if ($seriesSettings->isLockedEditing() && $operation === EmployeeTalkPositionAccessLevel::EDIT) {
            return false;
        }

        // Has no authority over the employee
        if (!$hasAuthority) {
            return false;
        }

        // Has Authority and is permitted to execute the given permission
        if ($canExecuteOperation) {
            return true;
        }

        // Has authority but no permission
        return false;
    }

    /**
     * @param int $ref_id
     *
     * @return bool
     */
    public function isTalkReadonlyByCurrentUser(int $ref_id): bool {
        return !$this->canEdit($ref_id);
    }

    /**
     * @return int
     */
    private function getCurrentUsersId(): int
    {
        return $this->container->user()->getId();
    }

    private function hasAuthorityAndOperationPermissionOverUser(ilObjUser $user, string $operation): bool {
        $myStaffAccess = ilMyStaffAccess::getInstance();
        $currentUserId = $this->getCurrentUsersId();
        $userId = $user->getId();

        /**
         * @var Array<int, Array<string>> $managedOrgUnitUsersOfUserByPosition
         */
        $managedOrgUnitUsersOfUserByPosition = $myStaffAccess->getUsersForUserPerPosition($currentUserId);

        foreach ($managedOrgUnitUsersOfUserByPosition as $position => $managedOrgUnitUserByPosition) {
            // Check if the position has any relevant position rights
            $permissionSet = ilOrgUnitPermissionQueries::getTemplateSetForContextName(ilObjEmployeeTalk::TYPE, intval($position));
            $isAbleToExecuteOperation = array_reduce($permissionSet->getOperations(), function (bool $prev, ilOrgUnitOperation $it) use ($operation) {
                return $prev || $it->getOperationString() === $operation;
            }, false);

            if (!$isAbleToExecuteOperation) {
                continue;
            }

            foreach ($managedOrgUnitUserByPosition as $managedOrgUnitUser)
                if (intval($managedOrgUnitUser) === $userId) {
                    return true;
                }
        }

        return false;
    }

}