<?php

class bdImage_Helper_Template
{
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
                $imageWidth = bdImage_Integration::getImageWidth($imageData);
                $imageHeight = bdImage_Integration::getImageHeight($imageData);
                if ($imageWidth > 0 && $imageHeight > 0) {
                    $height = intval($size);
                    $width = $height / $imageHeight * $imageWidth;
                }
                break;
            case bdImage_Integration::MODE_STRETCH_HEIGHT:
                $imageWidth = bdImage_Integration::getImageWidth($imageData);
                $imageHeight = bdImage_Integration::getImageHeight($imageData);
                if ($imageWidth > 0 && $imageHeight > 0) {
                    $width = intval($size);
                    $height = $width / $imageWidth * $imageHeight;
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