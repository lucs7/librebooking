<?php

interface IRoleService
{
    /**
     * @abstract
     *
     * @return bool
     */
    public function IsApplicationAdministrator(User $user);

    /**
     * @abstract
     *
     * @return bool
     */
    public function IsResourceAdministrator(User $user);

    /**
     * @abstract
     *
     * @return bool
     */
    public function IsGroupAdministrator(User $user);

    /**
     * @abstract
     *
     * @return bool
     */
    public function IsScheduleAdministrator(User $user);

    /**
     * @param int $otherUserId
     *
     * @return bool
     */
    public function IsAdminFor(UserSession $userSession, $otherUserId);
}

interface IAuthorizationService extends IRoleService
{
    /**
     * @abstract
     *
     * @param UserSession $reserver user who is requesting access to perform action
     *
     * @return bool
     */
    public function CanReserveForOthers(UserSession $reserver);

    /**
     * @abstract
     *
     * @param UserSession $reserver     user who is requesting access to perform action
     * @param int         $reserveForId user to reserve for
     *
     * @return bool
     */
    public function CanReserveFor(UserSession $reserver, $reserveForId);

    /**
     * @abstract
     *
     * @param UserSession $approver     user who is requesting access to perform action
     * @param int         $approveForId user to approve for
     *
     * @return bool
     */
    public function CanApproveFor(UserSession $approver, $approveForId);

    /**
     * @return bool
     */
    public function CanEditForResource(UserSession $user, IResource $resource);

    /**
     * @return bool
     */
    public function CanApproveForResource(UserSession $user, IResource $resource);
}

class AuthorizationService implements IAuthorizationService
{
    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(IUserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param UserSession $reserver user who is requesting access to perform action
     *
     * @return bool
     */
    public function CanReserveForOthers(UserSession $reserver)
    {
        if ($reserver->IsAdmin) {
            return true;
        }

        $user = $this->userRepository->LoadById($reserver->UserId);

        return $user->IsGroupAdmin();
    }

    /**
     * @param UserSession $reserver     user who is requesting access to perform action
     * @param int         $reserveForId user to reserve for
     *
     * @return bool
     */
    public function CanReserveFor(UserSession $reserver, $reserveForId)
    {
        if ($reserveForId == $reserver->UserId) {
            return true;
        }

        return $this->IsAdminFor($reserver, $reserveForId);
    }

    /**
     * @param UserSession $approver     user who is requesting access to perform action
     * @param int         $approveForId user to approve for
     *
     * @return bool
     */
    public function CanApproveFor(UserSession $approver, $approveForId)
    {
        return $this->IsAdminFor($approver, $approveForId);
    }

    /**
     * @return bool
     */
    public function IsApplicationAdministrator(User $user)
    {
        if (Configuration::Instance()->IsAdminEmail($user->EmailAddress())) {
            return true;
        }

        return $user->IsInRole(RoleLevel::APPLICATION_ADMIN);
    }

    /**
     * @return bool
     */
    public function IsResourceAdministrator(User $user)
    {
        return $user->IsInRole(RoleLevel::RESOURCE_ADMIN);
    }

    /**
     * @return bool
     */
    public function IsGroupAdministrator(User $user)
    {
        return $user->IsInRole(RoleLevel::GROUP_ADMIN);
    }

    /**
     * @return bool
     */
    public function IsScheduleAdministrator(User $user)
    {
        return $user->IsInRole(RoleLevel::SCHEDULE_ADMIN);
    }

    /**
     * @param int $otherUserId
     *
     * @return bool
     */
    public function IsAdminFor(UserSession $userSession, $otherUserId)
    {
        if ($userSession->IsAdmin) {
            return true;
        }

        if (!$userSession->IsGroupAdmin) {
            // dont even bother checking if the user isnt a group admin
            return false;
        }

        $user1 = $this->userRepository->LoadById($userSession->UserId);
        $user2 = $this->userRepository->LoadById($otherUserId);

        return $user1->IsAdminFor($user2);
    }

    /**
     * @return bool
     */
    public function CanEditForResource(UserSession $userSession, IResource $resource)
    {
        if ($userSession->IsAdmin) {
            return true;
        }

        if (!$userSession->IsResourceAdmin && !$userSession->IsScheduleAdmin) {
            return false;
        }

        $user = $this->userRepository->LoadById($userSession->UserId);

        return $user->IsResourceAdminFor($resource);
    }

    /**
     * @return bool
     */
    public function CanApproveForResource(UserSession $userSession, IResource $resource)
    {
        if ($userSession->IsAdmin) {
            return true;
        }

        if (!$userSession->IsResourceAdmin) {
            return false;
        }

        $user = $this->userRepository->LoadById($userSession->UserId);

        return $user->IsResourceAdminFor($resource);
    }
}

class GuestAuthorizationService implements IAuthorizationService
{
    public function IsApplicationAdministrator(User $user)
    {
        return false;
    }

    public function IsResourceAdministrator(User $user)
    {
        return false;
    }

    public function IsGroupAdministrator(User $user)
    {
        return false;
    }

    public function IsScheduleAdministrator(User $user)
    {
        return false;
    }

    public function IsAdminFor(UserSession $userSession, $otherUserId)
    {
        return false;
    }

    public function CanReserveForOthers(UserSession $reserver)
    {
        return false;
    }

    public function CanReserveFor(UserSession $reserver, $reserveForId)
    {
        return false;
    }

    public function CanApproveFor(UserSession $approver, $approveForId)
    {
        return false;
    }

    public function CanEditForResource(UserSession $user, IResource $resource)
    {
        return false;
    }

    public function CanApproveForResource(UserSession $user, IResource $resource)
    {
        return false;
    }
}
