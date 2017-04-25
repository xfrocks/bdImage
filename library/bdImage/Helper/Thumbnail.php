<?php

class bdImage_Helper_Thumbnail
{
    public static $maxAttempt = 3;
    public static $coolDownSeconds = 600;
    public static $cropTopLeft = false;

    const ERROR_DOWNLOAD_REMOTE_URI = '/download/remote/uri.error';
    const HASH_FALLBACK = 'default';

    public static function main()
    {
        $startTime = microtime(true);
        if (headers_sent()) {
            die(1);
        }

        set_time_limit(0);
        $url = filter_input(INPUT_GET, 'url');
        $size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT);
        $mode = filter_input(INPUT_GET, 'mode');
        $hash = filter_input(INPUT_GET, 'hash');

        if (!is_string($url) || strlen($url) === 0
            || !is_int($size) || $size === 0
            || !is_string($mode) || strlen($mode) === 0
            || !is_string($hash) || strlen($hash) === 0
            || bdImage_Helper_Data::computeHash($url, $size, $mode) != $hash
        ) {
            // invalid request, we may issue 401 but this is more of a security feature
            // so we are issuing 403 response now...
            header('HTTP/1.0 403 Forbidden');
            die(1);
        }

        XenForo_Application::disablePhpErrorHandler();
        set_error_handler(array(__CLASS__, 'handlePhpError'));

        try {
            $thumbnailUri = bdImage_Helper_Thumbnail::getThumbnailUri($url, $size, $mode, $hash);
            header('Location: ' . $thumbnailUri, true, 302);
        } catch (bdImage_Exception_WithImage $ewi) {
            $ewi->output();
            XenForo_Error::logException($ewi, false, '[Sent output] ');
        } catch (Exception $e) {
            header('HTTP/1.0 500 Internal Server Error');
            if (XenForo_Application::debugMode()) {
                XenForo_Error::logException($e, false, '[Sent 500] ');
            }
        }

        if (XenForo_Application::debugMode() && !headers_sent()) {
            header('X-Thumbnail-Time: ' . (microtime(true) - $startTime));
        }

        die(0);
    }

    public static function handlePhpError($errorType)
    {
        if (!($errorType & error_reporting())) {
            return;
        }

        $args = func_get_args();
        throw new XenForo_Exception(json_encode($args));
    }

    /**
     * @param string $url
     * @param int $size
     * @param int|string $mode
     * @param string $hash
     * @return string
     */
    public static function getThumbnailUri($url, $size, $mode, $hash)
    {
        $config = XenForo_Application::getConfig();
        foreach (array(
                     'maxAttempt',
                     'coolDownSeconds',
                     'cropTopLeft'
                 ) as $key) {
            // $config['bdImage_maxAttempt'] = 3;
            // $config['bdImage_coolDownSeconds'] = 600;
            // $config['bdImage_cropTopLeft'] = false;
            self::$$key = $config->get('bdImage_' . $key, self::$$key);
        }

        return self::_buildThumbnailLink($url, $size, $mode, $hash);
    }

    /**
     * @param string $url
     * @param int $size
     * @param int|string $mode
     * @param string $hash
     * @return string
     */
    public static function buildPhpLink($url, $size, $mode, $hash)
    {
        $phpUrl = bdImage_Listener::$phpUrl;
        if (!is_string($phpUrl) || strlen($phpUrl) === 0) {
            $phpUrl = sprintf('%s/%s/thumbnail.php',
                rtrim(XenForo_Application::getOptions()->get('boardUrl'), '/'),
                bdImage_Listener::$generatorDirName);
        }

        return sprintf('%s?url=%s&size=%d&mode=%s&hash=%s',
            $phpUrl, rawurlencode($url), intval($size), $mode, $hash);
    }

    /**
     * @param string $url
     * @param int $size
     * @param int|string $mode
     * @param string $hash
     * @return string
     *
     * @throws bdImage_Exception_WithImage
     */
    protected static function _buildThumbnailLink($url, $size, $mode, $hash)
    {
        $forceRebuild = false;
        if (!empty($_REQUEST['rebuild'])) {
            $forceRebuild = $_REQUEST['rebuild'] === bdImage_Helper_Data::computeHash(
                    self::buildPhpLink($url, $size, $mode, $hash), 0, 'rebuild');
        }

        $cachePath = bdImage_Helper_File::getCachePath($url, $size, $mode, $hash);
        $cacheFileSize = bdImage_Helper_File::getImageFileSizeIfExists($cachePath);

        $thumbnailUrl = bdImage_Helper_File::getCacheUrl($url, $size, $mode, $hash);
        $thumbnailUri = XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
        if ($cacheFileSize > bdImage_Helper_File::THUMBNAIL_ERROR_FILE_LENGTH
            && !$forceRebuild
        ) {
            return sprintf('%s?%d', $thumbnailUri, $cacheFileSize);
        }

        $thumbnailError = array();
        if ($cacheFileSize > 0 && self::$maxAttempt > 1) {
            $thumbnailError = bdImage_Helper_File::getThumbnailError($cachePath);

            if (isset($thumbnailError['latestAttempt'])
                && $thumbnailError['latestAttempt'] > XenForo_Application::$time - self::$coolDownSeconds
                && $hash !== self::HASH_FALLBACK
                && !$forceRebuild
            ) {
                $fallbackImage = self::_getFallbackImage($size, $mode);
                if ($fallbackImage !== null) {
                    self::_log('Cooling down...');
                    return self::_buildThumbnailLink($fallbackImage, $size, $mode, self::HASH_FALLBACK);
                }
            }
        }

        $imagePath = self::_downloadImageIfNeeded($url);

        $imageType = self::_guessImageTypeByFileExtension($url, $imagePath);
        if ($imageType !== null) {
            self::_log('Guessed image type: %d', $imageType);
            $imageObj = self::_createImageObjFromFile($imagePath, $imageType);
            if (empty($imageObj)) {
                if (self::_detectImageType($imagePath, $imageType)) {
                    self::_log('Detected image type: %d', $imageType);
                    $imageObj = self::_createImageObjFromFile($imagePath, $imageType);
                }
            }
        }

        if (empty($imageObj)
            && $hash !== self::HASH_FALLBACK
        ) {
            $fallbackImage = self::_getFallbackImage($size, $mode);
            if ($fallbackImage !== null) {
                if (self::$maxAttempt > 1
                    && (!isset($thumbnailError['attemptCount'])
                        || $thumbnailError['attemptCount'] < self::$maxAttempt)
                ) {
                    self::_log('Using fallback image...');
                    bdImage_Helper_File::saveThumbnailError($cachePath, $thumbnailError);
                    return self::_buildThumbnailLink($fallbackImage, $size, $mode, self::HASH_FALLBACK);
                } else {
                    if (self::$maxAttempt > 1) {
                        self::_log('Exceeded MAX_ATTEMPT');
                    }
                    $fallbackImageType = self::_guessImageTypeByFileExtension($fallbackImage);
                    try {
                        $imageObj = self::_createImageObjFromFile($fallbackImage, $fallbackImageType);
                    } catch (Exception $e) {
                        self::_log($e->getMessage());
                    }
                }
            }
        }

        if (empty($imageObj)) {
            self::_log('Using blank image...');
            $imageObj = XenForo_Image_Abstract::createImage($size, $size);
        }

        if (is_callable(array($imageObj, 'bdImage_optimizeOutput'))) {
            call_user_func(array($imageObj, 'bdImage_optimizeOutput'), true);
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

        $cacheDir = dirname($cachePath);
        try {
            XenForo_Helper_File::createDirectory($cacheDir, true);
        } catch (Exception $e) {
            throw new bdImage_Exception_WithImage($e->getMessage(), $imageObj, $e);
        }
        if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
            throw new bdImage_Exception_WithImage(sprintf('Dir %s is not writable', $cacheDir), $imageObj);
        }
        if (file_exists($cachePath) && !is_writable($cachePath)) {
            throw new bdImage_Exception_WithImage(sprintf('Path %s is not writable', $cachePath), $imageObj);
        }

        $tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'bdImageThumbnail_');
        if (!is_string($tempFile)) {
            throw new bdImage_Exception_WithImage(sprintf('tempnam() returns %s',
                var_export($tempFile, true)), $imageObj);
        }

        $outputImageType = self::_guessImageTypeByFileExtension($cachePath);
        $imageObj->output($outputImageType, $tempFile, bdImage_Listener::$imageQuality);

        $tempFileSize = filesize($tempFile);
        try {
            XenForo_Helper_File::safeRename($tempFile, $cachePath);
        } catch (Exception $e) {
            @unlink($tempFile);
            throw new bdImage_Exception_WithImage($e->getMessage(), $imageObj, $e);
        }

        try {
            XenForo_Helper_File::makeWritableByFtpUser($cachePath);
        } catch (Exception $e) {
            // ignore this one
        }

        if (is_callable(array($imageObj, 'bdImage_cleanUp'))) {
            call_user_func(array($imageObj, 'bdImage_cleanUp'));
        }
        unset($imageObj);

        self::_log('Done');
        return sprintf('%s?%d', $thumbnailUri, $tempFileSize);
    }

    /**
     * @param XenForo_Image_Abstract $imageObj
     * @param int $targetHeight
     */
    protected static function _resizeStretchWidth(XenForo_Image_Abstract $imageObj, $targetHeight)
    {
        $targetWidth = $targetHeight / $imageObj->getHeight() * $imageObj->getWidth();
        $imageObj->thumbnail($targetWidth, $targetHeight);
    }

    /**
     * @param XenForo_Image_Abstract $imageObj
     * @param int $targetWidth
     */
    protected static function _resizeStretchHeight(XenForo_Image_Abstract $imageObj, $targetWidth)
    {
        $targetHeight = $targetWidth / $imageObj->getWidth() * $imageObj->getHeight();
        $imageObj->thumbnail($targetWidth, $targetHeight);
    }

    /**
     * @param XenForo_Image_Abstract $imageObj
     * @param int $targetWidth
     * @param int $targetHeight
     */
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
            self::_cropCenter($imageObj, $targetWidth, $targetHeight);
        } else {
            if ($origRatio > $cropRatio) {
                self::_log('Requested size is larger than the image size');
                self::_cropCenter($imageObj, $imageObj->getHeight() * $cropRatio, $imageObj->getHeight());
            } else {
                self::_cropCenter($imageObj, $imageObj->getWidth(), $imageObj->getWidth() / $cropRatio);
            }
        }
    }

    /**
     * @param XenForo_Image_Abstract $imageObj
     * @param int $target
     */
    protected static function _cropSquare(XenForo_Image_Abstract $imageObj, $target)
    {
        $imageObj->thumbnailFixedShorterSide($target);
        self::_cropCenter($imageObj, $target, $target);
    }

    /**
     * @param XenForo_Image_Abstract $imageObj
     * @param int $cropWidth
     * @param int $cropHeight
     */
    protected static function _cropCenter(XenForo_Image_Abstract $imageObj, $cropWidth, $cropHeight)
    {
        if (self::$cropTopLeft) {
            // revert to top left cropping (old version behavior)
            $imageObj->crop(0, 0, $cropWidth, $cropHeight);
            return;
        }

        $width = $imageObj->getWidth();
        $height = $imageObj->getHeight();
        $x = floor(($width - $cropWidth) / 2);
        $y = floor(($height - $cropHeight) / 2);
        $imageObj->crop($x, $y, $cropWidth, $cropHeight);
    }

    /**
     * @param int $size
     * @param int|string $mode
     * @return string
     */
    protected static function _getFallbackImage($size, $mode)
    {
        $bestFallbackImage = null;
        $bestRatio = null;
        $fallbackImages = preg_split('#\s#', bdImage_Option::get('fallbackImages'), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($fallbackImages as $fallbackImage) {
            if (substr($fallbackImage, 0, 1) !== '/') {
                /** @var XenForo_Application $application */
                $application = XenForo_Application::getInstance();
                $fallbackImage = sprintf('%s/%s', rtrim($application->getRootDir(), '/'), $fallbackImage);
            }
            $fallbackImageSize = bdImage_ShippableHelper_ImageSize::calculate($fallbackImage);

            if (empty($fallbackImageSize['width'])
                || empty($fallbackImageSize['height'])
            ) {
                continue;
            }
            $fallbackRatio = $fallbackImageSize['width'] / $fallbackImageSize['height'];

            switch ($mode) {
                case bdImage_Integration::MODE_STRETCH_WIDTH:
                    if ($bestRatio === null
                        || $bestRatio > $fallbackRatio
                    ) {
                        $bestFallbackImage = $fallbackImage;
                        $bestRatio = $fallbackRatio;
                    }
                    break;
                case bdImage_Integration::MODE_STRETCH_HEIGHT:
                    if ($bestRatio === null
                        || $bestRatio < $fallbackRatio
                    ) {
                        $bestFallbackImage = $fallbackImage;
                        $bestRatio = $fallbackRatio;
                    }
                    break;
                default:
                    if (is_numeric($mode) && $mode > 0) {
                        $ratio = $size / $mode;
                    } else {
                        $ratio = 1;
                    }
                    if ($bestRatio === null
                        || abs(1 - $bestRatio / $ratio) > abs(1 - $fallbackRatio / $ratio)
                    ) {
                        $bestFallbackImage = $fallbackImage;
                        $bestRatio = $fallbackRatio;
                    }
                    break;
            }
        }

        return $bestFallbackImage;
    }

    /**
     * @param string $path
     * @param int $type
     * @return null|XenForo_Image_Abstract
     *
     * @see XenForo_Image_Gd::createFromFileDirect
     * @see XenForo_Image_ImageMagick_Pecl::createFromFileDirect
     */
    protected static function _createImageObjFromFile($path, $type)
    {
        try {
            return XenForo_Image_Abstract::createFromFile($path, $type);
        } catch (Exception $e) {
            self::_log($e->getMessage());
            return null;
        }
    }

    /**
     * @param string $url
     * @return string
     */
    protected static function _downloadImageIfNeeded($url)
    {
        $cachedPathOrUrl = bdImage_Helper_File::getImageCachedPathOrUrl($url);
        if (bdImage_Helper_File::existsAndNotEmpty($cachedPathOrUrl)) {
            self::_log('Using cached path %s...', $cachedPathOrUrl);
            return $cachedPathOrUrl;
        }

        $boardUrl = XenForo_Application::getOptions()->get('boardUrl');
        if (strpos($url, '..') === false
            && strpos($url, $boardUrl) === 0
        ) {
            $localFilePath = self::_getLocalFilePath(substr($url, strlen($boardUrl)));
            if (strlen($localFilePath) > 0
                && bdImage_Helper_File::existsAndNotEmpty($localFilePath)
            ) {
                self::_log('Using local file path %s...', $localFilePath);
                return $localFilePath;
            }
        }

        if (preg_match('#attachments/(.+\.)*(?<id>\d+)/#', $url, $matches)) {
            $fullIndex = XenForo_Link::buildPublicLink('full:index');
            $canonicalIndex = XenForo_Link::buildPublicLink('canonical:index');
            if (strpos($url, $fullIndex) === 0 || strpos($url, $canonicalIndex) === 0) {
                $attachmentDataFilePath = self::_getAttachmentDataFilePath($matches['id']);
                if (bdImage_Helper_File::existsAndNotEmpty($attachmentDataFilePath)) {
                    self::_log('Using attachment data file path %s...', $attachmentDataFilePath);
                    return $attachmentDataFilePath;
                }
            }
        }

        // this is a remote uri, try to download it and return the downloaded file's path
        $originalCachePath = bdImage_Helper_File::getOriginalCachePath($url);
        if (!bdImage_Helper_File::existsAndNotEmpty($originalCachePath)) {
            XenForo_Helper_File::createDirectory(dirname($originalCachePath), true);
            $downloaded = bdImage_ShippableHelper_TempFile::download($url, array(
                'tempFile' => $originalCachePath,
                'maxDownloadSize' => XenForo_Application::getOptions()->get('attachmentMaxFileSize') * 1024 * 10,
            ));
            if (empty($downloaded)) {
                self::_log('Cannot download url %s', $url);
                return self::ERROR_DOWNLOAD_REMOTE_URI;
            }
        }

        self::_log('Using original cache path %s...', $originalCachePath);
        return $originalCachePath;
    }

    /**
     * @param string $path
     * @return string
     */
    protected static function _getLocalFilePath($path)
    {
        // remove query parameters
        $path = preg_replace('#(\?|\#).*$#', '', $path);
        if (strlen($path) === 0) {
            return $path;
        }

        $extension = XenForo_Helper_File::getFileExtension($path);
        if (!in_array($extension, array('gif', 'jpeg', 'jpg', 'png'), true)) {
            return '';
        }

        /** @var XenForo_Application $app */
        $app = XenForo_Application::getInstance();
        $path = sprintf('%s/%s', rtrim($app->getRootDir(), '/'), ltrim($path, '/'));

        return $path;
    }

    /**
     * @param int $attachmentId
     * @return string
     */
    protected static function _getAttachmentDataFilePath($attachmentId)
    {
        /** @var XenForo_Model_Attachment $attachmentModel */
        static $attachmentModel = null;
        static $attachments = array();

        if ($attachmentModel === null) {
            $attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
        }

        if (!isset($attachments[$attachmentId])) {
            $attachments[$attachmentId] = $attachmentModel->getAttachmentById($attachmentId);
        }

        if (empty($attachments[$attachmentId])) {
            return '';
        }

        return $attachmentModel->getAttachmentDataFilePath($attachments[$attachmentId]);
    }

    /**
     * @param string $url
     * @param string $path
     * @return int|null
     */
    protected static function _guessImageTypeByFileExtension($url, $path = '')
    {
        switch ($path) {
            case self::ERROR_DOWNLOAD_REMOTE_URI:
                return null;
        }

        $ext = XenForo_Helper_File::getFileExtension($url);
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
            default:
                $result = IMAGETYPE_JPEG;
        }

        return $result;
    }

    /**
     * @param string $path
     * @param int $guessedImageType
     * @return bool
     */
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

        if ($detectedImageType !== null
            && $detectedImageType !== $guessedImageType
        ) {
            $guessedImageType = $detectedImageType;
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected static function _log()
    {
        static $headerCount = 0;

        if (!XenForo_Application::debugMode()) {
            return false;
        }

        $args = func_get_args();
        $message = call_user_func_array('sprintf', $args);

        $scriptFilename = (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
        $scriptFilename = basename($scriptFilename);
        if ($scriptFilename === 'thumbnail.php') {
            return self::_setHeaderSafe(sprintf('X-Thumbnail-%d: %s', $headerCount++, $message));
        }

        $requestPaths = XenForo_Application::get('requestPaths');
        return XenForo_Helper_File::log(__CLASS__, sprintf('%s: %s', $requestPaths['requestUri'], $message));
    }

    /**
     * @param string $string
     * @param bool $replace
     * @param null|int $httpResponseCode
     * @return bool
     */
    protected static function _setHeaderSafe($string, $replace = true, $httpResponseCode = null)
    {
        if (headers_sent()) {
            return false;
        }

        header($string, $replace, $httpResponseCode);

        return true;
    }
}