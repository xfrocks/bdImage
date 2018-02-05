<?php

class bdImage_Helper_Thumbnail
{
    public static $maxAttempt = 3;
    public static $coolDownSeconds = 600;
    public static $cropTopLeft = false;

    const ERROR_DOWNLOAD_REMOTE_URI = '/download/remote/uri.error';
    const HASH_FALLBACK = 'default';

    protected static $startTime = 0;

    public static function main()
    {
        self::$startTime = microtime(true);
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
            || bdImage_Helper_Data::computeHash($url, $size, $mode) !== $hash
        ) {
            // invalid request, we may issue 401 but this is more of a security feature
            // so we are issuing 403 response now...
            header('HTTP/1.0 403 Forbidden');
            die(1);
        }

        self::_bootstrap();

        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $boardUrl = XenForo_Application::getOptions()->get('boardUrl');
            if (strpos($boardUrl, $_SERVER['HTTP_ORIGIN']) === 0) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
        }

        try {
            $thumbnailLink = self::_buildThumbnailLink($url, $size, $mode, $hash);
            header('Location: ' . $thumbnailLink, true, 302);
        } catch (bdImage_Exception_WithImage $ewi) {
            $ewi->output();

            if ($ewi->getMessage()) {
                XenForo_Error::logException($ewi, false, '[Sent output] ');
            }
        } catch (Exception $e) {
            self::_echoHttp500WithTinyGif($e->getMessage());

            if (XenForo_Application::debugMode()) {
                XenForo_Error::logException($e, false, '[Sent 500] ');
            }
        }

        die(0);
    }

    public static function handlePhpError($errorType, $errorMessage)
    {
        if (!($errorType & error_reporting())) {
            return;
        }

        throw new XenForo_Exception($errorMessage);
    }

    public static function handleFatalError()
    {
        if (XenForo_Application::debugMode() && !headers_sent()) {
            header('X-Thumbnail-Time: ' . (microtime(true) - self::$startTime));
        }

        $error = @error_get_last();
        if (!$error) {
            return;
        }

        if (empty($error['type']) || !($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
            return;
        }

        $errorMessage = '';
        if (isset($error['message'])) {
            $errorMessage = $error['message'];
        }

        self::_echoHttp500WithTinyGif($errorMessage);
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
            $phpUrl = sprintf(
                '%s/%s/thumbnail.php',
                rtrim(XenForo_Application::getOptions()->get('boardUrl'), '/'),
                bdImage_Listener::$generatorDirName
            );
        }

        return sprintf(
            '%s?url=%s&size=%d&mode=%s&hash=%s',
            $phpUrl,
            rawurlencode($url),
            intval($size),
            $mode,
            $hash
        );
    }

    protected static function _bootstrap()
    {
        ini_set('display_errors', '0');
        XenForo_Application::disablePhpErrorHandler();
        set_error_handler(array(__CLASS__, 'handlePhpError'));
        register_shutdown_function(array(__CLASS__, 'handleFatalError'));

        $config = XenForo_Application::getConfig();
        foreach (array(
                     'maxAttempt',
                     'coolDownSeconds',
                     'cropTopLeft'
                 ) as $key) {
            self::$$key = $config->get('bdImage_' . $key, self::$$key);
        }

        /** @noinspection SpellCheckingInspection */
        $httpReferrerKey = 'HTTP_REFERER';
        if (!empty($_SERVER[$httpReferrerKey])) {
            $_POST['_bdImage_httpReferrer'] = $_SERVER[$httpReferrerKey];
        }
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
            $rebuildHash = bdImage_Helper_Data::computeHash(
                self::buildPhpLink($url, $size, $mode, $hash),
                0,
                'rebuild'
            );
            $forceRebuild = $_REQUEST['rebuild'] === $rebuildHash;
        }

        $cachePath = bdImage_Helper_File::getCachePath($url, $size, $mode, $hash);
        $cacheFileHash = bdImage_Helper_File::getCacheFileHash($cachePath);
        $thumbnailUrl = bdImage_Helper_File::getCacheUrl($url, $size, $mode, $hash);
        $thumbnailUri = XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
        if ($cacheFileHash !== null && !$forceRebuild) {
            return sprintf('%s?%s', $thumbnailUri, $cacheFileHash);
        }

        $thumbnailError = array();
        if ($cacheFileHash > 0 && self::$maxAttempt > 1) {
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

        $imageInfo = self::_getImageInfo($imagePath);
        if (!empty($imageInfo)) {
            $maxImageResizePixelCount = XenForo_Application::getConfig()->get('maxImageResizePixelCount');
            if ($imageInfo['width'] * $imageInfo['height'] > $maxImageResizePixelCount) {
                self::_log(
                    'Image too big (%dx%d, type %d), maxImageResizePixelCount=%d',
                    $imageInfo['width'],
                    $imageInfo['height'],
                    $imageInfo['type'],
                    $maxImageResizePixelCount
                );
                $imageInfo = null;
            }
        }

        if (!empty($imageInfo)) {
            self::_log('Image type: %d', $imageInfo['type']);
            $imageObj = self::_createImageObjFromFile($imagePath, $imageInfo['type']);
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
                    $fallbackImageInfo = self::_getImageInfo($fallbackImage);
                    if ($fallbackImageInfo !== null) {
                        $imageObj = self::_createImageObjFromFile($fallbackImage, $fallbackImageInfo['type']);
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

        if ($imageObj->getWidth() * $imageObj->getHeight() < 4096) {
            // Optimization: skip caching if the output is too small
            throw new bdImage_Exception_WithImage('', $imageObj);
        }

        $cacheDir = dirname($cachePath);
        try {
            XenForo_Helper_File::createDirectory($cacheDir, true);
        } catch (Exception $e) {
            throw new bdImage_Exception_WithImage($e->getMessage(), $imageObj, $e);
        }

        $tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'bdImageThumbnail_');
        if (!is_string($tempFile)) {
            throw new bdImage_Exception_WithImage(
                sprintf('tempnam() returns %s', var_export($tempFile, true)),
                $imageObj
            );
        }

        /** @noinspection PhpParamsInspection */
        $imageObj->output(
            bdImage_Helper_File::getImageTypeFromCachePath($cachePath),
            $tempFile,
            bdImage_Listener::$imageQuality
        );

        try {
            $renamed = XenForo_Helper_File::safeRename($tempFile, $cachePath);
            if (!$renamed) {
                @unlink($tempFile);
                throw new bdImage_Exception_WithImage(
                    sprintf('Cannot rename %s to %s', $tempFile, $cachePath),
                    $imageObj
                );
            }
        } catch (Exception $e) {
            @unlink($tempFile);
            throw new bdImage_Exception_WithImage($e->getMessage(), $imageObj, $e);
        }

        if (is_callable(array($imageObj, 'bdImage_cleanUp'))) {
            call_user_func(array($imageObj, 'bdImage_cleanUp'));
        }
        unset($imageObj);

        self::_log('Done');
        return sprintf('%s?%s', $thumbnailUri, bdImage_Helper_File::getCacheFileHash($cachePath));
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
            /** @noinspection PhpParamsInspection */
            $imageObj->crop(0, 0, $cropWidth, $cropHeight);
            return;
        }

        $width = $imageObj->getWidth();
        $height = $imageObj->getHeight();
        $x = floor(($width - $cropWidth) / 2);
        $y = floor(($height - $cropHeight) / 2);

        /** @noinspection PhpParamsInspection */
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

        if (strpos($url, '..') === false) {
            $boardUrl = XenForo_Application::getOptions()->get('boardUrl');
            if (strpos($url, $boardUrl) === 0) {
                $localFilePath = self::_getLocalFilePath(substr($url, strlen($boardUrl)));
                if (strlen($localFilePath) > 0) {
                    self::_log('Using local file path %s...', $localFilePath);
                    return $localFilePath;
                }
            }

            $externalDataFilePath = self::_getExternalDataFilePath($url);
            if (strlen($externalDataFilePath) > 0) {
                self::_log('Using external_data file path %s...', $externalDataFilePath);
                return $externalDataFilePath;
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
     * @param string $pathFromUrl
     * @param null|string $rootDir
     * @return string
     */
    protected static function _getLocalFilePath($pathFromUrl, $rootDir = null)
    {
        // remove query parameters
        $pathFromUrl = preg_replace('/(\?|#).*$/', '', $pathFromUrl);
        if (strlen($pathFromUrl) === 0) {
            return '';
        }

        $extension = XenForo_Helper_File::getFileExtension($pathFromUrl);
        if (!in_array($extension, array('gif', 'jpeg', 'jpg', 'png'), true)) {
            return '';
        }

        if ($rootDir === null) {
            /** @var XenForo_Application $app */
            $app = XenForo_Application::getInstance();
            $rootDir = $app->getRootDir();
        }

        $path = sprintf('%s/%s', rtrim($rootDir, '/'), ltrim($pathFromUrl, '/'));
        if (bdImage_Helper_File::existsAndNotEmpty($path)) {
            return $path;
        }

        return '';
    }

    /**
     * @param string $url
     * @return string
     */
    protected static function _getExternalDataFilePath($url)
    {
        $externalDataUrls = bdImage_Listener::$externalDataUrls;
        $externalDataUrls[XenForo_Application::$externalDataUrl] = '';
        foreach ($externalDataUrls as $externalDataUrl => $externalDataPath) {
            $externalDataUrlLength = strlen($externalDataUrl);
            if (substr($url, 0, $externalDataUrlLength) !== $externalDataUrl) {
                continue;
            }

            if ($externalDataPath === '') {
                $externalDataPath = XenForo_Helper_File::getExternalDataPath();
            }

            $path = self::_getLocalFilePath(substr($url, $externalDataUrlLength), $externalDataPath);
            if (strlen($path) > 0) {
                return $path;
            }
        }

        return '';
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

    protected static function _getImageInfo($path)
    {
        switch ($path) {
            case self::ERROR_DOWNLOAD_REMOTE_URI:
                return null;
        }

        $raw = getimagesize($path);
        if (!is_array($raw)) {
            return null;
        }

        $info = array();
        $info['width'] = $raw[0];
        $info['height'] = $raw[1];
        $info['type'] = $raw[2];

        return $info;
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

    /**
     * @param string $errorMessage
     */
    protected static function _echoHttp500WithTinyGif($errorMessage)
    {
        header('HTTP/1.0 500 Internal Server Error');

        if (is_string($errorMessage)
            && strlen($errorMessage) > 0
            && XenForo_Application::debugMode()
        ) {
            header('X-Error-Message: ' . $errorMessage);
        }

        // http://probablyprogramming.com/2009/03/15/the-tiniest-gif-ever
        header('Content-Type: image/gif');
        /** @noinspection SpellCheckingInspection */
        echo base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
    }
}
