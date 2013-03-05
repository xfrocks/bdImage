<?php

class bdImage_Listener
{
	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'XenForo_DataWriter_Discussion_Thread',
			'XenForo_DataWriter_DiscussionMessage_Post',
			'XenForo_DataWriter_Forum',
			'XenForo_Model_Log',
			'XenForo_Model_Thread',
		);
		
		if (in_array($class, $classes))
		{
			$extend[] = 'bdImage_' . $class;
		}
	}
	
	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		XenForo_Template_Helper_Core::$helperCallbacks['bdimage_thumbnail'] = array('bdImage_Integration', 'buildThumbnailLink');
		XenForo_Template_Helper_Core::$helperCallbacks['bdimage_width'] = array('bdImage_Integration', 'getImageWidth');
		XenForo_Template_Helper_Core::$helperCallbacks['bdimage_height'] = array('bdImage_Integration', 'getImageHeight');
		XenForo_Template_Helper_Core::$helperCallbacks['bdimage_imgattribs'] = array('bdImage_Integration', 'getImgAttributes');
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdImage_FileSums::getHashes();
	}
}