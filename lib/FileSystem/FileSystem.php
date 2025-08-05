<?php

namespace Booked;

require_once ROOT_DIR.'lib/Common/namespace.php';
require_once ROOT_DIR.'lib/Application/Admin/namespace.php';

interface IFileSystem
{
    /**
     * @param $path         string
     * @param $fileName     string
     * @param $fileContents string
     *
     * @return bool
     */
    public function Save($path, $fileName, $fileContents);

    /**
     * @param $fullPath string
     *
     * @return string
     */
    public function GetFileContents($fullPath);

    /**
     * @param $fullPath string
     *
     * @return bool
     */
    public function RemoveFile($fullPath);

    /**
     * @return string
     */
    public function GetReservationAttachmentsPath();

    /**
     * @param $fullPath string
     *
     * @return string[]
     */
    public function GetFiles($fullPath);

    /**
     * @param string $fullPath
     *
     * @return bool
     */
    public function Exists($fullPath);

    /**
     * @return void
     */
    public function FlushSmartyCache();
}

class FileSystem implements IFileSystem
{
    public function Save($path, $fileName, $fileContents)
    {
        $fullName = $path.$fileName;
        \Log::Debug('Saving file to %s', $fullName);

        if (false === file_put_contents($fullName, $fileContents)) {
            \Log::Error('Could not write contents of file: %s', $fullName);

            return false;
        }

        return true;
    }

    public function GetFileContents($fullPath)
    {
        $contents = file_get_contents($fullPath);
        if (false === $contents) {
            \Log::Error('Could not read contents of file: %s', $fullPath);

            return null;
        }

        return $contents;
    }

    public function RemoveFile($fullPath)
    {
        \Log::Debug('Deleting file: %s', $fullPath);
        if (false === unlink($fullPath)) {
            \Log::Error('Could not delete file: %s', $fullPath);

            return false;
        }

        return true;
    }

    public function GetReservationAttachmentsPath()
    {
        return \Paths::ReservationAttachments();
    }

    public function GetFiles($path, $filter = '*')
    {
        return glob($path.$filter);
    }

    public function Exists($fullPath)
    {
        return file_exists($fullPath);
    }

    public function FlushSmartyCache()
    {
        $cacheDir = new \TemplateCacheDirectory();
        $cacheDir->Flush();
    }
}
