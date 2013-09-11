<?php

class bdImage_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer_Threads
{
	protected function _getConfiguration()
	{
		$config = parent::_getConfiguration();

		$config['name'] = '[bd] Image: Thread Images';
		$config['options'] += array(
				'thumbnail_width' => XenForo_Input::UINT,
				'thumbnail_height' => XenForo_Input::UINT,
				'gap' => XenForo_Input::UINT,
		);

		return $config;
	}

	protected function _getOptionsTemplate()
	{
		return 'bdimage_widget_options_threads';
	}
	
	protected function _validateOptionValue($optionKey, &$optionValue) {
		if ('thumbnail_width' == $optionKey) {
			if (empty($optionValue)) $optionValue = 100;
		} elseif ('thumbnail_height' == $optionKey) {
			if (empty($optionValue)) $optionValue = 100;
		} elseif ('gap' == $optionKey) {
			if (empty($optionValue)) $optionValue = 10;
		}
	
		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'bdimage_widget_threads';
	}
}