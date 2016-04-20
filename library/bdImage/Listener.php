<?php

class bdImage_Listener
{
    const XENFORO_CONTROLLERPUBLIC_POST_SAVE = 'bdImage_XenForo_ControllerPublic_Post::actionSave';
    const XENFORO_CONTROLLERPUBLIC_THREAD_SAVE = 'bdImage_XenForo_ControllerPublic_Thread::actionSave';

    public static function load_class($class, array &$extend)
    {
        static $classes = array(
            'XenForo_ControllerPublic_Attachment',
            'XenForo_ControllerPublic_Thread',
            'XenForo_DataWriter_Discussion_Thread',
            'XenForo_DataWriter_DiscussionMessage_Post',
            'XenForo_DataWriter_Forum',
            'XenForo_Image_Gd',
            'XenForo_Image_ImageMagick_Pecl',
        );

        if (in_array($class, $classes)) {
            $extend[] = 'bdImage_' . $class;
        }
    }

    public static function load_class_model($class, array &$extend)
    {
        static $classes = array(
            'XenForo_Model_Post',
            'XenForo_Model_Thread',
        );

        if (in_array($class, $classes)) {
            $extend[] = 'bdImage_' . $class;
        }
    }

    public static function init_dependencies(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Dependencies_Abstract $dependencies,
        array $data
    ) {
        define('BDIMAGE_IS_WORKING', 1);

        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_getoption'] = array(
            'bdImage_Option',
            'get'
        );

        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_image'] = array(
            'bdImage_Integration',
            'getImage'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_safeurl'] = array(
            'bdImage_Integration',
            'getSafeImageUrl'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_filename'] = 'basename';
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_thumbnail'] = array(
            'bdImage_Integration',
            'buildThumbnailLink'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_width'] = array(
            'bdImage_Integration',
            'getImageWidth'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_height'] = array(
            'bdImage_Integration',
            'getImageHeight'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_imgattribs'] = array(
            'bdImage_Integration',
            'getImgAttributes'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdimage_widget_slider_cssclass'] = array(
            'bdImage_Template_Helper_WidgetSlider',
            'getCssClass'
        );

        $config = XenForo_Application::getConfig();
        $generatorDirName = $config->get(bdImage_Integration::CONFIG_GENERATOR_DIR_NAME);
        if (is_string($generatorDirName)
            && strlen($generatorDirName) > 0
        ) {
            bdImage_Integration::$generatorDirName = $generatorDirName;
        }
    }

    public static function template_hook(
        $hookName,
        &$contents,
        array $hookParams,
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Template_Abstract $template
    ) {
        if ($hookName == 'wf_widget_options_threads_layout') {
            if (strpos($hookParams['options_loaded'], 'bdImage_') === 0) {
                $contents = '';
            }
        }
    }

    public static function widget_framework_ready(array &$renderers)
    {
        $renderers[] = 'bdImage_WidgetRenderer_Threads';
        $renderers[] = 'bdImage_WidgetRenderer_ThreadsTwo';
        $renderers[] = 'bdImage_WidgetRenderer_SliderThreads';
        $renderers[] = 'bdImage_WidgetRenderer_AttachmentsGrid';
        $renderers[] = 'bdImage_WidgetRenderer_ThreadsGrid';
        $renderers[] = 'bdImage_WidgetRenderer_SliderThreads2';
    }

    public static function file_health_check(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_ControllerAdmin_Abstract $controller,
        array &$hashes
    ) {
        $hashes += bdImage_FileSums::getHashes();
    }

}
