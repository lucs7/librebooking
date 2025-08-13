<?php

namespace LibreBooking\Pages;

interface IPage
{
    public function PageLoad();

    public function Redirect($url);

    public function RedirectToError($errorMessageId = \ErrorMessages::UNKNOWN_ERROR, $lastPage = '');

    public function IsPostBack();

    public function IsValid();

    public function GetLastPage($defaultPage = '');

    public function RegisterValidator($validatorId, $validator);

    public function EnforceCSRFCheck();

    public function GetSortField();

    public function GetSortDirection();
}

class_alias(__NAMESPACE__ . '\\IPage', 'IPage');
