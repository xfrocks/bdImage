<?php

class bdImage_Helper_Image
{
    public static function getSize($imageData, $doFetch = true)
    {
        $imageData = bdImage_Helper_Data::unpack($imageData);

        $width = false;
        $height = false;

        if (!empty($imageData['width'])
            && !empty($imageData['height'])
        ) {
            $width = $imageData['width'];
            $height = $imageData['height'];
        }

        if ((empty($width)
                || empty($height))
            && $doFetch
        ) {
            $uri = bdImage_Integration::getAccessibleUri($imageData['url']);
            if (!empty($uri)) {
                $imageSize = bdImage_Helper_ShippableHelper_ImageSize::calculate($uri);
                if (!empty($imageSize['width'])) {
                    $width = $imageSize['width'];
                }
                if (!empty($imageSize['height'])) {
                    $height = $imageSize['height'];
                }
            }
        }

        if (!empty($width)
            && !empty($height)
        ) {
            return array(
                $width,
                $height
            );
        } else {
            return false;
        }
    }
}