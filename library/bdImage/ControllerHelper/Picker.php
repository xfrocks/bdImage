<?php

class bdImage_ControllerHelper_Picker extends XenForo_ControllerHelper_Abstract
{
    const EMPTY_PREFIX = '';

    /**
     * @param string $prefix
     * @return null|string
     * @throws XenForo_Exception
     */
    public function getPickedData($prefix = 'bdimage_')
    {
        $visitor = XenForo_Visitor::getInstance();

        $pickedImage = $this->getPickedImage($prefix);
        if (!is_string($pickedImage)) {
            return null;
        }

        $imageUrl = bdImage_Helper_Data::get($pickedImage, bdImage_Helper_Data::IMAGE_URL);
        $imageWidth = 0;
        $imageHeight = 0;
        $extraData = array();
        if ($imageUrl === $pickedImage) {
            if (strlen($imageUrl) > 0) {
                $imageSize = bdImage_Integration::getSize($imageUrl);
                if ($imageSize === false) {
                    throw new XenForo_Exception(new XenForo_Phrase(
                        'bdimage_image_x_is_not_accessible',
                        array('url' => $imageUrl)
                    ), true);
                }
                list($imageWidth, $imageHeight) = $imageSize;
            }
        } else {
            $extraData = bdImage_Helper_Data::unpack($pickedImage);
            if (isset($extraData[bdImage_Helper_Data::IMAGE_WIDTH])) {
                $imageWidth = $extraData[bdImage_Helper_Data::IMAGE_WIDTH];
            }
            if (isset($extraData[bdImage_Helper_Data::IMAGE_HEIGHT])) {
                $imageHeight = $extraData[bdImage_Helper_Data::IMAGE_HEIGHT];
            }
        }

        $input = $this->_controller->getInput();
        if ($prefix !== bdImage_ControllerHelper_Picker::EMPTY_PREFIX) {
            $input = new XenForo_Input($input->filterSingle(
                $prefix . 'extra_data',
                XenForo_Input::ARRAY_SIMPLE
            ));
        }
        $extraDataInput = $input->filter(array(
            'is_cover' => XenForo_Input::BOOLEAN,
            'cover_color' => XenForo_Input::STRING,
            'is_cover_included' => XenForo_Input::BOOLEAN,
        ));

        if ($visitor->hasPermission('general', 'bdImage_setCover') &&
            ($prefix == bdImage_ControllerHelper_Picker::EMPTY_PREFIX || $extraDataInput['is_cover_included'])) {
            $extraData['is_cover'] = $extraDataInput['is_cover'];
            $extraData['cover_color'] = $extraDataInput['cover_color'];
        }

        if (empty($imageUrl)) {
            $extraData['is_cover'] = false;
            $extraData['cover_color'] = '';
        }

        $extraData['_locked'] = true;
        return bdImage_Helper_Data::pack($imageUrl, $imageWidth, $imageHeight, $extraData);
    }

    /**
     * @param string $prefix
     * @return null|string
     * @throws XenForo_Exception
     */
    public function getPickedImage($prefix = 'bdimage_')
    {
        if ($prefix !== bdImage_ControllerHelper_Picker::EMPTY_PREFIX &&
            !$this->_controller->getInput()->filterSingle($prefix . 'included', XenForo_Input::BOOLEAN)) {
            return null;
        }

        $input = $this->_controller->getInput()->filter(array(
            $prefix . 'image' => XenForo_Input::STRING,
            $prefix . 'other' => XenForo_Input::STRING,
        ));

        if ($input[$prefix . 'image'] === 'other') {
            return $input[$prefix . 'other'];
        } elseif (!empty($input[$prefix . 'image'])) {
            return $input[$prefix . 'image'];
        } else {
            return '';
        }
    }
}
