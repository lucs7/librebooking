<?php

require_once ROOT_DIR.'lib/Email/Messages/AccountActivationEmail.php';

class AccountActivation implements IAccountActivation
{
    /**
     * @var IAccountActivationRepository
     */
    private $activationRepository;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(IAccountActivationRepository $activationRepository, IUserRepository $userRepository)
    {
        $this->activationRepository = $activationRepository;
        $this->userRepository = $userRepository;
    }

    public function Notify(User $user)
    {
        $activationCode = BookedStringHelper::Random(30);

        $this->activationRepository->AddActivation($user, $activationCode);

        ServiceLocator::GetEmailService()->Send(new AccountActivationEmail($user, $activationCode));
    }

    public function Activate($activationCode)
    {
        $userId = $this->activationRepository->FindUserIdByCode($activationCode);
        $this->activationRepository->DeleteActivation($activationCode);

        if (null != $userId) {
            $user = $this->userRepository->LoadById($userId);
            $user->Activate();
            $this->userRepository->Update($user);

            return new ActivationResult(true, $user);
        }

        return new ActivationResult(false);
    }
}

class ActivationResult
{
    /**
     * @var bool
     */
    private $activated;

    /**
     * @var User|null
     */
    private $user;

    /**
     * @param bool      $activated
     * @param User|null $user
     */
    public function __construct($activated, $user = null)
    {
        $this->activated = $activated;
        $this->user = $user;
    }

    /**
     * @return bool
     */
    public function Activated()
    {
        return $this->activated;
    }

    /**
     * @return User|null
     */
    public function User()
    {
        return $this->user;
    }
}
