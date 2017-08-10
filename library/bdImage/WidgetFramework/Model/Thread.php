<?php

class bdImage_WidgetFramework_Model_Thread extends XFCP_bdImage_WidgetFramework_Model_Thread
{
    protected $_bdImage_addThreadCondition = false;

    public function bdImage_addThreadCondition($enabled)
    {
        $this->_bdImage_addThreadCondition = $enabled;
    }

    public function prepareThreadConditions(array $conditions, array &$fetchOptions)
    {
        $response = parent::prepareThreadConditions($conditions, $fetchOptions);

        if ($this->_bdImage_addThreadCondition) {
            return $this->getConditionsForClause(array(
                $response,
                "thread.bdimage_image <> ''",
            ));
        }

        return $response;
    }
}
