<?php

class bdImage_WidgetRenderer_SliderThreads2 extends bdImage_WidgetRenderer_ThreadsBase
{
    protected function _getConfiguration()
    {
        $config = parent::_getConfiguration();

        $config['name'] = '[bd] Image: Thread Images bxSlider';
        $config['options'] += array(
            'thumbnail_width' => XenForo_Input::UINT,
            'thumbnail_height' => XenForo_Input::UINT,
            'title' => XenForo_Input::UINT,

            'dots' => XenForo_Input::BOOLEAN,
            'nav' => XenForo_Input::BOOLEAN,
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
        $options = array();
        $options['captions'] = !empty($widget['options']['title']);
        $options['controls'] = !empty($widget['options']['nav']);
        $options['pager'] = isset($widget['options']['dots']) ? $widget['options']['dots'] : true;
        $renderTemplateObject->setParam('bxsliderOptions', $options);

        return parent::_render($widget, $positionCode, $params, $renderTemplateObject);
    }
}
