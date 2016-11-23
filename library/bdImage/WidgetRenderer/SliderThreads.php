<?php

class bdImage_WidgetRenderer_SliderThreads extends bdImage_WidgetRenderer_ThreadsBase
{
    protected function _getConfiguration()
    {
        $config = parent::_getConfiguration();

        $config['name'] = '[bd] Image: Thread Images Carousel';
        $config['options'] += array(
            'thumbnail_width' => XenForo_Input::UINT,
            'thumbnail_height' => XenForo_Input::UINT,
            'title' => XenForo_Input::UINT,

            'gap' => XenForo_Input::UINT,
            'visible_count' => XenForo_Input::UINT,
        );

        return $config;
    }

    protected function _getOptionsTemplate()
    {
        return 'bdimage_widget_options_slider_threads';
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if (empty($optionValue)) {
            switch ($optionKey) {
                case 'thumbnail_width':
                case 'thumbnail_height':
                    $optionValue = 100;
                    break;
                case 'title':
                    $optionValue = 50;
                    break;
                case 'gap':
                    $optionValue = 10;
                    break;
                case 'visible_count':
                    $optionValue = 1;
            }
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'bdimage_widget_slider_threads';
    }
}
