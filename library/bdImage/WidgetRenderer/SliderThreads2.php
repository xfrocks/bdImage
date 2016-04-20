<?php

class bdImage_WidgetRenderer_SliderThreads2 extends WidgetFramework_WidgetRenderer_Threads
{
    protected function _getConfiguration()
    {
        $config = parent::_getConfiguration();

        $config['name'] = '[bd] Image: Thread Images bxSlider';
        $config['options'] += array(
            'thumbnail_width' => XenForo_Input::UINT,
            'thumbnail_height' => XenForo_Input::UINT,
            'title' => XenForo_Input::UINT,

            'auto' => XenForo_Input::UINT,
            'pager' => XenForo_Input::UINT,
        );

        $config['useWrapper'] = false;

        return $config;
    }

    protected function _getOptionsTemplate()
    {
        return 'bdimage_widget_options_slider_threads_2';
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if (empty($optionValue)) {
            switch ($optionKey) {
                case 'thumbnail_width':
                    $optionValue = 400;
                    break;
                case 'thumbnail_height':
                    $optionValue = 300;
                    break;
                case 'title':
                    $optionValue = 50;
                    break;
                case 'auto':
                    $optionValue = 0;
                    break;
                case 'pager':
                    if (strval($optionValue) === '') {
                        $optionValue = 1;
                    }
                    break;
            }
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'bdimage_widget_slider_threads_2';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $core = WidgetFramework_Core::getInstance();

        /* @var $threadModel bdImage_XenForo_Model_Thread */
        $threadModel = $core->getModelFromCache('XenForo_Model_Thread');
        $threadModel->bdImage_addThreadCondition(true);

        $response = parent::_render($widget, $positionCode, $params, $renderTemplateObject);

        $threadModel->bdImage_addThreadCondition(false);

        return $response;
    }

}
