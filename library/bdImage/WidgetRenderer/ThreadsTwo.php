<?php

class bdImage_WidgetRenderer_ThreadsTwo extends WidgetFramework_WidgetRenderer_Threads
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

        if (!empty($widget['options']['feature_body'])) {
            $widget['options']['layout'] = 'full';
        }

        $response = parent::_render($widget, $positionCode, $params, $renderTemplateObject);

        $threadModel->bdImage_addThreadCondition(false);

        return $response;
    }

}
