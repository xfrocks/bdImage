<?php

class bdImage_Helper_File
{
    // http://cloudinary.com/blog/one_pixel_is_worth_three_thousand_words
    // image file should have more than 8 bytes
    const IMAGE_FILE_SIZE_THRESHOLD = 8;

    /**
     * @param string $path
     * @return bool
     */
    public static function existsAndNotEmpty($path)
    {
        return self::getImageFileSizeIfExists($path) > self::IMAGE_FILE_SIZE_THRESHOLD;
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
        $fh = fopen($path, 'rb');
        if (empty($fh)) {
            return null;
        }

        // current format
        // magic bytes: ERR
        // version: char
        // attempt count: char
        $bytes = fread($fh, filesize($path));
        fclose($fh);

        if (substr($bytes, 0, 3) !== 'ERR') {
            // magic bytes mismatched
            return null;
        }

        $raw = unpack('C*', substr($bytes, 3));
        $data = array('version' => $raw[1]);

        switch ($data['version']) {
            case 1:
                if (isset($raw[2])) {
                    $data['attemptCount'] = $raw[2];
                }
                break;
        }

        return $data;
    }

    public static function saveThumbnailError($path, array $data = array())
    {
        $data += array(
            'attemptCount' => 0,
        );

        $data['attemptCount']++;

        $raw = 'ERR' . pack('C*', 1, $data['attemptCount']);

        XenForo_Helper_File::createDirectory(dirname($path), true);
        return file_put_contents($path, $raw);
    }
}
