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
                $imageSize = bdImage_ShippableHelper_ImageSize::calculate($uri);
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

    public static function getDataUriTransparentAtSameSize($imageData)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }

        list($width, $height) = self::getSize($imageData);
        if (empty($width) || empty($height)) {
            return '';
        }

        $gcd = self::_findGreatestCommonDivisor($width, $height);
        $width /= $gcd;
        $height /= $gcd;

        $image = imagecreate($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $colorTransparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $colorTransparent);

        ob_start();
        imagepng($image);
        $imageBytes = ob_get_contents();
        ob_end_clean();

        return 'data:image/png;base64,' . base64_encode($imageBytes);
    }

    protected static function _findGreatestCommonDivisor($a, $b)
    {
        $mod = $a % $b;
        if ($mod === 0) {
            return $b;
        } else {
            return self::_findGreatestCommonDivisor($b, $mod);
        }
    }
}