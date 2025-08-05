<?php

require_once ROOT_DIR.'lib/Application/Authorization/PermissionService.php';
require_once ROOT_DIR.'lib/Application/Authorization/PermissionServiceFactory.php';

class GuestPermissionService implements IPermissionService
{
    /**
     * @return bool
     */
    public function CanAccessResource(IPermissibleResource $resource, UserSession $user)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function CanBookResource(IPermissibleResource $resource, UserSession $user)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function CanViewResource(IPermissibleResource $resource, UserSession $user)
    {
        return true;
    }
}

class GuestPermissionServiceFactory implements IPermissionServiceFactory
{
    /**
     * @return IPermissionService
     */
    public function GetPermissionService()
    {
        return new GuestPermissionService();
    }
}
