<?php

class bdImage_bdApi_Extend_Model_Thread extends XFCP_bdImage_bdApi_Extend_Model_Thread
{
    public function prepareApiDataForThread(array $thread, array $forum, array $firstPost)
    {
        $data = call_user_func(array('parent', 'prepareApiDataForThread'), $thread, $forum, $firstPost);

        $imageData = bdImage_Helper_Template::getImageData('', $thread);
        if (!empty($imageData)) {
            if (!isset($data['thread_thumbnail'])) {
                $thumbnailSize = intval(XenForo_Application::getOptions()->get('attachmentThumbnailDimensions'));
                if ($thumbnailSize > 0) {
                    $data['thread_thumbnail'] = array(
                        'link' => new bdImage_Helper_LazyThumbnailer($imageData, $thumbnailSize),
                        'width' => $thumbnailSize,
                        'height' => $thumbnailSize,
                    );
                }
            }

            if (!isset($data['thread_image'])) {
                list($width, $height) = bdImage_Integration::getSize($imageData, false);
                $data['thread_image'] = array(
                    'link' => bdImage_Integration::getOriginalUrl($imageData),
                    'width' => intval($width),
                    'height' => intval($height),
                );
            }
        }

        return $data;
    }
}
