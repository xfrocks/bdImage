<?php

class bdImage_Integration
{
    const MODE_CROP_EQUAL = 'ce';
    const MODE_STRETCH_WIDTH = 'sw';
    const MODE_STRETCH_HEIGHT = 'sh';

    protected static $_calculatedImageSizes = array();

    /**
     * @param string|array $imageData
     * @param int $size
     * @param string|int $mode
     * @return string thumbnail url
     */
    public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
    {
        $unpacked = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = $unpacked[bdImage_Helper_Data::IMAGE_URL];
        if (empty($imageUrl)
            || !defined('BDIMAGE_IS_WORKING')
        ) {
            return $imageUrl;
        }

        $size = intval($size);
        if ($size === 0) {
            return '';
        }

        $imageSize = self::getSize($unpacked, false);
        if ($imageSize !== false
            && parse_url($imageUrl) !== false
        ) {
            // we have the image size information
            // try to return the image url itself if its size matches the requested thumbnail
            list($imageWidth, $imageHeight) = $imageSize;
            switch ($mode) {
                case self::MODE_STRETCH_WIDTH:
                    if ($imageHeight === $size) {
                        return $imageUrl;
                    }
                    break;
                case self::MODE_STRETCH_HEIGHT:
                    if ($imageWidth === $size) {
                        return $imageUrl;
                    }
                    break;
                default:
                    if (is_numeric($mode)) {
                        if ($imageWidth === $size
                            && $imageHeight === intval($mode)
                        ) {
                            return $imageUrl;
                        }
                    } else {
                        if ($imageWidth === $size
                            && $imageHeight === $size
                        ) {
                            return $imageUrl;
                        }
                    }
            }
        }

        $thumbnailUrl = null;
        $hash = bdImage_Helper_Data::computeHash($imageUrl, $size, $mode);

        if (!bdImage_Listener::$skipCacheCheck) {
            $cachePath = bdImage_Helper_File::getCachePath($imageUrl, $size, $mode, $hash);
            $cacheFileHash = bdImage_Helper_File::getCacheFileHash($cachePath);
            if ($cacheFileHash !== null) {
                $thumbnailUrl = sprintf(
                    '%s?%s',
                    bdImage_Helper_File::getCacheUrl($imageUrl, $size, $mode, $hash),
                    $cacheFileHash
                );
            }
        }

        if (empty($thumbnailUrl)) {
            $thumbnailUrl = bdImage_Helper_Thumbnail::buildPhpLink($imageUrl, $size, $mode, $hash);
        }

        return XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
    }

    /**
     * @param string $imageData
     * @return string original url
     */
    public static function getOriginalUrl($imageData)
    {
        $unpacked = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = $unpacked[bdImage_Helper_Data::IMAGE_URL];
        if (empty($imageUrl)
            || !defined('BDIMAGE_IS_WORKING')
            || substr($imageUrl, 0, 2) === '//'
            || substr($imageUrl, 0, 7) === 'http://'
            || substr($imageUrl, 0, 8) === 'https://'
        ) {
            // nothing to do here
            return $imageUrl;
        }

        $size = self::getSize($unpacked, false);
        if ($size === false) {
            // too bad, we cannot determine the size
            return $imageUrl;
        }

        return self::buildThumbnailLink($unpacked, $size[0], $size[1]);
    }

    /**
     * @param string|array $imageData
     * @param bool $doFetch
     * @return array|false
     */
    public static function getSize($imageData, $doFetch = true)
    {
        $unpacked = bdImage_Helper_Data::unpack($imageData);

        $width = false;
        $height = false;

        if (isset($unpacked[bdImage_Helper_Data::IMAGE_WIDTH])
            && isset($unpacked[bdImage_Helper_Data::IMAGE_HEIGHT])
        ) {
            $width = $unpacked[bdImage_Helper_Data::IMAGE_WIDTH];
            $height = $unpacked[bdImage_Helper_Data::IMAGE_HEIGHT];
        }

        if ((empty($width) || empty($height)) && $doFetch) {
            $cachedPathOrUrl = bdImage_Helper_File::getImageCachedPathOrUrl($unpacked);
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
        list($width, $height) = self::getSize($imageData, false);
        if (empty($width) || empty($height)) {
            return '';
        }

        return bdImage_ShippableHelper_ImageSize::getDataUriAtSize($width, $height);
    }
}
