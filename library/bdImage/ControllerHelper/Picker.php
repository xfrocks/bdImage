<?php

class bdImage_ControllerHelper_Picker extends XenForo_ControllerHelper_Abstract
{
    public function getPickedData()
    {
        $uri = $this->getPickedImageUrl();
        if ($uri === null) {
            return null;
        }

        $extraData = $this->_controller->getInput()->filterSingle('bdimage_extra_data', XenForo_Input::ARRAY_SIMPLE);
        $extraDataInput = new XenForo_Input($extraData);
        $extraData = $extraDataInput->filter(array(
            'is_cover' => XenForo_Input::BOOLEAN,
        ));
        $extraData['_locked'] = true;

        list($imageWidth, $imageHeight) = bdImage_Helper_Image::getSize($uri);
        return bdImage_Helper_Data::pack($uri, $imageWidth, $imageHeight, $extraData);
    }

    public function getPickedImageUrl()
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
            return $input['bdimage_image'];
        } else {
            return '';
        }
    }

}
