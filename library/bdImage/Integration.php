<?php

class bdImage_Integration
{
    const MODE_CROP_EQUAL = 'ce';
    const MODE_STRETCH_WIDTH = 'sw';
    const MODE_STRETCH_HEIGHT = 'sh';

    /**
     * @param string $imageData
     * @param int $size
     * @param string|int $mode
     * @return string thumbnail url
     */
    public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
    {
        if (!defined('BDIMAGE_IS_WORKING')) {
            return bdImage_Helper_Data::get($imageData, 'url');
        }

        $imageData = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = $imageData['url'];
        if (empty($imageUrl)) {
            return '';
        }

        if (!empty($imageData['width'])
            && !empty($imageData['height'])
            && parse_url($imageUrl) !== false
        ) {
            // we have the image size information
            // try to return the image url itself if its size matches the requested thumbnail
            switch ($mode) {
                case self::MODE_STRETCH_WIDTH:
                    if ($imageData['height'] == $size) {
                        return $imageUrl;
                    }
                    break;
                case self::MODE_STRETCH_HEIGHT:
                    if ($imageData['width'] == $size) {
                        return $imageUrl;
                    }
                    break;
                default:
                    if (is_numeric($mode)) {
                        if ($imageData['width'] == $size
                            && $imageData['height'] == $mode
                        ) {
                            return $imageUrl;
                        }
                    } else {
                        if ($imageData['width'] == $size
                            && $imageData['height'] == $size
                        ) {
                            return $imageUrl;
                        }
                    }
            }
        }

        $hash = bdImage_Helper_Data::computeHash($imageUrl, $size, $mode);

        $cachePath = bdImage_Helper_File::getCachePath($imageUrl, $size, $mode, $hash);
        $cacheFileSize = bdImage_Helper_File::getImageFileSizeIfExists($cachePath);
        if ($cacheFileSize > bdImage_Helper_File::THUMBNAIL_ERROR_FILE_LENGTH) {
            $thumbnailUrl = sprintf('%s?%d', bdImage_Helper_File::getCacheUrl($imageUrl,
                $size, $mode, $hash), $cacheFileSize);
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
        if (!defined('BDIMAGE_IS_WORKING')) {
            return bdImage_Helper_Data::get($imageData, 'url');
        }

        $imageData = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = $imageData['url'];

        if (Zend_Uri::check($imageUrl)) {
            // it is an uri already, return asap
            return $imageUrl;
        }

        $size = bdImage_Helper_Image::getSize($imageData);
        if ($size === false) {
            // too bad, we cannot determine the size
            return $imageUrl;
        }

        return self::buildThumbnailLink($imageData, $size[0], $size[1]);
    }

    /**
     * @param string $imageData
     * @return int
     */
    public static function getImageWidth($imageData)
    {
        if (!defined('BDIMAGE_IS_WORKING')) {
            return 0;
        }

        $imageSize = bdImage_Helper_Image::getSize($imageData);
        if (!is_array($imageSize)) {
            return 0;
        }

        return $imageSize[0];
    }

    /**
     * @param string $imageData
     * @return int
     */
    public static function getImageHeight($imageData)
    {
        if (!defined('BDIMAGE_IS_WORKING')) {
            return 0;
        }

        $imageSize = bdImage_Helper_Image::getSize($imageData);
        if (!is_array($imageSize)) {
            return 0;
        }

        return $imageSize[1];
    }
}
