<?php

namespace LibreBooking\Pages\Admin\Import;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'Presenters/ActionPresenter.php');
require_once(ROOT_DIR . 'Presenters/Admin/Import/ICalImportPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');

interface IICalImportPage extends IActionPage
{
    /**
     * @return UploadedFile
     */
    public function GetImportFile();

    /**
     * @param int $numberImported
     * @param int $numberSkipped
     */
    public function SetNumberImported($numberImported, $numberSkipped);
}

class ICalImportPage extends ActionPage implements IICalImportPage
{
    /**
     * @var ICalImportPresenter
     */
    private $presenter;

    public function __construct()
    {
        $this->presenter = new ICalImportPresenter(
            $this,
            new UserRepository(),
            new ResourceRepository(),
            new ReservationRepository(),
            new Registration(),
            new ScheduleRepository()
        );

        parent::__construct('ImportICS', 1);
    }

    public function ProcessAction()
    {
        $this->presenter->ProcessAction();
    }

    public function ProcessDataRequest($dataRequest)
    {
        // no-op
    }

    public function ProcessPageLoad()
    {
        $this->Display('Admin/Import/ics_import.tpl');
    }

    public function GetImportFile()
    {
        return $this->server->GetFile(FormKeys::ICS_IMPORT_FILE);
    }

    public function SetNumberImported($numberImported, $numberSkipped)
    {
        $this->SetJson(['importCount' => $numberImported, 'skippedRows' => $numberSkipped]);
    }
}
class_alias(__NAMESPACE__ . '\\IICalImportPage', 'IICalImportPage');
class_alias(__NAMESPACE__ . '\\ICalImportPage', 'ICalImportPage');
