<?php

class bdImage_Listener
{
	const XENFORO_CONTROLLERPUBLIC_POST_SAVE = 'bdImage_XenForo_ControllerPublic_Post::actionSave';
	const XENFORO_CONTROLLERPUBLIC_THREAD_SAVE = 'bdImage_XenForo_ControllerPublic_Thread::actionSave';

	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'XenForo_ControllerPublic_Attachment',
			'XenForo_ControllerPublic_Post',
			'XenForo_ControllerPublic_Thread',
			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_Forum',
			'XenForo_Model_Log',
			'XenForo_Model_Post',
			'XenForo_Model_Thread',
		);

		if (in_array($class, $classes))
		{
			$extend[] = 'bdImage_' . $class;
		}
	}

	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
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
	}

	public static function widget_framework_ready(array &$renderers)
	{
		$renderers[] = 'bdImage_WidgetRenderer_Threads';
		$renderers[] = 'bdImage_WidgetRenderer_SliderThreads';
	}

	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdImage_FileSums::getHashes();
	}

}
