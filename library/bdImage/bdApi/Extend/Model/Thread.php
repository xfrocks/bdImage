<?php

class bdImage_bdApi_Extend_Model_Thread extends XFCP_bdImage_bdApi_Extend_Model_Thread
{
    public function prepareApiDataForThread(array $thread, array $forum, array $firstPost)
    {
        $data = call_user_func(array('parent', 'prepareApiDataForThread'), $thread, $forum, $firstPost);

        if (isset($thread['bdimage_image'])) {
            $imageData = $thread['bdimage_image'];
            if (!isset($data['thread_thumbnail'])) {
                $thumbnailSize = intval(XenForo_Application::getOptions()->get('attachmentThumbnailDimensions'));
                if ($thumbnailSize > 0) {
                    $data['thread_thumbnail'] = array(
                        'link' => bdImage_Integration::buildThumbnailLink($imageData, $thumbnailSize),
                        'width' => $thumbnailSize,
                        'height' => $thumbnailSize,
                    );
                }
            }

            if (!isset($data['thread_image'])) {
                $fullSize = bdImage_Helper_Image::getSize($imageData);
                if (is_array($fullSize)) {
                    $data['thread_image'] = array(
                        'link' => bdImage_Integration::getOriginalUrl($imageData),
                        'width' => $fullSize[0],
                        'height' => $fullSize[1],
                    );
                }
            }
        }

        return $data;
    }
}
