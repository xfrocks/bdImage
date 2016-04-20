<?php

class bdImage_ControllerHelper_Picker extends XenForo_ControllerHelper_Abstract
{
    public function getPickedImage()
    {
        $input = $this->_controller->getInput()->filter(array(
            'bdimage_picker' => XenForo_Input::UINT,
            'bdimage_current_image' => XenForo_Input::STRING,
            'bdimage_image' => XenForo_Input::STRING,
            'bdimage_other' => XenForo_Input::STRING,
        ));

        if (empty($input['bdimage_picker'])) {
            return false;
        }

        if ($input['bdimage_image'] == 'other') {
            $input['bdimage_image'] = $input['bdimage_other'];
        }

        if (!empty($input['bdimage_current_image'])
            && $input['bdimage_current_image'] == $input['bdimage_image']
        ) {
            return false;
        }

        if (!empty($input['bdimage_image'])) {
            return bdImage_Integration::getAccessibleUri($input['bdimage_image']);
        } else {
            return '';
        }
    }

}
