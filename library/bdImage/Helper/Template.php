<?php

class bdImage_Helper_Template
{
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
                return $container[$_key];
            }
        }

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

        return call_user_func_array(array('bdImage_Integration', 'buildThumbnailLink'), $buildParams);
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

        return bdImage_ShippableHelper_ImageSize::getDataUriAtSize($params['width'], $params['height']);
    }

    public static function renderAttachImageStyleAttribute($default, array $attachment)
    {
        if (empty($attachment['width'])
            || empty($attachment['height'])) {
            return '';
        }

        $percent = $attachment['height'] / $attachment['width'] * 100;
        return sprintf('padding-bottom:%.6f%%;width:%dpx', $percent, $attachment['width']);
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

        $thumbnailUrl = call_user_func_array(array('bdImage_Integration', 'buildThumbnailLink'), $buildParams);
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
        if (!($template instanceof XenForo_Template_Abstract)) {
            return '';
        }

        $template->setParams($params);

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
            case bdImage_Integration::MODE_CROP_EQUAL:
                $width = intval($size);
                $height = intval($size);
                break;
            case bdImage_Integration::MODE_STRETCH_WIDTH:
                $imageSize = bdImage_Integration::getSize($imageData, false);
                if ($imageSize !== false) {
                    $height = intval($size);
                    $width = $height / $imageSize[1] * $imageSize[0];
                }
                break;
            case bdImage_Integration::MODE_STRETCH_HEIGHT:
                $imageSize = bdImage_Integration::getSize($imageData, false);
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
