<?php

abstract class bdImage_WidgetRenderer_ThreadsBase extends WidgetFramework_WidgetRenderer_Threads
{
    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $core = WidgetFramework_Core::getInstance();

        /** @var bdImage_WidgetFramework_Model_Thread $wfThreadModel */
        $wfThreadModel = $core->getModelFromCache('WidgetFramework_Model_Thread');
        $wfThreadModel->bdImage_addThreadCondition(true);

        $response = parent::_render($widget, $positionCode, $params, $renderTemplateObject);

        $wfThreadModel->bdImage_addThreadCondition(false);

        return $response;
    }

}
