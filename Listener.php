<?php

namespace Xfrocks\Image;

class Listener
{
    const CONFIG_IMAGE_QUALITY = 'bdImage_imageQuality';
    public static $imageQuality = 66;

    const CONFIG_EXTERNAL_DATA_URLS = 'bdImage_externalDataUrls';
    public static $externalDataUrls = array();

    /**
     * Implement custom builder to use an independent image resizer,
     * below is a simplified example for https://github.com/willnorris/imageproxy
     *
     * ```
     *     $config['bdImage_customBuildThumbnailLink'] = function ($imageUrl, $size, $mode) {
     *         $options = sprintf('%dx%d', $size, $mode);
     *         switch ($mode) {
     *             case \Xfrocks\Image\Integration::MODE_STRETCH_WIDTH:
     *                 $options = sprintf('x%d', $size);
     *                 break;
     *             case \Xfrocks\Image\Integration::MODE_STRETCH_HEIGHT:
     *                 $options = sprintf('%dx', $size);
     *                 break;
     *             case \Xfrocks\Image\Integration::MODE_CROP_EQUAL:
     *                 $options = sprintf('%d', $size);
     *                 break;
     *         }
     *         return sprintf('https://imageproxy.domain.com/%s/%s', $options, $imageUrl);
     *     }
     * ```
     *
     * @var null|string|callable
     */
    public static $customBuildThumbnailLink = null;

    /**
     * This directory should be watched and remove files via cron. Something like these:
     * `find ./data/bdImage/cache -type f -iname '*.jpg' -atime +90 -exec rm {} \;`
     *
     * @var string
     */
    public static $generatorDirName = 'bdImage';

    /**
     * Useful to be used with $phpUrl if there is one dedicated thumbnail server.
     *
     * @var bool
     */
    public static $skipCacheCheck = false;

    const CONFIG_PHP_URL = 'bdImage_phpUrl';
    public static $phpUrl = null;

    const CONFIG_MAX_IMAGE_RESIZE_PIXEL_COUNT = 'bdImage_maxImageResizePixelCount';
    public static $maxImageResizePixelCountEq1 = false;
    public static $maxImageResizePixelOurs = 0;
}
