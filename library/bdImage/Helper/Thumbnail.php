<?php

class bdImage_Helper_Thumbnail
{
    public static function getThumbnailUri($imageUri, $size, $mode, $hash)
    {
        $cachePath = bdImage_Integration::getCachePath($imageUri, $size, $mode, $hash);

        $thumbnailUrl = bdImage_Integration::getCacheUrl($imageUri, $size, $mode, $hash);
        $thumbnailUri = XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
        if (bdImage_Helper_File::existsAndNotEmpty($cachePath)) {
            return $thumbnailUri;
        }

        $imagePath = self::_downloadImageIfNeeded($imageUri);

        $imageType = self::_guessImageTypeByFileExtension($imageUri);
        $imageObj = XenForo_Image_Abstract::createFromFile($imagePath, $imageType);
        if (empty($imageObj)) {
            if (self::_detectImageType($imagePath, $imageType)) {
                $imageObj = XenForo_Image_Abstract::createFromFile($imagePath, $imageType);
            }
        }
        if (empty($imageObj)) {
            throw new XenForo_Exception('Unable to read image');
        }

        switch ($mode) {
            case bdImage_Integration::MODE_STRETCH_WIDTH:
                self::_resizeStretchWidth($imageObj, $size);
                break;
            case bdImage_Integration::MODE_STRETCH_HEIGHT:
                self::_resizeStretchHeight($imageObj, $size);
                break;
            default:
                if (is_numeric($mode)) {
                    self::_cropExact($imageObj, $size, $mode);
                } else {
                    self::_cropSquare($imageObj, $size);
                }
                break;
        }

        if (is_callable(array($imageObj, 'bdImage_outputProgressiveJpeg'))) {
            call_user_func(array($imageObj, 'bdImage_outputProgressiveJpeg'), true);
        }

        $tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
        $imageObj->output($imageType, $tempFile);

        XenForo_Helper_File::createDirectory(dirname($cachePath), true);
        XenForo_Helper_File::safeRename($tempFile, $cachePath);

        return $thumbnailUri;
    }

    protected static function _resizeStretchWidth(XenForo_Image_Abstract $imageObj, $targetHeight)
    {
        $targetWidth = $targetHeight / $imageObj->getHeight() * $imageObj->getWidth();
        $imageObj->thumbnail($targetWidth, $targetHeight);
    }

    protected static function _resizeStretchHeight(XenForo_Image_Abstract $imageObj, $targetWidth)
    {
        $targetHeight = $targetWidth / $imageObj->getWidth() * $imageObj->getHeight();
        $imageObj->thumbnail($targetWidth, $targetHeight);
    }

    protected static function _cropExact(XenForo_Image_Abstract $imageObj, $targetWidth, $targetHeight)
    {
        $origRatio = $imageObj->getWidth() / $imageObj->getHeight();
        $cropRatio = $targetWidth / $targetHeight;
        if ($origRatio > $cropRatio) {
            $thumbnailHeight = $targetHeight;
            $thumbnailWidth = $thumbnailHeight * $origRatio;
        } else {
            $thumbnailWidth = $targetWidth;
            $thumbnailHeight = $thumbnailWidth / $origRatio;
        }

        if ($thumbnailWidth <= $imageObj->getWidth()
            && $thumbnailHeight <= $imageObj->getHeight()
        ) {
            $imageObj->thumbnail($thumbnailWidth, $thumbnailHeight);
            $imageObj->crop(0, 0, $targetWidth, $targetHeight);
        } else {
            // thumbnail requested is larger then the image size
            if ($origRatio > $cropRatio) {
                $imageObj->crop(0, 0, $imageObj->getHeight() * $cropRatio, $imageObj->getHeight());
            } else {
                $imageObj->crop(0, 0, $imageObj->getWidth(), $imageObj->getWidth() / $cropRatio);
            }
        }
    }

    protected static function _cropSquare(XenForo_Image_Abstract $imageObj, $target)
    {
        $imageObj->thumbnailFixedShorterSide($target);
        $imageObj->crop(0, 0, $target, $target);
    }

    protected static function _downloadImageIfNeeded($uri)
    {
        if (Zend_Uri::check($uri)) {
            $boardUrl = XenForo_Application::getOptions()->get('boardUrl');
            if (strpos($uri, $boardUrl) === 0) {
                // looks like an url from our own site
                $path = substr($uri, strlen($boardUrl));
                if (bdImage_Helper_File::existsAndNotEmpty($path)) {
                    return $path;
                }
            }

            // this is a remote uri, try to download it and return the downloaded file's path
            $originalCachePath = bdImage_Integration::getOriginalCachePath($uri);
            if (!bdImage_Helper_File::existsAndNotEmpty($originalCachePath)) {
                XenForo_Helper_File::createDirectory(dirname($originalCachePath), true);
                file_put_contents($originalCachePath, @file_get_contents($uri));
            }

            return $originalCachePath;
        }

        return $uri;
    }

    protected static function _guessImageTypeByFileExtension($uri)
    {
        $result = IMAGETYPE_JPEG;

        $ext = XenForo_Helper_File::getFileExtension($uri);
        switch ($ext) {
            case 'gif':
                $result = IMAGETYPE_GIF;
                break;
            case 'jpg':
            case 'jpeg':
                $result = IMAGETYPE_JPEG;
                break;
            case 'png':
                $result = IMAGETYPE_PNG;
                break;
        }

        return $result;
    }

    protected static function _detectImageType($path, &$guessedImageType)
    {
        $detectedImageType = null;

        $fh = fopen($path, 'rb');
        if (!empty($fh)) {
            $data = fread($fh, 2);

            if (!empty($data)
                && strlen($data) == 2
            ) {
                switch ($data) {
                    case 'BM':
                        $detectedImageType = IMAGETYPE_BMP;
                        break;
                    case 'GI':
                        $detectedImageType = IMAGETYPE_GIF;
                        break;
                    case chr(0xFF) . chr(0xd8):
                        $detectedImageType = IMAGETYPE_JPEG;
                        break;
                    case chr(0x89) . 'P':
                        $detectedImageType = IMAGETYPE_PNG;
                        break;
                    case 'II':
                        $detectedImageType = IMAGETYPE_TIFF_II;
                        break;
                    case 'MM':
                        $detectedImageType = IMAGETYPE_TIFF_MM;
                        break;
                }
            }

            fclose($fh);
        }

        if ($detectedImageType != $guessedImageType) {
            $guessedImageType = $detectedImageType;
            return true;
        }

        return false;
    }
}