<?php

class bdImage_bdApi_ControllerApi_Thread extends XFCP_bdImage_bdApi_ControllerApi_Thread
{
    protected function _prepareThreads(array $threads, array $forum = null)
    {
        $thumbnailConfig = bdImage_Integration::parseApiThumbnailConfig(
            $this,
            'Api-Thread-Thumbnail-Width',
            'Api-Thread-Thumbnail-Height'
        );
        if (is_array($thumbnailConfig)) {
            foreach ($threads as &$threadRef) {
                $threadRef['_bdImage_thumbnailConfig'] = $thumbnailConfig;
            }
        }

        return parent::_prepareThreads($threads, $forum);
    }
}
