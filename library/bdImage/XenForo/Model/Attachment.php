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
            return '/dev/null';
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
        if (bdImage_Option::get('takeOverAttachThumbnail') &&
            intval(XenForo_Application::getConfig()->get('maxImageResizePixelCount')) === 1 &&
            $file->isImage() &&
            !isset($extra['thumbnail_width']) &&
            !isset($extra['thumbnail_height'])
        ) {
            $extra['width'] = $file->getImageInfoField('width');
            $extra['height'] = $file->getImageInfoField('height');
        }

        return parent::insertUploadedAttachmentData($file, $userId, $extra);
    }
}
