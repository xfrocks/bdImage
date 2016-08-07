<?php

class bdImage_Helper_Data
{
    public static function get($rawData, $key)
    {
        $data = self::unpack($rawData);
        if (isset($data[$key])) {
            return $data[$key];
        } else {
            return null;
        }
    }

    public static function packUrl($url, array $extraData = array())
    {
        list($imageWidth, $imageHeight) = bdImage_Helper_Image::getSize($url);
        return self::pack($url, $imageWidth, $imageHeight, $extraData);
    }

    public static function pack($url, $width, $height, array $extraData = array())
    {
        $data = array('url' => $url);

        if ($width > 0) {
            $data['width'] = $width;
        }

        if ($height > 0) {
            $data['height'] = $height;
        }

        $data += $extraData;

        if (count($data) == 1) {
            // no need to pack data
            return $data['url'];
        } else {
            // use JSON to pack it
            return json_encode($data);
        }
    }

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

            if (!isset($result['url'])) {
                $result['url'] = false;
            }
        } else {
            // it looks like the raw data is just the image url
            // may be from an earlier version?
            $result['url'] = strval($rawData);
        }

        return $result;
    }

    public static function computeHash($imageUrl, $size, $mode)
    {
        return md5(md5($imageUrl) .
            intval($size) . $mode .
            XenForo_Application::getConfig()->get('globalSalt'));
    }
}