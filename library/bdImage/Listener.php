<?php

class bdImage_Listener
{
    const XENFORO_CONTROLLERPUBLIC_POST_SAVE = 'bdImage_XenForo_ControllerPublic_Post::actionSave';
    const XENFORO_CONTROLLERPUBLIC_THREAD_SAVE = 'bdImage_XenForo_ControllerPublic_Thread::actionSave';

    /**
     * @param XenForo_Dependencies_Abstract $dependencies
     * @param array $data
     *
     * @see bdImage_Option::get
     * @see bdImage_Helper_Data::get
     * @see bdImage_Integration::getImage
     * @see bdImage_Integration::buildThumbnailLink
     * @see bdImage_Integration::buildFullSizeLink
     * @see bdImage_Integration::getImageWidth
     * @see bdImage_Integration::getImageHeight
     * @see bdImage_Integration::getImgAttributes
     * @see bdImage_Template_Helper_WidgetSlider::getCssClass
     */
    public static function init_dependencies(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_Dependencies_Abstract $dependencies,
        array $data
    ) {
        define('BDIMAGE_IS_WORKING', 1);

        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_getOption')]
            = array('bdImage_Option', 'get');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_getData')]
            = array('bdImage_Helper_Data', 'get');

        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_image')]
            = array('bdImage_Integration', 'getImage');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_safeUrl')]
            = array('bdImage_Integration', 'getImage'); // kept for compatibility reason
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_filename')] = 'basename';
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_thumbnail')]
            = array('bdImage_Integration', 'buildThumbnailLink');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_fullSize')]
            = array('bdImage_Integration', 'buildFullSizeLink');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_width')]
            = array('bdImage_Integration', 'getImageWidth');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_height')]
            = array('bdImage_Integration', 'getImageHeight');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_imgAttribs')]
            = array('bdImage_Integration', 'getImgAttributes');
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdImage_widget_slider_cssClass')]
            = array('bdImage_Template_Helper_WidgetSlider', 'getCssClass');

        $config = XenForo_Application::getConfig();
        $generatorDirName = $config->get(bdImage_Integration::CONFIG_GENERATOR_DIR_NAME);
        if (is_string($generatorDirName)
            && strlen($generatorDirName) > 0
        ) {
            bdImage_Integration::$generatorDirName = $generatorDirName;
        }

        bdImage_ShippableHelper_Updater::onInitDependencies($dependencies);
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
        if ($class === 'XenForo_Model_Thread') {
            $extend[] = 'bdImage_XenForo_Model_Thread';
        }
    }
}
