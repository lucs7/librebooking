<?php

namespace LibreBooking\Pages;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR. 'Domain/Access/namespace.php');

class TermsOfServicePage extends Page
{
    public function __construct()
    {
        parent::__construct('TermsOfService');
    }

    public function PageLoad()
    {
        $repo = new TermsOfServiceRepository();
        $tos = $repo->Load();

        if ($tos != null) {
            $this->Set('TermsContent', $tos->Text());
        }
        $this->Display('tos.tpl');
    }
}
class_alias(__NAMESPACE__ . '\\TermsOfServicePage', 'TermsOfServicePage');
