<?php

class bdImage_bdApi_Extend_Model_Thread extends XFCP_bdImage_bdApi_Extend_Model_Thread
{
    public function prepareApiDataForThread(array $thread, array $forum, array $firstPost)
    {
        $data = parent::prepareApiDataForThread($thread, $forum, $firstPost);

        $imageData = bdImage_Helper_Template::getImageData('', $thread);
        if (empty($imageData)) {
            return $data;
        }

        $unpacked = bdImage_Helper_Data::unpack($imageData);

        $secondaryKey = '';
        if (!empty($GLOBALS[bdImage_Listener::API_GLOBALS_SECONDARY_KEY])) {
            $secondaryKey = $GLOBALS[bdImage_Listener::API_GLOBALS_SECONDARY_KEY];
        }
        if (!empty($secondaryKey)) {
            $unpacked = bdImage_Helper_Data::unpackSecondaryOrDefault($unpacked, $secondaryKey);
        }

        $imageUrl = bdImage_Integration::getOriginalUrl($unpacked);
        if (empty($imageUrl)) {
            return $data;
        }

        $thumbnailSize = $thumbnailMode = intval(XenForo_Application::getOptions()->get('attachmentThumbnailDimensions'));
        if (!empty($thread['_bdImage_thumbnailConfig'])) {
            $thumbnailConfig = $thread['_bdImage_thumbnailConfig'];
            if (is_array($thumbnailConfig)) {
                if (isset($thumbnailConfig['size'])) {
                    $thumbnailSize = $thumbnailConfig['size'];
                }
                if (isset($thumbnailConfig['mode'])) {
                    $thumbnailMode = $thumbnailConfig['mode'];
                }
            }
        }

        if ($thumbnailSize > 0) {
            $data['thread_thumbnail'] = array(
                'link' => new bdImage_Helper_LazyThumbnailer($unpacked, $thumbnailSize, $thumbnailMode)
            );

            if (is_numeric($thumbnailMode)) {
                $data['thread_thumbnail'] += array(
                    'width' => intval($thumbnailSize),
                    'height' => intval($thumbnailMode),
                );
            } else {
                $data['thread_thumbnail'] += array(
                    'size' => $thumbnailSize,
                    'mode' => $thumbnailMode,
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
