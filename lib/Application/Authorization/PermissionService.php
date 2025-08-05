<?php

interface IPermissionService
{
    /**
     * @return bool
     */
    public function CanAccessResource(IPermissibleResource $resource, UserSession $user);

    /**
     * @return bool
     */
    public function CanBookResource(IPermissibleResource $resource, UserSession $user);

    /**
     * @return bool
     */
    public function CanViewResource(IPermissibleResource $resource, UserSession $user);
}

class PermissionService implements IPermissionService
{
    /**
     * @var IResourcePermissionStore
     */
    private $_store;

    private $_allowedResourceIds;

    private $_bookableResourceIds;

    private $_viewOnlyResourceIds;

    public function __construct(IResourcePermissionStore $store)
    {
        $this->_store = $store;
    }

    /**
     * @return bool
     */
    public function CanAccessResource(IPermissibleResource $resource, UserSession $user)
    {
        if ($user->IsAdmin) {
            return true;
        }

        if (null == $this->_allowedResourceIds) {
            $this->_allowedResourceIds = $this->_store->GetAllResources($user->UserId);
        }

        return in_array($resource->GetResourceId(), $this->_allowedResourceIds);
    }

    /**
     * @return bool
     */
    public function CanBookResource(IPermissibleResource $resource, UserSession $user)
    {
        if ($user->IsAdmin) {
            return true;
        }

        if (null == $this->_bookableResourceIds) {
            $this->_bookableResourceIds = $this->_store->GetBookableResources($user->UserId);
        }

        return in_array($resource->GetResourceId(), $this->_bookableResourceIds);
    }

    /**
     * @return bool
     */
    public function CanViewResource(IPermissibleResource $resource, UserSession $user)
    {
        if ($user->IsAdmin) {
            return true;
        }

        if (null == $this->_viewOnlyResourceIds) {
            $this->_viewOnlyResourceIds = $this->_store->GetViewOnlyResources($user->UserId);
        }

        return in_array($resource->GetResourceId(), $this->_viewOnlyResourceIds);
    }
}
