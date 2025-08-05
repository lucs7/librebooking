<?php

interface IAccountActivation
{
    /**
     * @abstract
     *
     * @return void
     */
    public function Notify(User $user);

    /**
     * @abstract
     *
     * @param string $activationCode
     *
     * @return ActivationResult
     */
    public function Activate($activationCode);
}
