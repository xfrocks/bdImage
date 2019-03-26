<?php

namespace Xfrocks\Image;

use Xfrocks\Image\Util\Data;
use Xfrocks\Image\Util\ThumbnailRules;

class Integration
{
    const MODE_CROP_EQUAL = 'ce';
    const MODE_STRETCH_WIDTH = 'sw';
    const MODE_STRETCH_HEIGHT = 'sh';

    protected static $_calculatedImageSizes = array();

    /**
     * @param string|array $imageData
     * @param int $size
     * @param string|int $mode
     * @return string thumbnail url
     */
    public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
    {
        $unpacked = Data::unpack($imageData);
        $imageUrl = $unpacked[Data::IMAGE_URL];
        if (empty($imageUrl)
            || !defined('BDIMAGE_IS_WORKING')
        ) {
            return $imageUrl;
        }

        $size = intval($size);
        if ($size === 0) {
            return '';
        }

        $imageSize = self::getSize($unpacked, false);
        if ($imageSize !== false
            && parse_url($imageUrl) !== false
        ) {
            // we have the image size information
            // try to return the image url itself if its size matches the requested thumbnail
            list($imageWidth, $imageHeight) = $imageSize;
            switch ($mode) {
                case self::MODE_STRETCH_WIDTH:
                    if ($imageHeight === $size) {
                        return $imageUrl;
                    }
                    break;
                case self::MODE_STRETCH_HEIGHT:
                    if ($imageWidth === $size) {
                        return $imageUrl;
                    }
                    break;
                default:
                    if (is_numeric($mode)) {
                        if ($imageWidth === $size
                            && $imageHeight === intval($mode)
                        ) {
                            return $imageUrl;
                        }
                    } else {
                        if ($imageWidth === $size
                            && $imageHeight === $size
                        ) {
                            return $imageUrl;
                        }
                    }
            }
        }

        $rules = ThumbnailRules::getThumbnailRules();
        if (count($rules) > 0) {
            foreach ($rules as $matcher => $kvPairs) {
                $ruleUrl = null;
                try {
                    $ruleUrl = ThumbnailRules::check($matcher, $kvPairs, $imageUrl, $size, $mode);
                } catch (\Exception $ruleException) {
                    if (\XF::$debugMode) {
                        \XF::logException($ruleException, false, 'Ignored: ');
                    }
                }
                if ($ruleUrl !== null) {
                    return $ruleUrl;
                }
            }
        }

        if (Listener::$customBuildThumbnailLink !== null
            && is_callable(Listener::$customBuildThumbnailLink)
        ) {
            $customUrl = null;
            try {
                $customUrl = call_user_func(Listener::$customBuildThumbnailLink, $imageUrl, $size, $mode);
            } catch (\Exception $customException) {
                if (\XF::$debugMode) {
                    \XF::logException($customException, false, 'Ignored: ');
                }
            }
            if ($customUrl !== null) {
                return $customUrl;
            }
        }

        return ThumbnailRules::builtIn($imageUrl, $size, $mode);
    }

    /**
     * @param string|array $imageData
     * @return string original url
     */
    public static function getOriginalUrl($imageData)
    {
        $unpacked = Data::unpack($imageData);
        $imageUrl = $unpacked[Data::IMAGE_URL];
        if (empty($imageUrl)
            || !defined('BDIMAGE_IS_WORKING')
            || substr($imageUrl, 0, 2) === '//'
            || substr($imageUrl, 0, 7) === 'http://'
            || substr($imageUrl, 0, 8) === 'https://'
        ) {
            // nothing to do here
            return $imageUrl;
        }

        $size = self::getSize($unpacked, false);
        if ($size === false) {
            // too bad, we cannot determine the size
            return $imageUrl;
        }

        return self::buildThumbnailLink($unpacked, $size[0], $size[1]);
    }

    /**
     * @param string|array $imageData
     * @param bool $doFetch
     * @return array|false
     */
    public static function getSize($imageData, $doFetch = true)
    {
        $unpacked = Data::unpack($imageData);

        $width = false;
        $height = false;

        if (isset($unpacked[Data::IMAGE_WIDTH])
            && isset($unpacked[Data::IMAGE_HEIGHT])
        ) {
            $width = $unpacked[Data::IMAGE_WIDTH];
            $height = $unpacked[Data::IMAGE_HEIGHT];
        }

        if ((empty($width) || empty($height)) && $doFetch) {
//            $cachedPathOrUrl = File::getImageCachedPathOrUrl($unpacked);
//            if (strlen($cachedPathOrUrl) > 0) {
//                if (!isset(self::$_calculatedImageSizes[$cachedPathOrUrl])) {
//                    self::$_calculatedImageSizes[$cachedPathOrUrl] = bdImage_ShippableHelper_ImageSize::calculate($cachedPathOrUrl);
//                }
//                $imageSizeRef =& self::$_calculatedImageSizes[$cachedPathOrUrl];
//
//                if (!empty($imageSizeRef['width'])) {
//                    $width = $imageSizeRef['width'];
//                }
//                if (!empty($imageSizeRef['height'])) {
//                    $height = $imageSizeRef['height'];
//                }
//            }
        }

        if (is_string($width)) {
            $width = intval($width);
        }
        if (is_string($height)) {
            $height = intval($height);
        }

        if ($width > 0 && $height > 0) {
            return array($width, $height);
        } else {
            return false;
        }
    }

    /**
     * @param string $sizeHeader
     * @param string|null $modeHeader
     * @return array|bool
     */
    public static function parseApiThumbnailConfig($sizeHeader, $modeHeader = null)
    {
//        $session = bdApi_Data_Helper_Core::safeGetSession();
//        if (empty($session)) {
//            return false;
//        }
//        $oauthClientId = $session->getOAuthClientId();
//        if (empty($oauthClientId)) {
//            return false;
//        }

        $request = \XF::app()->request();
        $size = self::_parseApiThumbnailRequestHeaderOrParam($request, $sizeHeader);
        if ($size === false) {
            return false;
        }

        $size = intval($size);
        if ($size === 0) {
            return false;
        }

        $mode = $size;
        if (is_string($modeHeader)) {
            $modeHeaderValue = self::_parseApiThumbnailRequestHeaderOrParam($request, $modeHeader);
            if ($modeHeaderValue !== false) {
                $mode = $modeHeaderValue;
            }
        }

        return array(
            'size' => $size,
            'mode' => $mode
        );
    }

    /**
     * @param \XF\Http\Request $request
     * @param string $header
     * @return string|false
     */
    protected static function _parseApiThumbnailRequestHeaderOrParam($request, $header)
    {
        $paramKey = '_bdImage' . str_replace('-', '', $header);
        $paramValue = $request->get($paramKey);
        if (is_string($paramValue)) {
            return $paramValue;
        }

        $headerValue = $request->getServer($header);
        return $headerValue;
    }
}
