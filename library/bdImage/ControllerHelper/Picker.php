<?php

class bdImage_ControllerHelper_Picker extends XenForo_ControllerHelper_Abstract
{
    public function getPickedData()
    {
        $uri = $this->getPickedImageUri();
        if ($uri === null) {
            return null;
        }

        if (empty($uri)) {
            return array();
        }

        $extraData = $this->_controller->getInput()->filterSingle('bdimage_extra_data', XenForo_Input::ARRAY_SIMPLE);
        $extraDataInput = new XenForo_Input($extraData);
        $extraData = $extraDataInput->filter(array(
            'is_cover' => XenForo_Input::BOOLEAN,
        ));
        $extraData['_locked'] = true;

        return bdImage_Integration::getImageFromUri($uri, $extraData);
    }

    public function getPickedImageUri()
    {
        $input = $this->_controller->getInput()->filter(array(
            'bdimage_picker' => XenForo_Input::UINT,
            'bdimage_image' => XenForo_Input::STRING,
            'bdimage_other' => XenForo_Input::STRING,
        ));

        if (empty($input['bdimage_picker'])) {
            return null;
        }

        if ($input['bdimage_image'] == 'other') {
            return $input['bdimage_other'];
        } elseif (!empty($input['bdimage_image'])) {
            return bdImage_Integration::getAccessibleUri($input['bdimage_image']);
        } else {
            return '';
        }
    }

}
