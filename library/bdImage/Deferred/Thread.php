<?php

class bdImage_Deferred_Thread extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'batch' => 250,
            'position' => 0
        ), $data);

        /* @var $threadModel XenForo_Model_Thread */
        $threadModel = XenForo_Model::create('XenForo_Model_Thread');

        $threadIds = $threadModel->getThreadIdsInRange($data['position'], $data['batch']);
        if (sizeof($threadIds) == 0) {
            return false;
        }
        $threads = $threadModel->getThreadsByIds($threadIds);

        foreach ($threadIds AS $threadId) {
            $data['position'] = $threadId;

            if (!isset($threads[$threadId])) {
                continue;
            }
            $thread = $threads[$threadId];
            if (!empty($thread['bdimage_image'])) {
                continue;
            }

            $image = null;
            if (empty($image)) {
                $image = $this->_getImageFromTinhteThreadThumbnail($thread);
            }
            if (empty($image)) {
                $image = $this->_getImageFromFirstPost($thread, $threadModel);
            }

            if (!is_string($image) || strlen($image) === 0) {
                continue;
            }

            if (XenForo_Application::debugMode() && defined('DEFERRED_CMD')) {
                echo(sprintf("Updating thread #%d with %s\n", $threadId, $image));
            }

            /** @var bdImage_XenForo_DataWriter_Discussion_Thread $dw */
            $dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
            $dw->setExistingData($thread, true);
            $dw->bdImage_setThreadImage($image);
            $dw->save();
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('threads');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }

    protected function _getImageFromTinhteThreadThumbnail(array $thread)
    {
        if (empty($thread['tinhte_thumbnail_url'])) {
            return null;
        }

        $url = $thread['tinhte_thumbnail_url'];
        $imageWidth = 0;
        $imageHeight = 0;

        $extraData = array(
            'is_cover' => !empty($thread['tinhte_thumbnail_cover']),
            'source' => __METHOD__,
        );

        if ($extraData['is_cover']) {
            list($imageWidth, $imageHeight) = bdImage_Integration::getSize($url);
            if (empty($imageWidth) || empty($imageHeight)) {
                $extraData['is_cover'] = false;
                $extraData['was_cover'] = true;
            }

            $extraData['cover_color'] = bdImage_Helper_Template::getCoverColorFromTinhteThreadThumbnail($thread['tinhte_thumbnail_cover']);
        }

        return bdImage_Helper_Data::pack($url, $imageWidth, $imageHeight, $extraData);
    }

    protected function _getImageFromFirstPost(array $thread, XenForo_Model_Thread $threadModel)
    {
        if (empty($thread['first_post_id'])) {
            return null;
        }

        /** @var XenForo_Model_Post $postModel */
        $postModel = $threadModel->getModelFromCache('XenForo_Model_Post');

        $firstPost = $postModel->getPostById($thread['first_post_id']);
        if (empty($firstPost)) {
            return null;
        }

        $contentData = array(
            'contentType' => 'post',
            'contentId' => $firstPost['post_id'],
            'contentAttachCount' => $firstPost['attach_count'],
            'attachmentHash' => false,
            'allAttachments' => !!bdImage_Option::get('allAttachments'),
        );
        return bdImage_Helper_BbCode::extractImage($firstPost['message'], $contentData, $threadModel);
    }
}
