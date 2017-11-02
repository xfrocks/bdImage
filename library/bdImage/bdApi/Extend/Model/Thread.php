<?php

class bdImage_bdApi_Extend_Model_Thread extends XFCP_bdImage_bdApi_Extend_Model_Thread
{
    public function prepareApiDataForThread(array $thread, array $forum, array $firstPost)
    {
        $data = call_user_func(array('parent', 'prepareApiDataForThread'), $thread, $forum, $firstPost);

        $imageData = bdImage_Helper_Template::getImageData('', $thread);
        if (!empty($imageData)) {
            $unpacked = bdImage_Helper_Data::unpack($imageData);

            $thumbnailSize = intval(XenForo_Application::getOptions()->get('attachmentThumbnailDimensions'));
            if ($thumbnailSize > 0) {
                $data['thread_thumbnail'] = array(
                    'link' => new bdImage_Helper_LazyThumbnailer($unpacked, $thumbnailSize),
                    'width' => $thumbnailSize,
                    'height' => $thumbnailSize,
                );
            }

            list($width, $height) = bdImage_Integration::getSize($unpacked, false);
            $data['thread_image'] = array(
                'link' => bdImage_Integration::getOriginalUrl($unpacked),
                'width' => intval($width),
                'height' => intval($height),
            );

            if (!empty($unpacked['is_cover'])) {
                $data['thread_image']['display_mode'] = 'cover';
            }
        }

        return $data;
    }
}
