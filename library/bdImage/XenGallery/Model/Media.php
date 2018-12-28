<?php

class bdImage_XenGallery_Model_Media extends XFCP_bdImage_XenGallery_Model_Media
{
    public function getMediaThumbnailUrl(array $data)
    {
        if (bdImage_Option::get('takeOverAttachThumbnail')) {
            if (isset($data['media_type']) && $data['media_type'] === 'image_upload') {
                if (isset($data['attachment_id'])) {
                    $imageData = XenForo_Link::buildPublicLink('full:attachments', $data);
                } else {
                    $imageData = $this->getAttachmentDataFilePath($data);
                }

                $options = XenForo_Application::getOptions();
                $size = $options->xengalleryThumbnailDimension['width'];
                $mode = $options->xengalleryThumbnailDimension['height'];

                // extra `#` is required to work around buggy url builder by XenGallery
                return bdImage_Integration::buildThumbnailLink($imageData, $size, $mode) . '#';
            }
        }

        return parent::getMediaThumbnailUrl($data);
    }
}
