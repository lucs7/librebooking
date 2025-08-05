<?php

define('ROOT_DIR', '../../');
require_once ROOT_DIR.'Pages/Ajax/AutoCompletePage.php';

$page = new AutoCompletePage();
if (AutoCompleteType::Organization != $page->GetType()) {
    $page = new SecurePageDecorator($page);
}
$page->PageLoad();
