<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Email/Messages/ForgotPasswordEmail.php');

class ForgotPwdPresenter
{
    /**
     * @var IForgotPwdPage
     */
    private $_page = null;

    public function __construct(IForgotPwdPage $page)
    {
        $this->_page = $page;
    }

    public function PageLoad()
    {
        if (
            Configuration::Instance()->GetKey(ConfigKeys::PASSWORD_DISABLE_RESET, new BooleanConverter())
            || !PluginManager::Instance()->LoadAuthentication()->ShowForgotPasswordPrompt()
        ) {
            $this->_page->SetEnabled(false);
            return;
        }

        if ($this->_page->ResetClicked()) {
            $this->SendRandomPassword();
            $this->_page->ShowResetEmailSent(true);
        }
    }

    public function SendRandomPassword()
    {
        $emailAddress = $this->_page->GetEmailAddress();

        Log::Info('Password reset requested from REMOTE_ADDR: %s', $_SERVER['REMOTE_ADDR'] ?? '');

        $temporaryPassword = Password::GenerateRandom();

        $passwordEncryption = new PasswordEncryption();
        $salt = $passwordEncryption->Salt();
        $encrypted = $passwordEncryption->Encrypt($temporaryPassword, $salt);

        $userRepository = new UserRepository();
        $user = $userRepository->FindByEmail($emailAddress);

        if ($user != null) {
            $user->ChangePassword($encrypted, $salt);
            $userRepository->Update($user);

            $emailMessage = new ForgotPasswordEmail($user, $temporaryPassword);
            ServiceLocator::GetEmailService()->Send($emailMessage);
        }
    }
}
