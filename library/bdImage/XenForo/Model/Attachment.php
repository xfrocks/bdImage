<?php

class bdImage_XenForo_Model_Attachment extends XFCP_bdImage_XenForo_Model_Attachment
{
    public function getAttachmentThumbnailFilePath(array $data, $externalDataPath = null)
    {
        if (bdImage_Option::get('takeOverAttachThumbnail') &&
            isset($data['thumbnail_width']) &&
            intval($data['thumbnail_width']) === 1 &&
            isset($data['thumbnail_height']) &&
            intval($data['thumbnail_height']) === 1
        ) {
            $method = __METHOD__;
            $dataId = isset($data['data_id']) ? $data['data_id'] : XenForo_Application::$time;
            return "/tmp/$method/$dataId";
        }

        return parent::getAttachmentThumbnailFilePath($data, $externalDataPath);
    }

    public function getAttachmentThumbnailUrl(array $data)
    {
        if (bdImage_Option::get('takeOverAttachThumbnail')) {
            if (isset($data['attachment_id'])) {
                $imageData = XenForo_Link::buildPublicLink('full:attachments', $data);
            } else {
                $imageData = $this->getAttachmentDataFilePath($data);
            }

            $size = XenForo_Application::getOptions()->attachmentThumbnailDimensions;
            $mode = bdImage_Integration::MODE_CROP_EQUAL;

            if (isset($data['height']) && $data['height'] > 0 && isset($data['width'])) {
                $ratio = $data['width'] / $data['height'];
                if ($ratio > 1.0) {
                    $mode = bdImage_Integration::MODE_STRETCH_HEIGHT;
                } else {
                    $mode = bdImage_Integration::MODE_STRETCH_WIDTH;
                }
            }

            return bdImage_Integration::buildThumbnailLink($imageData, $size, $mode);
        }

        return parent::getAttachmentThumbnailUrl($data);
    }

    public function insertUploadedAttachmentData(XenForo_Upload $file, $userId, array $extra = array())
    {
        if (!isset($extra['width']) &&
            !isset($extra['height']) &&
            bdImage_Option::get('takeOverAttachThumbnail') &&
            bdImage_Listener::$maxImageResizePixelCountEq1 &&
            $file->isImage()
        ) {
            $extra['width'] = $file->getImageInfoField('width');
            $extra['height'] = $file->getImageInfoField('height');
        }

        return parent::insertUploadedAttachmentData($file, $userId, $extra);
    }
}
