<?php

require_once ROOT_DIR.'Domain/Access/namespace.php';

class TermsOfServiceValidator extends ValidatorBase implements IValidator
{
    /**
     * @var ITermsOfServiceRepository
     */
    private $termsOfServiceRepository;
    /**
     * @var bool
     */
    private $hasAcknowledged;

    /**
     * @param bool $hasAcknowledged
     */
    public function __construct(ITermsOfServiceRepository $termsOfServiceRepository, $hasAcknowledged)
    {
        $this->termsOfServiceRepository = $termsOfServiceRepository;
        $this->hasAcknowledged = $hasAcknowledged;
    }

    public function Validate()
    {
        $this->isValid = true;

        $terms = $this->termsOfServiceRepository->Load();

        if (null != $terms && $terms->AppliesToRegistration()) {
            $this->isValid = $this->hasAcknowledged;
        }
    }
}
