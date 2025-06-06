<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/ResourceEditPage.php');

$page = new AdminPageDecorator(new ResourceEditPage());
$page->PageLoad();
