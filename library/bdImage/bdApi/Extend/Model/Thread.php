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

        $primaryUnpacked = $unpacked = bdImage_Helper_Data::unpack($imageData);
        $secondaryUnpacked = null;

        $secondaryKey = '';
        if (!empty($GLOBALS[bdImage_Listener::API_GLOBALS_SECONDARY_KEY])) {
            $secondaryKey = $GLOBALS[bdImage_Listener::API_GLOBALS_SECONDARY_KEY];
        }
        if (!empty($secondaryKey)) {
            $secondaryUnpacked = $unpacked = bdImage_Helper_Data::unpack(
                bdImage_Helper_Data::unpackSecondary($unpacked, $secondaryKey)
            );
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

        $data += $this->prepareApiDataForThreadImage($unpacked, $thumbnailSize, $thumbnailMode);
        if (empty($data['links']['image']) && !empty($data['thread_image'])) {
            // legacy support
            $data['links']['image'] = $data['thread_image']['link'];
        }

        if ($secondaryUnpacked !== null) {
            $primaryKeyPrefix = 'thread_primary_';
            if (empty($data['thread_image'])) {
                // for some reason, the secondary image cannot be prepared
                // let's fallback to primary
                $primaryKeyPrefix = 'thread_';
            }

            $primaryData = $this->prepareApiDataForThreadImage(
                $primaryUnpacked,
                $thumbnailSize,
                $thumbnailMode,
                $primaryKeyPrefix
            );
            if (isset($primaryData["{$primaryKeyPrefix}image"])
                && (
                    empty($data['thread_image'])
                    || $primaryData["{$primaryKeyPrefix}image"]['link'] !== $data['thread_image']['link']
                )
            ) {
                $data += $primaryData;
            }
        }

        $data['permissions']['edit_image'] = !empty($firstPost) ? $this->_getPostModel()->canEditPost($firstPost,
            $thread, $forum) : false;
        if ($data['permissions']['edit_image']) {
            $data['permissions']['set_image_cover'] = XenForo_Visitor::getInstance()->hasPermission('general',
                'bdImage_setCover');
            $data['links']['edit_image'] = bdApi_Data_Helper_Core::safeBuildApiLink('threads/image', $thread);
        }

        return $data;
    }

    public function prepareApiDataForThreadImage(
        array $unpacked,
        $thumbnailSize,
        $thumbnailMode,
        $keyPrefix = 'thread_'
    ) {
        $data = array();

        $imageUrl = bdImage_Integration::getOriginalUrl($unpacked);
        if (empty($imageUrl)) {
            return $data;
        }

        list($width, $height) = bdImage_Integration::getSize($unpacked, false);
        $data["{$keyPrefix}image"] = array(
            'link' => $imageUrl,
            'width' => intval($width),
            'height' => intval($height),
        );

        if (!empty($unpacked['is_cover'])) {
            $data["{$keyPrefix}image"]['display_mode'] = 'cover';
        }

        if ($thumbnailSize > 0) {
            $data["{$keyPrefix}thumbnail"] = array(
                'link' => new bdImage_Helper_LazyThumbnailer($unpacked, $thumbnailSize, $thumbnailMode)
            );

            if (is_numeric($thumbnailMode)) {
                $data["{$keyPrefix}thumbnail"] += array(
                    'width' => intval($thumbnailSize),
                    'height' => intval($thumbnailMode),
                );
            } else {
                $data["{$keyPrefix}thumbnail"] += array(
                    'size' => $thumbnailSize,
                    'mode' => $thumbnailMode,
                );
            }
        }

        return $data;
    }
}
