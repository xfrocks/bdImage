<?php

class bdImage_Helper_Data
{
    const IMAGE_URL = 'url';
    const IMAGE_WIDTH = 'width';
    const IMAGE_HEIGHT = 'height';
    const SECONDARY_IMAGES = 'secondary';

    /**
     * @param string $rawData
     * @param string|array $subKey
     * @return mixed|null
     */
    public static function get($rawData, $subKey)
    {
        if (is_array($subKey)) {
            if (count($subKey) === 1) {
                $subKey = reset($subKey);
            } else {
                $subKey = '';
            }
        }

        $data = self::unpack($rawData);

        if (isset($data[$subKey])) {
            return $data[$subKey];
        } else {
            return null;
        }
    }

    /**
     * @param string $url
     * @param array $extraData
     * @return string
     */
    public static function packUrl($url, array $extraData = array())
    {
        list($imageWidth, $imageHeight) = bdImage_Helper_Image::getSize($url);
        return self::pack($url, $imageWidth, $imageHeight, $extraData);
    }

    /**
     * @param string $rawData
     * @param string $secondaryKey
     * @param string $secondaryUrl
     * @param array $secondaryExtraData
     * @return string
     */
    public static function packSecondary($rawData, $secondaryKey, $secondaryUrl, array $secondaryExtraData = array())
    {
        $data = self::unpack($rawData);
        $data[self::SECONDARY_IMAGES][$secondaryKey] = self::packUrl($secondaryUrl, $secondaryExtraData);
        return self::_packArray($data);
    }

    /**
     * @param string $url
     * @param int $width
     * @param int $height
     * @param array $extraData
     * @return string
     */
    public static function pack($url, $width, $height, array $extraData = array())
    {
        $data = array(self::IMAGE_URL => $url);

        if ($width > 0) {
            $data[self::IMAGE_WIDTH] = $width;
        }

        if ($height > 0) {
            $data[self::IMAGE_HEIGHT] = $height;
        }

        $data += $extraData;

        return self::_packArray($data);
    }

    /**
     * @param string $rawData
     * @return array
     */
    public static function unpack($rawData)
    {
        if (is_array($rawData)) {
            $data = $rawData;
        } else {
            $data = @json_decode($rawData, true);
        }
        $result = array();

        if (!empty($data)) {
            $result = $data;

            if (!isset($result[self::IMAGE_URL])) {
                // make sure `url` is always available (even if it's empty)
                $result[self::IMAGE_URL] = '';
            }
        } else {
            // it looks like the raw data is just the image url
            // may be from an earlier version?
            $result[self::IMAGE_URL] = strval($rawData);
        }

        return $result;
    }

    /**
     * @param string $imageUrl
     * @param int $size
     * @param int|string $mode
     * @return string
     */
    public static function computeHash($imageUrl, $size, $mode)
    {
        return md5(md5(strval($imageUrl)) .
            intval($size) . $mode .
            XenForo_Application::getConfig()->get('globalSalt'));
    }

    /**
     * @param array $data
     * @return string
     */
    protected static function _packArray(array $data)
    {
        if (count($data) === 0) {
            return '';
        }

        if (count($data) === 1 && isset($data[self::IMAGE_URL])) {
            return utf8_trim($data[self::IMAGE_URL]);
        }

        return json_encode($data);
    }
}