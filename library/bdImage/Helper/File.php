<?php

class bdImage_Helper_File
{
    // current format
    // 3 magic bytes: ERR
    // 1 version: char
    // 1 attempt count: char
    // 4 latest attempt: unsigned long
    const THUMBNAIL_ERROR_FORMAT_PACK = 'CCN';
    const THUMBNAIL_ERROR_FORMAT_UNPACK = 'Cv/Cac/Nla';
    const THUMBNAIL_ERROR_MAGIC_BYTES = 'ERR';
    const THUMBNAIL_ERROR_VERSION = 2;
    const THUMBNAIL_ERROR_FILE_LENGTH = 9;

    /**
     * @param string $uri
     * @param int $size
     * @param int|string $mode
     * @param string $hash
     * @param bool $pathPrefix
     * @return string
     *
     * @see bdImage_Helper_File::getImageTypeFromCachePath()
     */
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

        return sprintf(
            '%s/%s/cache/%s_%s/%s/%s.%s',
            $pathPrefix,
            bdImage_Listener::$generatorDirName,
            $size,
            $mode,
            $divider,
            $hash,
            $ext
        );
    }

    /**
     * @param string $cachePath
     * @return int
     *
     * @see bdImage_Helper_File::getCachePath()
     */
    public static function getImageTypeFromCachePath($cachePath)
    {
        $outputFileExtension = XenForo_Helper_File::getFileExtension($cachePath);
        switch ($outputFileExtension) {
            case 'gif':
                return IMAGETYPE_GIF;
            case 'png':
                return IMAGETYPE_PNG;
        }

        return IMAGETYPE_JPEG;
    }

    /**
     * @param string $uri
     * @param int $size
     * @param int|string $mode
     * @param string $hash
     * @return string
     */
    public static function getCacheUrl($uri, $size, $mode, $hash)
    {
        return self::getCachePath($uri, $size, $mode, $hash, XenForo_Application::$externalDataUrl);
    }

    /**
     * @param string $uri
     * @param bool $pathPrefix
     * @return string
     */
    public static function getOriginalCachePath($uri, $pathPrefix = false)
    {
        if ($pathPrefix === false) {
            $pathPrefix = XenForo_Helper_File::getInternalDataPath();
        }

        return sprintf(
            '%s/%s/cache/%s/%s.orig',
            $pathPrefix,
            bdImage_Listener::$generatorDirName,
            gmdate('Ym'),
            md5($uri)
        );
    }

    /**
     * @param string|array $imageData
     * @return string
     */
    public static function getImageCachedPathOrUrl($imageData)
    {
        $url = bdImage_Helper_Data::get($imageData, bdImage_Helper_Data::IMAGE_URL);
        if (!is_string($url) || strlen($url) < 0) {
            return '';
        }

        if (self::existsAndNotEmpty($url)) {
            return $url;
        }

        /** @var XenForo_Application $application */
        $application = XenForo_Application::getInstance();
        $path = $application->getRootDir() . '/' . $url;
        if (self::existsAndNotEmpty($path)) {
            return realpath($path);
        }

        $originalCachePath = self::getOriginalCachePath($url);
        if (self::existsAndNotEmpty($originalCachePath)) {
            return $originalCachePath;
        } else {
            return $url;
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function existsAndNotEmpty($path)
    {
        if (!is_string($path) || strlen($path) === 0) {
            // invalid path
            return false;
        }

        $pathStat = @stat($path);
        if (!is_array($pathStat)
            || !isset($pathStat['size'])
        ) {
            // bad file
            return false;
        }

        return $pathStat['size'] > 0;
    }

    /**
     * @param string $path
     * @param bool $checkFileSize
     * @param bool $checkFileMtime
     * @param int $hashLength
     * @return null|string
     */
    public static function getCacheFileHash($path, $checkFileSize = true, $checkFileMtime = true, $hashLength = 6)
    {
        if (!is_string($path) || strlen($path) === 0) {
            // invalid path
            return null;
        }

        $pathStat = @stat($path);
        if (!is_array($pathStat)
            || !isset($pathStat['size'])
            || !isset($pathStat['mtime'])
        ) {
            // bad file
            return null;
        }

        if ($checkFileSize && $pathStat['size'] < bdImage_Helper_File::THUMBNAIL_ERROR_FILE_LENGTH) {
            // file too small
            return null;
        }

        if ($checkFileMtime) {
            $fileTtlDays = bdImage_Option::get('fileTtlDays');
            if ($fileTtlDays > 0 && $pathStat['mtime'] < XenForo_Application::$time - $fileTtlDays * 86400) {
                // file too old
                return null;
            }
        }

        return substr(md5($pathStat['size'] . $pathStat['mtime']), 0, $hashLength);
    }

    /**
     * @param string $path
     * @return array
     */
    public static function getThumbnailError($path)
    {
        $data = array(
            'version' => 0,
            'attemptCount' => 0,
            'latestAttempt' => 0,
        );

        $fh = fopen($path, 'rb');
        if (empty($fh)) {
            return $data;
        }

        $bytes = fread($fh, self::THUMBNAIL_ERROR_FILE_LENGTH);
        fclose($fh);

        if (strlen($bytes) < self::THUMBNAIL_ERROR_FILE_LENGTH
            || substr($bytes, 0, 3) !== self::THUMBNAIL_ERROR_MAGIC_BYTES
        ) {
            return $data;
        }

        $raw = unpack(self::THUMBNAIL_ERROR_FORMAT_UNPACK, substr($bytes, 3));

        if (isset($raw['v'])) {
            $data['version'] = $raw['v'];
        }

        if (isset($raw['ac'])) {
            $data['attemptCount'] = $raw['ac'];
        }

        if (isset($raw['la'])) {
            $data['latestAttempt'] = $raw['la'];
        }

        return $data;
    }

    /**
     * @param string $path
     * @param array $data
     * @return int
     */
    public static function saveThumbnailError($path, array $data = array())
    {
        $attemptCount = 0;
        if (isset($data['attemptCount'])) {
            $attemptCount = $data['attemptCount'];
        }

        $raw = self::THUMBNAIL_ERROR_MAGIC_BYTES
            . pack(
                self::THUMBNAIL_ERROR_FORMAT_PACK,
                self::THUMBNAIL_ERROR_VERSION,
                $attemptCount + 1,
                XenForo_Application::$time
            );

        XenForo_Helper_File::createDirectory(dirname($path), true);
        $written = file_put_contents($path, $raw);
        if ($written !== false) {
            XenForo_Helper_File::makeWritableByFtpUser($path);
        }

        return $written;
    }
}
