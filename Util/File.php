<?php

namespace Xfrocks\Image\Util;

use Xfrocks\Image\Listener;

class File
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
     * @see \Xfrocks\Image\Util\File::getImageTypeFromCachePath()
     */
    public static function getCachePath($uri, $size, $mode, $hash, $pathPrefix = false)
    {
        if ($pathPrefix === false) {
            $pathPrefix = \XF::config('externalDataPath');
        }

        $uriExt = \XF\Util\File::getFileExtension($uri);
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
            Listener::$generatorDirName,
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
     * @see \Xfrocks\Image\Util\File::getCachePath()
     */
    public static function getImageTypeFromCachePath($cachePath)
    {
        $outputFileExtension = \XF\Util\File::getFileExtension($cachePath);
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
        return self::getCachePath($uri, $size, $mode, $hash, \XF::config('externalDataUrl'));
    }

    /**
     * @param string $uri
     * @param bool $pathPrefix
     * @return string
     */
    public static function getOriginalCachePath($uri, $pathPrefix = false)
    {
        if ($pathPrefix === false) {
            $pathPrefix = \XF::config('internalDataPath');
        }

        return sprintf(
            '%s/%s/cache/%s/%s.orig',
            $pathPrefix,
            Listener::$generatorDirName,
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
        $url = Data::get($imageData, Data::IMAGE_URL);
        if (!is_string($url) || strlen($url) < 0) {
            return '';
        }

        if (self::existsAndNotEmpty($url)) {
            return $url;
        }

        $path = \XF::getRootDirectory() . '/' . $url;
        if (self::existsAndNotEmpty($path)) {
            $path = realpath($path);
            if (!$path) {
                return '';
            }

            return $path;
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

        return \XF::fs()->getSize($path) > 0;
    }

    /**
     * @param string $path
     * @param bool $checkFileSize
     * @param bool $checkFileMtime
     * @param int $hashLength
     * @return null|array
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

        if ($checkFileSize && $pathStat['size'] < File::THUMBNAIL_ERROR_FILE_LENGTH) {
            // file too small
            return null;
        }

        if ($checkFileMtime) {
            $fileTtlDays = \XF::app()->options()->bdImage_fileTtlDays;
            if ($fileTtlDays > 0 && $pathStat['mtime'] < \XF::$time - $fileTtlDays * 86400) {
                // file too old
                return null;
            }
        }

        $hash = substr(md5($pathStat['size'] . $pathStat['mtime']), 0, $hashLength);

        if (\XF::$debugMode) {
            $hash = sprintf('s%d-t%d-h%s', $pathStat['size'], $pathStat['mtime'], $hash);
        }

        return array($pathStat['size'], $pathStat['mtime'], $hash);
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

        if ($bytes === false) {
            throw new \InvalidArgumentException('Cannot read thumbnail data. Bytes are false.');
        }

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
     * @return int|false
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
                \XF::$time
            );

        \XF\Util\File::createDirectory(dirname($path), true);
        $written = file_put_contents($path, $raw);
        if ($written !== false) {
            \XF\Util\File::makeWritableByFtpUser($path);
        }

        return $written;
    }
}
