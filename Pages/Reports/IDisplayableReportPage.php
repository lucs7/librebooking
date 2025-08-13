<?php

namespace LibreBooking\Pages\Reports;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;

interface IDisplayableReportPage
{
    public function BindReport(\IReport $report, \IReportDefinition $definition, $selectedColumns);

    public function DisplayError();

    public function ShowResults();

    public function PrintReport();

    public function ShowCsv();
}
class_alias(__NAMESPACE__ . '\\IDisplayableReportPage', 'IDisplayableReportPage');
