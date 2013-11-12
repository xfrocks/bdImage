<?php

class bdImage_WidgetRenderer_ThreadsGrid extends WidgetFramework_WidgetRenderer_Threads
{
	protected function _getConfiguration()
	{
		$config = parent::_getConfiguration();

		$config['name'] = '[bd] Image: Threads Grid';
		$config['options'] += array(
			'grid_columns' => XenForo_Input::UINT,
			'thumbnail_width' => XenForo_Input::UINT,
			'thumbnail_height' => XenForo_Input::UINT,
			'gap' => XenForo_Input::UINT,
		);

		return $config;
	}

	protected function _getOptionsTemplate()
	{
		return 'bdimage_widget_options_threads_grid';
	}

	protected function _validateOptionValue($optionKey, &$optionValue)
	{
		if (empty($optionValue))
		{
			switch ($optionKey)
			{
				case 'grid_rows':
					$optionValue = 5;
					break;
				case 'thumbnail_width':
				case 'thumbnail_height':
					$optionValue = 50;
					break;
				case 'gap':
					$optionValue = 5;
					break;
			}
		}

		return parent::_validateOptionValue($optionKey, $optionValue);
	}

	protected function _getRenderTemplate(array $widget, $positionCode, array $params)
	{
		return 'bdimage_widget_threads_grid';
	}

	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject)
	{
		$core = WidgetFramework_Core::getInstance();

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');
		$threadModel->bdImage_addThreadCondition(true);

		$response = parent::_render($widget, $positionCode, $params, $renderTemplateObject);

		$threadModel->bdImage_addThreadCondition(false);

		return $response;
	}

}
