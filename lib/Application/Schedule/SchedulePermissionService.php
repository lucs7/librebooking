<?php

class SchedulePermissionService implements IPermissionService
{
    /**
     * @var IPermissionService
     */
    private $permissionService;

    public function __construct(IPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * @return bool
     */
    public function CanAccessResource(IPermissibleResource $resource, UserSession $user)
    {
        return $this->permissionService->CanAccessResource($resource, $user);
    }

    /**
     * @return bool
     */
    public function CanBookResource(IPermissibleResource $resource, UserSession $user)
    {
        return $this->permissionService->CanBookResource($resource, $user);
    }

    /**
     * @return bool
     */
    public function CanViewResource(IPermissibleResource $resource, UserSession $user)
    {
        return $this->permissionService->CanViewResource($resource, $user);
    }
}
