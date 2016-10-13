<?php

class bdImage_Integration
{
    const CONFIG_GENERATOR_DIR_NAME = 'bdImage_generatorDirName';
    public static $generatorDirName = 'bdImage';

    // used in getImageWidth and getImageHeight
    // to cache image sizes calculated
    protected static $_imageSizes = array();

    const MODE_CROP_EQUAL = 'ce';
    const MODE_STRETCH_WIDTH = 'sw';
    const MODE_STRETCH_HEIGHT = 'sh';

    public static function getBbCodeImages($bbCode, array $contentData = array(), $dwOrModel = null)
    {
        /** @var bdImage_BbCode_Formatter_Collector $formatter */
        $formatter = XenForo_BbCode_Formatter_Base::create('bdImage_BbCode_Formatter_Collector');
        if (!empty($contentData)) {
            $formatter->setContentData($contentData);
        }
        if (!empty($dw)) {
            $formatter->setDwOrModel($dwOrModel);
        }

        $parser = new XenForo_BbCode_Parser($formatter);
        $parser->render($bbCode);

        return $formatter->getImageDataMany();
    }

    public static function getBbCodeImage($bbCode, array $contentData = array(), $dwOrModel = null)
    {
        $imageDataMany = self::getBbCodeImages($bbCode, $contentData, $dwOrModel);
        if (empty($imageDataMany)) {
            return null;
        }

        $imageData = reset($imageDataMany);
        if (empty($imageData)) {
            return null;
        }

        $imageUrl = self::getImage($imageData);
        $imageWidth = self::getImageWidth($imageData);
        $imageHeight = self::getImageHeight($imageData);

        return bdImage_Helper_Data::pack($imageUrl, $imageWidth, $imageHeight,
            bdImage_Helper_Data::unpack($imageData));
    }

    public static function getAccessibleUri($url)
    {
        if (empty($url)) {
            return false;
        }

        if (bdImage_Helper_File::existsAndNotEmpty($url)) {
            return $url;
        }

        /** @var XenForo_Application $application */
        $application = XenForo_Application::getInstance();
        $path = $application->getRootDir() . '/' . $url;
        if (bdImage_Helper_File::existsAndNotEmpty($path)) {
            return realpath($path);
        }

        $originalCachePath = bdImage_Integration::getOriginalCachePath($url);
        if (bdImage_Helper_File::existsAndNotEmpty($originalCachePath)) {
            return $originalCachePath;
        } else {
            return $url;
        }
    }

    public static function getCachePath($uri, $size, $mode, $hash, $pathPrefix = false)
    {
        if ($pathPrefix === false) {
            $pathPrefix = XenForo_Helper_File::getExternalDataPath();
        }

        $uriExt = XenForo_Helper_File::getFileExtension($uri);
        switch ($uriExt) {
            case 'gif':
            case 'png':
                $ext = $uriExt;
                break;
            default:
                $ext = 'jpg';
        }

        $divider = substr(md5($hash), 0, 2);

        return sprintf('%s/%s/cache/%s_%s/%s/%s.%s', $pathPrefix,
            self::$generatorDirName, $size, $mode, $divider, $hash, $ext);
    }

    public static function getCacheUrl($uri, $size, $mode, $hash)
    {
        return self::getCachePath($uri, $size, $mode, $hash, XenForo_Application::$externalDataUrl);
    }

    public static function getOriginalCachePath($uri, $pathPrefix = false)
    {
        if ($pathPrefix === false) {
            $pathPrefix = XenForo_Helper_File::getInternalDataPath();
        }

        return sprintf('%s/%s/cache/%s/%s.orig', $pathPrefix, self::$generatorDirName, gmdate('Ym'), md5($uri));
    }

    public static function getImage($imageData)
    {
        return bdImage_Helper_Data::get($imageData, 'url');
    }

    public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
    {
        if (!defined('BDIMAGE_IS_WORKING')) {
            return self::getImage($imageData);
        }

        $imageData = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = $imageData['url'];

        if (Zend_Uri::check($imageUrl)
            && !empty($imageData['width'])
            && !empty($imageData['height'])
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

        $cachePath = bdImage_Integration::getCachePath($imageUrl, $size, $mode, $hash);
        $cacheFileSize = bdImage_Helper_File::getImageFileSizeIfExists($cachePath);
        if ($cacheFileSize > bdImage_Helper_File::THUMBNAIL_ERROR_FILE_LENGTH) {
            $thumbnailUrl = sprintf('%s?%d', bdImage_Integration::getCacheUrl($imageUrl,
                $size, $mode, $hash), $cacheFileSize);
        }

        if (empty($thumbnailUrl)) {
            $thumbnailUrl = bdImage_Helper_Thumbnail::buildPhpLink($imageUrl, $size, $mode, $hash);
        }

        return XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
    }

    public static function buildFullSizeLink($imageData)
    {
        if (!defined('BDIMAGE_IS_WORKING')) {
            return self::getImage($imageData);
        }

        $imageData = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = $imageData['url'];

        if (Zend_Uri::check($imageUrl)) {
            // it is an uri already, return asap
            return $imageUrl;
        }

        $size = bdImage_Helper_Image::getSize($imageData);
        if (empty($size)) {
            // too bad, we cannot determine the size
            return $imageUrl;
        }

        return self::buildThumbnailLink($imageData, $size[0], $size[1]);
    }

    public static function getImgAttributes($imageData, $size, $mode = self::MODE_CROP_EQUAL)
    {
        $width = 0;
        $height = 0;

        switch ($mode) {
            case self::MODE_CROP_EQUAL:
                $width = intval($size);
                $height = intval($size);
                break;
            case self::MODE_STRETCH_WIDTH:
                $imageWidth = self::getImageWidth($imageData);
                $imageHeight = self::getImageHeight($imageData);
                if ($imageWidth > 0 && $imageHeight > 0) {
                    $height = intval($size);
                    $width = $height / $imageHeight * $imageWidth;
                }
                break;
            case self::MODE_STRETCH_HEIGHT:
                $imageWidth = self::getImageWidth($imageData);
                $imageHeight = self::getImageHeight($imageData);
                if ($imageWidth > 0 && $imageHeight > 0) {
                    $width = intval($size);
                    $height = $width / $imageWidth * $imageHeight;
                }
                break;
            default:
                if (is_numeric($mode)) {
                    $width = intval($size);
                    $height = intval($mode);
                }
        }

        $attributes = array();

        if ($width > 0) {
            $attributes[] = sprintf(' width="%d"', $width);
        }
        if ($height > 0) {
            $attributes[] = sprintf(' height="%d"', $height);
        }

        return implode('', $attributes);
    }

    public static function getImageWidth($imageData)
    {
        if (!isset(self::$_imageSizes[$imageData])) {
            self::$_imageSizes[$imageData] = bdImage_Helper_Image::getSize($imageData);
        }

        if (is_array(self::$_imageSizes[$imageData])) {
            return self::$_imageSizes[$imageData][0];
        }

        return false;
    }

    public static function getImageHeight($imageData)
    {
        if (!isset(self::$_imageSizes[$imageData])) {
            self::$_imageSizes[$imageData] = bdImage_Helper_Image::getSize($imageData);
        }

        if (is_array(self::$_imageSizes[$imageData])) {
            return self::$_imageSizes[$imageData][1];
        }

        return false;
    }
}
