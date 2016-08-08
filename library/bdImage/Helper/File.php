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
     * @param string $path
     * @return bool
     */
    public static function existsAndNotEmpty($path)
    {
        return self::getImageFileSizeIfExists($path) > self::THUMBNAIL_ERROR_FILE_LENGTH;
    }

    /**
     * @param string $path
     * @return int
     */
    public static function getImageFileSizeIfExists($path)
    {
        if (!is_string($path) || strlen($path) === 0) {
            // invalid path
            return 0;
        }

        $pathStat = @stat($path);
        if ($pathStat === false
            || !isset($pathStat['size'])
        ) {
            return 0;
        }

        return $pathStat['size'];
    }

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

    public static function saveThumbnailError($path, array $data = array())
    {
        $attemptCount = 0;
        if (isset($data['attemptCount'])) {
            $attemptCount = $data['attemptCount'];
        }

        $raw = self::THUMBNAIL_ERROR_MAGIC_BYTES
            . pack(self::THUMBNAIL_ERROR_FORMAT_PACK,
                self::THUMBNAIL_ERROR_VERSION,
                $attemptCount + 1,
                XenForo_Application::$time
            );

        XenForo_Helper_File::createDirectory(dirname($path), true);
        return file_put_contents($path, $raw);
    }
}
