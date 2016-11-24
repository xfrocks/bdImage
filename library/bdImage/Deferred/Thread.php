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

            if (empty($image)) {
                continue;
            }

            $dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
            $dw->setExistingData($thread, true);
            $dw->set('bdimage_image', $image);
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

        return bdImage_Helper_Data::packUrl($thread['tinhte_thumbnail_url'], array(
            'is_cover' => !empty($thread['tinhte_thumbnail_cover']),
            'source' => __METHOD__,
        ));
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
            'attachmentHash' => false,
            'allAttachments' => !!bdImage_Option::get('allAttachments'),
        );
        return bdImage_Helper_BbCode::extractImage($firstPost['message'], $contentData, $threadModel);
    }
}