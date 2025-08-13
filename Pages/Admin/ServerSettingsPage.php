<?php

namespace LibreBooking\Pages\Admin;

use LibreBooking\Pages\Page;
use LibreBooking\Pages\ActionPage;
use LibreBooking\Pages\SecurePage;
use LibreBooking\Pages\IActionPage;
use LibreBooking\Pages\IPage;
use LibreBooking\Pages\IPageable;
use IRepeatOptionsComposite;
require_once(ROOT_DIR . 'lib/Application/Admin/namespace.php');

class ServerSettingsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('ServerSettings');
    }

    public function PageLoad()
    {
        if ($this->TakingAction()) {
            $this->ProcessAction();
        }

        $plugins = $this->GetPlugins();

        $uploadDir = new ImageUploadDirectory();
        $cacheDir = new TemplateCacheDirectory();

        $this->Set('plugins', $plugins);
        $this->Set('currentTime', date('Y-m-d H:i:s (e P)'));
        $this->Set('imageUploadDirPermissions', substr(sprintf('%o', fileperms($uploadDir->GetDirectory())), -4));
        $this->Set('imageUploadDirectory', $uploadDir->GetDirectory());
        $this->Set('templateCacheDirectory', $cacheDir->GetDirectory());
        $this->Display('Configuration/server_settings.tpl');
    }

    public function ProcessAction()
    {
        if ($this->GetAction() == 'changePermissions') {
            $uploadDir = new ImageUploadDirectory();
            $uploadDir->MakeWriteable();
        } else {
            $cacheDir = new TemplateCacheDirectory();
            $cacheDir->Flush();
        }
    }

    private function GetPlugins()
    {
        $plugins = [];
        $dit = new RecursiveDirectoryIterator(ROOT_DIR . 'plugins');

        /** @var SplFileInfo $path  */
        foreach ($dit as $path) {
            if ($path->isDir() && basename($path->getPathname()) != '.' && basename($path->getPathname()) != '..') {
                $plugins[basename($path->getPathname())] = [];
                /** @var SplFileInfo $plugin  */
                foreach (new RecursiveDirectoryIterator($path) as $plugin) {
                    if ($plugin->isDir() && basename($plugin->getPathname()) != '.' && basename($plugin->getPathname()) != '..') {
                        $plugins[basename($path->getPathname())][] = basename($plugin->getPathname());
                    }
                }
            }
        }

        return $plugins;
    }
}
class_alias(__NAMESPACE__ . '\\ServerSettingsPage', 'ServerSettingsPage');
