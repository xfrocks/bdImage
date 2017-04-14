<?php

class bdImage_ControllerHelper_Picker extends XenForo_ControllerHelper_Abstract
{
    /**
     * @param array $existingImage
     * @return null|string
     * @throws XenForo_Exception
     */
    public function getPickedData(array $existingImage = array())
    {
        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasPermission('general', 'bdImage_usePicker')) {
            return null;
        }

        $pickedImage = $this->getPickedImage();
        if (!is_string($pickedImage)) {
            return null;
        }

        $imageUrl = bdImage_Helper_Data::get($pickedImage, 'url');
        $imageWidth = 0;
        $imageHeight = 0;
        $pickedExtraData = array();
        if ($imageUrl === $pickedImage) {
            $imageSize = bdImage_Helper_Image::getSize($imageUrl);
            if ($imageSize === false) {
                throw new XenForo_Exception(new XenForo_Phrase('bdimage_image_x_is_not_accessible',
                    array('url' => $imageUrl)), true);
            }
            list($imageWidth, $imageHeight) = $imageSize;
        } else {
            $pickedExtraData = bdImage_Helper_Data::unpack($pickedImage);
            if (isset($pickedExtraData['width'])) {
                $imageWidth = $pickedExtraData['width'];
            }
            if (isset($pickedExtraData['height'])) {
                $imageHeight = $pickedExtraData['height'];
            }
        }

        $extraDataInput = new XenForo_Input($this->_controller->getInput()->filterSingle(
            'bdimage_extra_data', XenForo_Input::ARRAY_SIMPLE));

        $extraDataFilters = array();
        if ($visitor->hasPermission('general', 'bdImage_setCover')) {
            $extraDataFilters['is_cover'] = XenForo_Input::BOOLEAN;
        }
        foreach ($extraDataFilters as $extraDataKey => $extraDataFilter) {
            if ($extraDataInput->filterSingle($extraDataKey . '_included', XenForo_Input::BOOLEAN)) {
                $pickedExtraData[$extraDataKey] = $extraDataInput->filterSingle($extraDataKey, $extraDataFilter);
            }
        }

        $extraData = array_merge($existingImage, $pickedExtraData);
        $extraData['_locked'] = true;

        return bdImage_Helper_Data::pack($imageUrl, $imageWidth, $imageHeight, $extraData);
    }

    /**
     * @return null|string
     */
    public function getPickedImage()
    {
        if (!$this->_controller->getInput()->filterSingle('bdimage_included', XenForo_Input::BOOLEAN)) {
            return null;
        }

        $input = $this->_controller->getInput()->filter(array(
            'bdimage_image' => XenForo_Input::STRING,
            'bdimage_other' => XenForo_Input::STRING,
        ));
        if ($input['bdimage_image'] === 'other') {
            return $input['bdimage_other'];
        } elseif (!empty($input['bdimage_image'])) {
            return $input['bdimage_image'];
        } else {
            return '';
        }
    }

}
