<?php

class bdImage_Helper_Data
{
    const IMAGE_URL = 'url';
    const IMAGE_WIDTH = 'width';
    const IMAGE_HEIGHT = 'height';
    const SECONDARY_IMAGES = 'secondary';

    /**
     * @param array|string $rawData
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
     * @param array|string $rawData
     * @param string $secondaryKey
     * @param string $secondaryData
     * @return string
     */
    public static function packSecondary($rawData, $secondaryKey, $secondaryData)
    {
        $data = array();
        if ($rawData !== '') {
            $data = self::unpack($rawData);
        }

        $data[self::SECONDARY_IMAGES][$secondaryKey] = $secondaryData;
        return self::_packArray($data);
    }

    /**
     * @param string $data,...
     * @return string
     */
    public static function mergeAndPack($data)
    {
        $args = func_get_args();
        $unpackedArgs = array_map(array(__CLASS__, '_unpackArrayOrString'), $args);
        $merged = call_user_func_array('array_merge', $unpackedArgs);
        return self::_packArray($merged);
    }

    /**
     * @param string $url
     * @param int $width
     * @param int $height
     * @param array $extraData
     * @return string
     */
    public static function pack($url, $width = 0, $height = 0, array $extraData = array())
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
     * @param array|string $rawData
     * @param string $secondaryKey
     * @return array|string
     */
    public static function unpackSecondaryOrDefault($rawData, $secondaryKey)
    {
        $data = self::unpack($rawData);
        if (!empty($data[self::SECONDARY_IMAGES][$secondaryKey])) {
            return $data[self::SECONDARY_IMAGES][$secondaryKey];
        }

        return $rawData;
    }

    /**
     * @param array|string $rawData
     * @return array
     */
    public static function unpack($rawData)
    {
        $data = self::_unpackArrayOrString($rawData);
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

    /**
     * @param array|string $rawData
     * @return array
     */
    protected static function _unpackArrayOrString($rawData)
    {
        $data = null;

        if (is_array($rawData)) {
            $data = $rawData;
        } elseif (is_string($rawData)) {
            $data = @json_decode($rawData, true);
        }

        if (!is_array($data)) {
            $data = array();
        }

        return $data;
    }
}
