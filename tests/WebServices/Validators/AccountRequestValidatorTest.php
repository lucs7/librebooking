<?php

require_once ROOT_DIR.'WebServices/Validators/AccountRequestValidator.php';

class AccountRequestValidatorTest extends TestBase
{
    /**
     * @var FakeAttributeService
     */
    private $attributeService;

    /**
     * @var AccountRequestValidator
     */
    private $validator;

    /**
     * @var FakeUserRepository
     */
    private $userRepository;
    /**
     * @var WebServiceUserSession
     */
    private $session;

    public function setUp(): void
    {
        parent::setup();

        $this->attributeService = new FakeAttributeService();
        $this->userRepository = new FakeUserRepository();
        $this->session = new WebServiceUserSession(1);

        $this->validator = new AccountRequestValidator($this->attributeService, $this->userRepository);
    }

    public function testWhenCreateRequestIsJunk()
    {
        $errors = $this->validator->ValidateCreate(null);
        $this->assertEquals(1, count($errors));
    }

    public function testCreateRequiredFields()
    {
        $this->expectsAttributeValidator();
        $request = CreateAccountRequest::Example();
        $request->firstName = null;
        $request->lastName = '';
        $request->userName = ' ';
        $request->password = ' ';
        $request->emailAddress = null;
        $request->timezone = ' ';
        $request->language = ' ';

        $this->userRepository->_Exists = null;

        $errors = $this->validator->ValidateCreate($request);
        $this->assertTrue(6 == count($errors));
    }

    public function testCreateValidatesEmailFormat()
    {
        $this->expectsAttributeValidator();
        $request = CreateAccountRequest::Example();
        $request->emailAddress = 'aaaaaa.com';
        $this->userRepository->_Exists = null;

        $errors = $this->validator->ValidateCreate($request);
        $this->assertTrue(1 == count($errors));
    }

    public function testCreateValidatesExistingEmail()
    {
        $this->expectsAttributeValidator();
        $request = CreateAccountRequest::Example();

        $this->userRepository->_Exists = 1;

        $errors = $this->validator->ValidateCreate($request);
        $this->assertTrue(2 == count($errors));
    }

    public function testCreateValidatesAttributes()
    {
        $request = CreateAccountRequest::Example();
        $result = new AttributeServiceValidationResult(false, ['error']);
        $this->attributeService->_ValidationResult = $result;

        $errors = $this->validator->ValidateCreate($request);
        $this->assertTrue(1 == count($errors));
    }

    public function testWhenUpdateRequestIsJunk()
    {
        $errors = $this->validator->ValidateUpdate(null, $this->session);
        $this->assertEquals(1, count($errors));
    }

    public function testUpdateRequiredFields()
    {
        $this->expectsAttributeValidator();
        $request = UpdateAccountRequest::Example();
        $request->firstName = null;
        $request->lastName = '';
        $request->userName = ' ';
        $request->timezone = ' ';

        $errors = $this->validator->ValidateUpdate($request, $this->session);
        $this->assertTrue(3 == count($errors));
    }

    public function testUpdateValidatesEmailFormat()
    {
        $this->expectsAttributeValidator();
        $request = UpdateAccountRequest::Example();
        $request->emailAddress = 'aaaaaa.com';
        $errors = $this->validator->ValidateUpdate($request, $this->session);
        $this->assertTrue(1 == count($errors));
    }

    public function testUpdateValidatesExistingEmail()
    {
        $this->expectsAttributeValidator();
        $request = UpdateAccountRequest::Example();

        $this->userRepository->_Exists = 2;

        $errors = $this->validator->ValidateUpdate($request, $this->session);
        $this->assertTrue(2 == count($errors));
    }

    public function testUpdateValidatesAttributes()
    {
        $request = UpdateAccountRequest::Example();
        $result = new AttributeServiceValidationResult(false, ['error']);
        $this->attributeService->_ValidationResult = $result;

        $errors = $this->validator->ValidateUpdate($request, $this->session);
        $this->assertTrue(1 == count($errors));
    }

    public function testValidatePasswordRequired()
    {
        $request = UpdateAccountPasswordRequest::Example();
        $request->newPassword = '';
        $errors = $this->validator->ValidatePasswordUpdate($request, $this->session);
        $this->assertTrue(2 == count($errors));
    }

    public function testValidatePasswordMatch()
    {
        $request = UpdateAccountPasswordRequest::Example();
        $request->newPassword = '123abc';
        $request->currentPassword = 'old';

        $enc = new PasswordEncryption();
        $pw = $enc->EncryptPassword($request->currentPassword);

        $this->userRepository->_User->encryptedPassword = $pw->EncryptedPassword();
        $this->userRepository->_User->passwordSalt = $pw->Salt();

        $errors = $this->validator->ValidatePasswordUpdate($request, $this->session);
        $this->assertTrue(0 == count($errors));
    }

    public function testValidatePasswordDoesNotMatch()
    {
        $request = UpdateAccountPasswordRequest::Example();
        $request->newPassword = '123abc';
        $request->currentPassword = 'old';

        $enc = new PasswordEncryption();
        $pw = $enc->EncryptPassword('no match');

        $this->userRepository->_User->encryptedPassword = $pw->EncryptedPassword();
        $this->userRepository->_User->passwordSalt = $pw->Salt();

        $errors = $this->validator->ValidatePasswordUpdate($request, $this->session);
        $this->assertTrue(1 == count($errors));
    }

    private function expectsAttributeValidator()
    {
        $this->attributeService->_ValidationResult = new AttributeServiceValidationResult(true, null);
    }
}
