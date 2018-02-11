<?php

class bdImage_bdApi_Extend_Model_Thread extends XFCP_bdImage_bdApi_Extend_Model_Thread
{
    public function prepareApiDataForThread(array $thread, array $forum, array $firstPost)
    {
        $data = call_user_func(array('parent', 'prepareApiDataForThread'), $thread, $forum, $firstPost);

        $imageData = bdImage_Helper_Template::getImageData('', $thread);
        if (empty($imageData)) {
            return $data;
        }

        $unpacked = bdImage_Helper_Data::unpack($imageData);
        $imageUrl = bdImage_Integration::getOriginalUrl($unpacked);
        if (empty($imageUrl)) {
            return $data;
        }

        $thumbnailWidth = $thumbnailHeight = intval(XenForo_Application::getOptions()->get('attachmentThumbnailDimensions'));
        if (!empty($_SERVER[bdImage_Listener::HTTP_API_THREAD_THUMBNAIL_WIDTH])) {
            // include headers `Api-Thread-Thumbnail-Width`
            // and `Api-Thread-Thumbnail-Height` to adjust thumbnail dimensions
            $thumbnailWidth = intval($_SERVER[bdImage_Listener::HTTP_API_THREAD_THUMBNAIL_WIDTH]);

            if (!empty($_SERVER[bdImage_Listener::HTTP_API_THREAD_THUMBNAIL_HEIGHT])) {
                $thumbnailHeight = $_SERVER[bdImage_Listener::HTTP_API_THREAD_THUMBNAIL_HEIGHT];
                if (is_numeric($thumbnailHeight)) {
                    $thumbnailHeight = intval($thumbnailHeight);
                }
            } else {
                $thumbnailHeight = $thumbnailWidth;
            }
        }

        if ($thumbnailWidth > 0) {
            $data['thread_thumbnail'] = array(
                'link' => new bdImage_Helper_LazyThumbnailer($unpacked, $thumbnailWidth, $thumbnailHeight)
            );

            if (is_int($thumbnailWidth) && is_int($thumbnailHeight)) {
                $data['thread_thumbnail'] += array(
                    'width' => $thumbnailWidth,
                    'height' => $thumbnailHeight,
                );
            } else {
                $data['thread_thumbnail'] += array(
                    'size' => $thumbnailWidth,
                    'mode' => $thumbnailHeight,
                );
            }
        }

        list($width, $height) = bdImage_Integration::getSize($unpacked, false);
        $data['thread_image'] = array(
            'link' => $imageUrl,
            'width' => intval($width),
            'height' => intval($height),
        );

        if (!empty($unpacked['is_cover'])) {
            $data['thread_image']['display_mode'] = 'cover';
        }

        if (empty($data['links']['image'])) {
            // legacy support
            $data['links']['image'] = $data['thread_image']['link'];
        }

        return $data;
    }
}
