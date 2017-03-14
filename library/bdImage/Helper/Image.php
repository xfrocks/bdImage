<?php

class bdImage_Helper_Image
{
    protected static $_calculatedImageSizes = array();

    /**
     * @param string $imageData
     * @param bool $doFetch
     * @return array|false
     */
    public static function getSize($imageData, $doFetch = true)
    {
        $imageData = bdImage_Helper_Data::unpack($imageData);

        $width = false;
        $height = false;

        if (isset($imageData['width']) && isset($imageData['height'])) {
            $width = $imageData['width'];
            $height = $imageData['height'];
        }

        if ((empty($width) || empty($height)) && $doFetch) {
            $cachedPathOrUrl = bdImage_Helper_File::getImageCachedPathOrUrl($imageData);
            if (strlen($cachedPathOrUrl) > 0) {
                if (!isset(self::$_calculatedImageSizes[$cachedPathOrUrl])) {
                    self::$_calculatedImageSizes[$cachedPathOrUrl] = bdImage_ShippableHelper_ImageSize::calculate($cachedPathOrUrl);
                }
                $imageSizeRef =& self::$_calculatedImageSizes[$cachedPathOrUrl];

                if (!empty($imageSizeRef['width'])) {
                    $width = $imageSizeRef['width'];
                }
                if (!empty($imageSizeRef['height'])) {
                    $height = $imageSizeRef['height'];
                }
            }
        }

        if (is_string($width)) {
            $width = intval($width);
        }
        if (is_string($height)) {
            $height = intval($height);
        }

        if ($width > 0 && $height > 0) {
            return array($width, $height);
        } else {
            return false;
        }
    }

    /**
     * @param string $imageData
     * @return string
     */
    public static function getDataUriTransparentAtSameSize($imageData)
    {
        list($width, $height) = self::getSize($imageData);
        if (empty($width) || empty($height)) {
            return '';
        }

        return bdImage_ShippableHelper_ImageSize::getDataUriAtSize($width, $height);
    }
}