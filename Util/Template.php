<?php

namespace Xfrocks\Image\Util;

use XF\Template\Templater;
use Xfrocks\Image\Integration;
use Xfrocks\Image\Uti\Thumbnail;

class Template
{
    /**
     * @param int $color
     * @return string
     */
    public static function getCoverColorFromTinhteThreadThumbnail($color)
    {
        if ($color < 2) {
            return '';
        }

        $code = $color - 2;
        $b = $code % 6;
        $code = ($code - $b) / 6;
        $g = $code % 6;
        $code = ($code - $g) / 6;
        $r = $code;
        return sprintf('rgb(%d,%d,%d)', $r * 51, $g * 51, $b * 51);
    }

    /**
     * @param string $key
     * @param array $container
     * @return string
     */
    public static function getImageData($key, array $container)
    {
        $keys = array();
        if (!empty($key)) {
            $keys[] = $key;
        } else {
            $keys = array(
                'bdimage_image',            // ours on `xf_thread`
                'bdimage_last_post_image',  // ours on `xf_forum`
                'tinhte_thumbnail_url',     // [Tinhte] Thread Thumbnail on `xf_thread`
            );
        }

        foreach ($keys as $_key) {
            if (!empty($container[$_key])) {
                if ($_key === 'tinhte_thumbnail_url') {
                    if (!empty($container['tinhte_thumbnail_cover'])) {
                        return Data::pack($container[$_key], 0, 0, [
                            'cover_color' => self::getCoverColorFromTinhteThreadThumbnail($container['tinhte_thumbnail_cover']),
                            'is_cover' => true
                        ]);
                    }
                }

                return $container[$_key];
            }
        }

        return '';
    }

    /**
     * @param string $templateOptionSubKey
     * @param array $container
     * @return string
     */
    public static function getImageDataIfTemplateOption($templateOptionSubKey, array $container)
    {
        if (!\XF::app()->options()->bdImage_template[$templateOptionSubKey]) {
            return '';
        }

        return self::getImageData('', $container);
    }

    /**
     * @param string $imageData
     * @return string
     */
    public static function getPreviewUrl($imageData)
    {
        $url = Integration::getOriginalUrl($imageData);
        $size = \XF::app()->options()->attachmentThumbnailDimensions * 2;
        $mode = Integration::MODE_STRETCH_WIDTH;
//        return Thumbnail::buildPhpLink($url, $size, $mode, array('_xfNoRedirect' => true));
        return '';
    }

    /**
     * @param string $text
     * @return string "PREFIX" if $text is "[PREFIX] Something else", full $text otherwise
     */
    public static function getTextPrefix($text)
    {
        if (preg_match('#^\[(?<prefix>.+?)\]#', $text, $matches)) {
            return $matches['prefix'];
        }

        return $text;
    }

    /**
     * @param string $imageData
     * @param array $params
     * @return string
     */
    public static function getThumbnailUrl($imageData, array $params)
    {
        $buildParams = array_values($params);
        array_unshift($buildParams, $imageData);

        return call_user_func_array(array('Xfrocks\Image\Integration', 'buildThumbnailLink'), $buildParams);
    }

    /**
     * @param string $contents
     * @param array $params with `width` and `height`
     * @return string
     */
    public static function getTransparentDataUri($contents, array $params)
    {
        if (empty($params['width']) || empty($params['height'])) {
            return $contents;
        }

//        return bdImage_ShippableHelper_ImageSize::getDataUriAtSize($params['width'], $params['height']);
    }

    public static function renderAttachImageStyleAttribute($which, array $attachment)
    {
        if (empty($attachment['width'])
            || empty($attachment['height'])) {
            return '';
        }

        if ($which === 'inner') {
            $percent = $attachment['height'] / $attachment['width'] * 100;
            return sprintf('padding-bottom:%.6f%%', $percent);
        }

        return sprintf('width:%dpx', $attachment['width']);
    }

    /**
     * @param string $html
     * @param array $params
     * @return string
     */
    public static function renderOgExtraHtml($html, array $params)
    {
        $extra = trim($params['extra']);
        if (empty($extra)) {
            return $html;
        }

        return preg_replace('#<meta[^>]+(og:image|twitter:image)[^>]+/>\s*#s', '', $html) . $extra;
    }

    /**
     * @param string $imageData
     * @param array $params
     * @return string
     */
    public static function renderImgAttributes($imageData, array $params)
    {
        $buildParams = array_values($params);
        array_unshift($buildParams, $imageData);

        $thumbnailUrl = call_user_func_array(array('Xfrocks\Image\Integration', 'buildThumbnailLink'), $buildParams);
        $attributes = call_user_func_array(array(__CLASS__, '_getImgWidthHeight'), $buildParams);

        return sprintf('src="%s"%s', $thumbnailUrl, $attributes);
    }

    /**
     * @param string $templateKey
     * @param array $params
     * @return mixed|string
     */
    public static function renderTemplateWithExtraParams($templateKey, array $params)
    {
        if (empty($templateKey)) {
            $templateKey = 'template';
        }

        if (!isset($params[$templateKey])) {
            return '';
        }

        $template = $params[$templateKey];
        unset($params[$templateKey]);
        if (!($template instanceof Templater)) {
            return '';
        }

        $template->addDefaultParams($params);

        return $template;
    }

    /**
     * @param string $imageData
     * @param int $size
     * @param int|string $mode
     * @return string
     */
    protected static function _getImgWidthHeight($imageData, $size, $mode)
    {
        $width = 0;
        $height = 0;

        switch ($mode) {
            case Integration::MODE_CROP_EQUAL:
                $width = intval($size);
                $height = intval($size);
                break;
            case Integration::MODE_STRETCH_WIDTH:
                $imageSize = Integration::getSize($imageData, false);
                if ($imageSize !== false) {
                    $height = intval($size);
                    $width = $height / $imageSize[1] * $imageSize[0];
                }
                break;
            case Integration::MODE_STRETCH_HEIGHT:
                $imageSize = Integration::getSize($imageData, false);
                if ($imageSize !== false) {
                    $width = intval($size);
                    $height = $width / $imageSize[0] * $imageSize[1];
                }
                break;
            default:
                if (is_numeric($mode)) {
                    $width = intval($size);
                    $height = intval($mode);
                }
        }

        $attributes = array();

        if ($width > 0) {
            $attributes[] = sprintf(' width="%d"', $width);
        }
        if ($height > 0) {
            $attributes[] = sprintf(' height="%d"', $height);
        }

        return implode('', $attributes);
    }
}
