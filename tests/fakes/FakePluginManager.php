<?php

class FakePluginManager extends PluginManager implements IPostRegistration
{
    public function __construct()
    {
    }

    public $preResPlugin;
    public $postResPlugin;
    public $postRegistrationPlugin;
    public $_LoadedRegistration = false;
    public $_RegistrationUser;
    public $_RegistrationPage;

    public function LoadPreReservation()
    {
        return (null == $this->preResPlugin) ? $this : $this->preResPlugin;
    }

    public function LoadPostReservation()
    {
        return (null == $this->postResPlugin) ? $this : $this->postResPlugin;
    }

    public function LoadPostRegistration()
    {
        $this->_LoadedRegistration = true;

        return (null == $this->postRegistrationPlugin) ? $this : $this->postRegistrationPlugin;
    }

    public function HandleSelfRegistration(User $user, IRegistrationPage $page, ILoginContext $loginContext)
    {
        $this->_RegistrationUser = $user;
        $this->_RegistrationPage = $page;
    }

    public function CreatePreUpdateService()
    {
        return null;
    }

    public function CreatePostUpdateService()
    {
        return null;
    }
}
