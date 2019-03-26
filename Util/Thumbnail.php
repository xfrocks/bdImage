<?php

namespace Xfrocks\Image\Util;

use XF\Entity\Attachment;
use Xfrocks\Image\Exception\WithImage;
use Xfrocks\Image\Integration;
use Xfrocks\Image\Listener;

class Thumbnail
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
            || Data::computeHash($url, $size, $mode) !== $hash
        ) {
            // invalid request, we may issue 401 but this is more of a security feature
            // so we are issuing 403 response now...
            header('HTTP/1.0 403 Forbidden');
            die(1);
        }

        self::_bootstrap();

        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $boardUrl = \XF::app()->options()->boardUrl;
            if (strpos($boardUrl, $_SERVER['HTTP_ORIGIN']) === 0) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
        }

        try {
            $thumbnailLink = self::_buildThumbnailLink($url, $size, $mode, $hash);
            header('Location: ' . $thumbnailLink, true, 302);
        } catch (WithImage $ewi) {
            $ewi->output();

            if ($ewi->getMessage()) {
                \XF::logException($ewi, false, '[Sent output] ');
            }
        } catch (\Exception $e) {
            self::_echoHttp500WithTinyGif($e->getMessage());

            if (\XF::$debugMode) {
                \XF::logException($e, false, '[Sent 500] ');
            }
        }

        die(0);
    }

    public static function handlePhpError($errorType, $errorMessage)
    {
        if (!($errorType & error_reporting())) {
            return;
        }

        throw new \Exception($errorMessage);
    }

    public static function handleFatalError()
    {
        if (\XF::$debugMode && !headers_sent()) {
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
     * @param array $params
     * @return string
     */
    public static function buildPhpLink($url, $size, $mode, array $params = array())
    {
        $phpUrl = Listener::$phpUrl;
        if (!is_string($phpUrl) || strlen($phpUrl) === 0) {
            $phpUrl = sprintf(
                '%s/%s/thumbnail.php',
                rtrim(\XF::app()->options()->boardUrl, '/'),
                Listener::$generatorDirName
            );
        }

        $params['url'] = $url;
        $params['size'] = intval($size);
        $params['mode'] = strval($mode);
        $params['hash'] = Data::computeHash($params['url'], $params['size'], $params['mode']);

        $url = $phpUrl;
        foreach ($params as $key => $param) {
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= sprintf('%s=%s', $key, rawurlencode($param));
        }

        return $url;
    }

    protected static function _bootstrap()
    {
        ini_set('display_errors', '0');
//        XenForo_Application::disablePhpErrorHandler();
        set_error_handler(array(__CLASS__, 'handlePhpError'));
        register_shutdown_function(array(__CLASS__, 'handleFatalError'));

        $config = \XF::app()->config();
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
     * @throws \Xfrocks\Image\Exception\WithImage
     */
    protected static function _buildThumbnailLink($url, $size, $mode, $hash)
    {
        $forceRebuild = false;
        if (!empty($_REQUEST['rebuild'])) {
            $rebuildHash = Data::computeHash(
                self::buildPhpLink($url, $size, $mode),
                0,
                'rebuild'
            );
            $forceRebuild = $_REQUEST['rebuild'] === $rebuildHash;
        }

        $noRedirect = !empty($_REQUEST['_xfNoRedirect']);

        $cachePath = File::getCachePath($url, $size, $mode, $hash);
        list($cacheFileSize, , $cacheFileHash) = File::getCacheFileHash($cachePath);
        $thumbnailUrl = File::getCacheUrl($url, $size, $mode, $hash);
//        $thumbnailUri = XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
        // TODO: Convert `XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);` to XF2
        $thumbnailUri = $thumbnailUrl;

        if ($cacheFileHash && $cacheFileSize !== File::THUMBNAIL_ERROR_FILE_LENGTH && !$forceRebuild) {
            if ($noRedirect) {
                self::_log('readfile %s', $cachePath);
                readfile($cachePath);
                exit(0);
            }

            return sprintf('%s?%s', $thumbnailUri, $cacheFileHash);
        }

        $thumbnailError = array();
        if ($cacheFileSize === File::THUMBNAIL_ERROR_FILE_LENGTH && self::$maxAttempt > 1) {
            $thumbnailError = File::getThumbnailError($cachePath);

            if (isset($thumbnailError['latestAttempt'])
                && $thumbnailError['latestAttempt'] > \XF::$time - self::$coolDownSeconds
                && $hash !== self::HASH_FALLBACK
                && !$forceRebuild
            ) {
                $fallbackImage = self::_getFallbackImage($size, $mode);
                if ($fallbackImage !== null) {
                    self::_log(
                        'Cooling down... Seconds since latest attempt = %d',
                        \XF::$time - $thumbnailError['latestAttempt']
                    );
                    return self::_buildThumbnailLink($fallbackImage, $size, $mode, self::HASH_FALLBACK);
                }
            }
        }

        $imagePath = self::_downloadImageIfNeeded($url);

        $imageInfo = self::_getImageInfo($imagePath);
        if (!empty($imageInfo)) {
            $config = \XF::config();
            $maxImageResizePixelCount = intval($config->get('maxImageResizePixelCount'));
            if ($maxImageResizePixelCount === 1) {
                $maxImageResizePixelCount = Listener::$maxImageResizePixelOurs;
            }
            if ($maxImageResizePixelCount > 0 &&
                $imageInfo['width'] * $imageInfo['height'] > $maxImageResizePixelCount
            ) {
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
            $imageObj = self::_createImageObjFromFile($imagePath);
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
                    File::saveThumbnailError($cachePath, $thumbnailError);
                    return self::_buildThumbnailLink($fallbackImage, $size, $mode, self::HASH_FALLBACK);
                } else {
                    if (self::$maxAttempt > 1) {
                        self::_log('Exceeded MAX_ATTEMPT');
                    }
                    $fallbackImageInfo = self::_getImageInfo($fallbackImage);
                    if ($fallbackImageInfo !== null) {
                        $imageObj = self::_createImageObjFromFile($fallbackImage);
                    }
                }
            }
        }

        if (empty($imageObj)) {
            self::_log('Using blank image...');
            $imageObj = \XF::app()->imageManager()->createImage($size, $size);
        }

        $callable = [$imageObj, 'bdImage_optimizeOutput'];
        if (is_callable($callable)) {
            call_user_func($callable, true);
        }

        switch ($mode) {
            case Integration::MODE_STRETCH_WIDTH:
                self::_resizeStretchWidth($imageObj, $size);
                break;
            case Integration::MODE_STRETCH_HEIGHT:
                self::_resizeStretchHeight($imageObj, $size);
                break;
            default:
                if (is_numeric($mode)) {
                    self::_cropExact($imageObj, $size, (int) $mode);
                } else {
                    self::_cropSquare($imageObj, $size);
                }

                break;
        }

        if ($noRedirect) {
            self::_log('$noRedirect');
            throw new WithImage('', $imageObj);
        }

        $cacheDir = dirname($cachePath);
        try {
            \XF\Util\File::createDirectory($cacheDir, true);
        } catch (\Exception $e) {
            throw new WithImage($e->getMessage(), $imageObj, $e);
        }

        $tempFile = tempnam(\XF\Util\File::getTempDir(), 'bdImageThumbnail_');
        if (!is_string($tempFile)) {
            throw new WithImage(
                sprintf('tempnam() returns %s', var_export($tempFile, true)),
                $imageObj
            );
        }

        $imageObj->output(
            $tempFile,
            Listener::$imageQuality
        );

//        try {
//            // TODO: Convert `XenForo_Helper_File::safeRename(...) to XF2
////            $renamed = \XF\Util\File::safeRename($tempFile, $cachePath);
//            if (!$renamed) {
//                @unlink($tempFile);
//                throw new WithImage(
//                    sprintf('Cannot rename %s to %s', $tempFile, $cachePath),
//                    $imageObj
//                );
//            }
//        } catch (\Exception $e) {
//            @unlink($tempFile);
//            throw new WithImage($e->getMessage(), $imageObj, $e);
//        }

        $callable = [$imageObj, 'bdImage_cleanUp'];
        if (is_callable($callable)) {
            call_user_func($callable);
        }
        unset($imageObj);

        self::_log('Done');

        list(, , $newCacheFileHash) = File::getCacheFileHash($cachePath);
        return sprintf('%s?%s', $thumbnailUri, $newCacheFileHash);
    }

    /**
     * @param \XF\Image\AbstractDriver $imageObj
     * @param int $targetHeight
     */
    protected static function _resizeStretchWidth(\XF\Image\AbstractDriver $imageObj, $targetHeight)
    {
        $targetWidth = $targetHeight / $imageObj->getHeight() * $imageObj->getWidth();
        $imageObj->resize($targetWidth, $targetHeight);
    }

    /**
     * @param \XF\Image\AbstractDriver $imageObj
     * @param int $targetWidth
     */
    protected static function _resizeStretchHeight(\XF\Image\AbstractDriver $imageObj, $targetWidth)
    {
        $targetHeight = $targetWidth / $imageObj->getWidth() * $imageObj->getHeight();
        $imageObj->resize($targetWidth, $targetHeight);
    }

    /**
     * @param \XF\Image\AbstractDriver $imageObj
     * @param int $targetWidth
     * @param int $targetHeight
     */
    protected static function _cropExact(\XF\Image\AbstractDriver $imageObj, $targetWidth, $targetHeight)
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
            $imageObj->resize($thumbnailWidth, $thumbnailHeight);
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
     * @param \XF\Image\AbstractDriver $imageObj
     * @param int $target
     */
    protected static function _cropSquare(\XF\Image\AbstractDriver $imageObj, $target)
    {
        $imageObj->resizeShortEdge($target);
        self::_cropCenter($imageObj, $target, $target);
    }

    /**
     * @param \XF\Image\AbstractDriver $imageObj
     * @param int $cropWidth
     * @param int $cropHeight
     */
    protected static function _cropCenter(\XF\Image\AbstractDriver $imageObj, $cropWidth, $cropHeight)
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
        $fallbackImages = preg_split('#\s#', \XF::app()->options()->bdImage_fallbackImages, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($fallbackImages)) {
            throw new \InvalidArgumentException('Bad fallback images rules');
        }

        foreach ($fallbackImages as $fallbackImage) {
            if (substr($fallbackImage, 0, 1) !== '/') {
                $fallbackImage = sprintf('%s/%s', rtrim(\XF::getRootDirectory(), '/'), $fallbackImage);
            }
//            $fallbackImageSize = bdImage_ShippableHelper_ImageSize::calculate($fallbackImage);
            $fallbackImageSize = [];

            if (empty($fallbackImageSize['width'])
                || empty($fallbackImageSize['height'])
            ) {
                continue;
            }
            $fallbackRatio = $fallbackImageSize['width'] / $fallbackImageSize['height'];

            switch ($mode) {
                case Integration::MODE_STRETCH_WIDTH:
                    if ($bestRatio === null
                        || $bestRatio > $fallbackRatio
                    ) {
                        $bestFallbackImage = $fallbackImage;
                        $bestRatio = $fallbackRatio;
                    }
                    break;
                case Integration::MODE_STRETCH_HEIGHT:
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
     * @return null|\XF\Image\AbstractDriver
     *
     * @see XenForo_Image_Gd::createFromFileDirect
     * @see XenForo_Image_ImageMagick_Pecl::createFromFileDirect
     */
    protected static function _createImageObjFromFile($path)
    {
        try {
            return \XF::app()->imageManager()->imageFromFile($path);
        } catch (\Exception $e) {
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
        $cachedPathOrUrl = File::getImageCachedPathOrUrl($url);
        if (File::existsAndNotEmpty($cachedPathOrUrl)) {
            self::_log('Using cached path %s...', $cachedPathOrUrl);
            return $cachedPathOrUrl;
        }

        if (strpos($url, '..') === false) {
            $boardUrl = \XF::app()->options()->boardUrl;
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
            $fullIndex = \XF::app()->router('public')->buildLink('full:index');
            $canonicalIndex = \XF::app()->router('public')->buildLink('canonical:index');
            if (strpos($url, $fullIndex) === 0 || strpos($url, $canonicalIndex) === 0) {
                $attachmentDataFilePath = self::_getAttachmentDataFilePath($matches['id']);
                if (File::existsAndNotEmpty($attachmentDataFilePath)) {
                    self::_log('Using attachment data file path %s...', $attachmentDataFilePath);
                    return $attachmentDataFilePath;
                }
            }
        }

        // this is a remote uri, try to download it and return the downloaded file's path
        $originalCachePath = File::getOriginalCachePath($url);
        if (!File::existsAndNotEmpty($originalCachePath)) {
            \XF\Util\File::createDirectory(dirname($originalCachePath), true);
//            $downloaded = bdImage_ShippableHelper_TempFile::download($url, array(
//                'tempFile' => $originalCachePath,
//                'maxDownloadSize' => XenForo_Application::getOptions()->get('attachmentMaxFileSize') * 1024 * 10,
//            ));
//            if (empty($downloaded)) {
//                self::_log('Cannot download url %s', $url);
//                return self::ERROR_DOWNLOAD_REMOTE_URI;
//            }
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

        $extension = \XF\Util\File::getFileExtension($pathFromUrl);
        if (!in_array($extension, array('gif', 'jpeg', 'jpg', 'png'), true)) {
            return '';
        }

        if ($rootDir === null) {
            $rootDir = \XF::getRootDirectory();
        }

        $path = sprintf('%s/%s', rtrim($rootDir, '/'), ltrim($pathFromUrl, '/'));
        if (File::existsAndNotEmpty($path)) {
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
        $externalDataUrls = Listener::$externalDataUrls;
        $externalDataUrls[\XF::app()->config('externalDataUrl')] = '';
        foreach ($externalDataUrls as $externalDataUrl => $externalDataPath) {
            $externalDataUrlLength = strlen($externalDataUrl);
            if (substr($url, 0, $externalDataUrlLength) !== $externalDataUrl) {
                continue;
            }

            if ($externalDataPath === '') {
                $externalDataPath = \XF::config('externalDataPath');
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
        /** @var Attachment|null $attachment */
        $attachment = \XF::em()->find('XF:Attachment', $attachmentId);
        if (!$attachment) {
            return '';
        }

        return $attachment->Data->getAbstractedDataPath();
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

        if (!\XF::$debugMode) {
            return false;
        }

        $args = func_get_args();
        $message = call_user_func_array('sprintf', $args);

        $scriptFilename = (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
        $scriptFilename = basename($scriptFilename);
        if ($scriptFilename === 'thumbnail.php') {
            return self::_setHeaderSafe(sprintf('X-Thumbnail-%d: %s', $headerCount++, $message));
        }

        // TODO: Complete log function
//        $requestPaths = XenForo_Application::get('requestPaths');
//        return XenForo_Helper_File::log(__CLASS__, sprintf('%s: %s', $requestPaths['requestUri'], $message));
        return false;
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
            && \XF::$debugMode
        ) {
            header('X-Error-Message: ' . $errorMessage);
        }

        // http://probablyprogramming.com/2009/03/15/the-tiniest-gif-ever
        header('Content-Type: image/gif');
        /** @noinspection SpellCheckingInspection */
        echo base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
    }
}
