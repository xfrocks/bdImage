<?php

class bdImage_WidgetRenderer_ThreadsTwo extends bdImage_WidgetRenderer_ThreadsBase
{
    protected function _getConfiguration()
    {
        $config = parent::_getConfiguration();

        $config['name'] = '[bd] Image: Thread Images (2 columns)';
        $config['options'] += array(
            'feature_width' => XenForo_Input::UINT,
            'feature_height' => XenForo_Input::UINT,
            'feature_body' => XenForo_Input::UINT,
            'small_size' => XenForo_Input::UINT,
            'small_title' => XenForo_Input::UINT,
            'small_column_width' => XenForo_Input::UINT,
            'column_gap' => XenForo_Input::UINT,
            'row_gap' => XenForo_Input::UINT,
        );

        return $config;
    }

    protected function _getOptionsTemplate()
    {
        return 'bdimage_widget_options_threads_two';
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if (empty($optionValue)) {
            switch ($optionKey) {
                case 'feature_width':
                    $optionValue = 300;
                    break;
                case 'feature_height':
                    $optionValue = 200;
                    break;
                case 'small_title':
                    $optionValue = 50;
                    break;
                case 'column_gap':
                    $optionValue = 10;
                    break;
                case 'row_gap':
                    $optionValue = 3;
                    break;
            }
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'bdimage_widget_threads_two';
    }

    protected function _getLayoutOptions($widget, $positionCode, $params, $layout)
    {
        $layoutOptions = parent::_getLayoutOptions($widget, $positionCode, $params, $layout);

        if (!empty($widget['options']['feature_body'])) {
            $layoutOptions['getPosts'] = true;
        }

        return $layoutOptions;
    }


}
