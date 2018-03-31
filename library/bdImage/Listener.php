<?php

class bdImage_Listener
{
    const API_GLOBALS_SECONDARY_KEY = 'bdImage_bdApi_Extend_Model_Thread::prepareApiDataForThread::secondaryKey';

    const CONFIG_EXTERNAL_DATA_URLS = 'bdImage_externalDataUrls';
    public static $externalDataUrls = array();

    const CONFIG_GENERATOR_DIR_NAME = 'bdImage_generatorDirName';

    /**
     * This directory should be watched and remove files via cron. Something like these:
     * `find ./data/bdImage/cache -type f -iname '*.jpg' -atime +90 -exec rm {} \;`
     *
     * @var string
     */
    public static $generatorDirName = 'bdImage';

    const CONFIG_IMAGE_QUALITY = 'bdImage_imageQuality';
    public static $imageQuality = 66;

    const CONFIG_PHP_URL = 'bdImage_phpUrl';
    public static $phpUrl = null;

    const CONFIG_SKIP_CACHE_CHECK = 'bdImage_skipCacheCheck';

    /**
     * Useful to be used with $phpUrl if there is one dedicated thumbnail server.
     *
     * @var bool
     */
    public static $skipCacheCheck = false;

    const XENFORO_CONTROLLERPUBLIC_POST_SAVE = 'bdImage_XenForo_ControllerPublic_Post::actionSave';

    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        define('BDIMAGE_IS_WORKING', !empty($data['addOns']['bdImage']) ? $data['addOns']['bdImage'] : 1);

        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_thumbnail')]
            = array('bdImage_Integration', 'buildThumbnailLink');

        $config = XenForo_Application::getConfig();

        $externalDataUrls = $config->get(self::CONFIG_EXTERNAL_DATA_URLS);
        if (!empty($externalDataUrls)) {
            foreach ($externalDataUrls as $externalDataUrl => $externalDataPath) {
                self::$externalDataUrls[$externalDataUrl] = $externalDataPath;
            }
        }

        $generatorDirName = $config->get(self::CONFIG_GENERATOR_DIR_NAME);
        if (is_string($generatorDirName) && strlen($generatorDirName) > 0) {
            self::$generatorDirName = $generatorDirName;
        }

        $imageQuality = $config->get(self::CONFIG_IMAGE_QUALITY);
        if ($imageQuality > 0) {
            self::$imageQuality = intval($imageQuality);
        }

        $phpUrl = $config->get(self::CONFIG_PHP_URL);
        if (is_string($phpUrl) && strlen($phpUrl) > 0) {
            self::$phpUrl = $phpUrl;
        }

        $skipCacheCheck = $config->get(self::CONFIG_SKIP_CACHE_CHECK);
        self::$skipCacheCheck = !!$skipCacheCheck;

        if (isset($data['routesAdmin'])) {
            bdImage_ShippableHelper_Updater::onInitDependencies($dependencies);
        }
    }

    public static function widget_framework_ready(array &$renderers)
    {
        $addOns = XenForo_Application::get('addOns');
        if (!isset($addOns['widget_framework'])
            || $addOns['widget_framework'] < 2060320
        ) {
            // realistically we don't need the isset() check
            // we do need to make sure [bd] Widget Framework is at least v2.6.3 beta 20 though
            return;
        }

        $renderers[] = 'bdImage_WidgetRenderer_Threads';
        $renderers[] = 'bdImage_WidgetRenderer_ThreadsTwo';
        $renderers[] = 'bdImage_WidgetRenderer_SliderThreads';
        $renderers[] = 'bdImage_WidgetRenderer_AttachmentsGrid';
        $renderers[] = 'bdImage_WidgetRenderer_ThreadsGrid';
        $renderers[] = 'bdImage_WidgetRenderer_SliderThreads2';
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdImage_FileSums::getHashes();
    }

    public static function load_class_XenForo_ControllerPublic_Thread($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Thread') {
            $extend[] = 'bdImage_XenForo_ControllerPublic_Thread';
        }
    }

    public static function load_class_XenForo_DataWriter_Discussion_Thread($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_Discussion_Thread') {
            $extend[] = 'bdImage_XenForo_DataWriter_Discussion_Thread';
        }
    }

    public static function load_class_4f477c58235ffb475271e2521731d700($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_DiscussionMessage_Post') {
            $extend[] = 'bdImage_XenForo_DataWriter_DiscussionMessage_Post';
        }
    }

    public static function load_class_XenForo_DataWriter_Forum($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_Forum') {
            $extend[] = 'bdImage_XenForo_DataWriter_Forum';
        }
    }

    public static function load_class_XenForo_Image_Gd($class, array &$extend)
    {
        if ($class === 'XenForo_Image_Gd') {
            $extend[] = 'bdImage_XenForo_Image_Gd';
        }
    }

    public static function load_class_XenForo_Image_ImageMagick_Pecl($class, array &$extend)
    {
        if ($class === 'XenForo_Image_ImageMagick_Pecl') {
            $extend[] = 'bdImage_XenForo_Image_ImageMagick_Pecl';
        }
    }

    public static function load_class_XenForo_Model_Thread($class, array &$extend)
    {
        if ($class === 'XenForo_Model_Thread'
            && is_callable(array('XenForo_Link', 'buildApiLink'))
        ) {
            $extend[] = 'bdImage_bdApi_Extend_Model_Thread';
        }
    }

    public static function load_class_WidgetFramework_Model_Thread($class, array &$extend)
    {
        if ($class === 'WidgetFramework_Model_Thread') {
            $extend[] = 'bdImage_WidgetFramework_Model_Thread';
        }
    }

    public static function load_class_bdApi_ControllerApi_Thread($class, array &$extend)
    {
        if ($class === 'bdApi_ControllerApi_Thread') {
            $extend[] = 'bdImage_bdApi_ControllerApi_Thread';
        }
    }
}
