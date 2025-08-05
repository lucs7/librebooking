<?php

use claviska\SimpleImage;

interface IImageFactory
{
    /**
     * @return IImage
     */
    public function Load($pathToImage);
}

class ImageFactory implements IImageFactory
{
    public function Load($pathToImage)
    {
        if (!extension_loaded('gd')) {
            exit('gd extension is required for image upload');
        }

        try {
            $image = new SimpleImage();
            $image->fromFile($pathToImage); // correct method in the new version

            return new Image($image);
        } catch (Exception $err) {
            exit('Error loading image: '.$err->getMessage());
        }
    }
}
