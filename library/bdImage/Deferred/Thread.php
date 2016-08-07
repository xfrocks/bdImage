<?php

class bdImage_Deferred_Thread extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        if (!bdImage_Option::get('threadAuto')) {
            return false;
        }

        $data = array_merge(array(
            'batch' => 250,
            'position' => 0
        ), $data);

        /* @var $threadModel XenForo_Model_Thread */
        $threadModel = XenForo_Model::create('XenForo_Model_Thread');
        /** @var XenForo_Model_Post $postModel */
        $postModel = $threadModel->getModelFromCache('XenForo_Model_Post');

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
            $threadRef =& $threads[$threadId];
            if (!empty($threadRef['bdimage_image'])
                || empty($threadRef['first_post_id'])
            ) {
                continue;
            }

            $firstPost = $postModel->getPostById($threadRef['first_post_id']);
            if (empty($firstPost)) {
                continue;
            }

            $contentData = array(
                'contentType' => 'post',
                'contentId' => $firstPost['post_id'],
                'attachmentHash' => false,
                'allAttachments' => !!bdImage_Option::get('allAttachments'),
            );
            $image = bdImage_Integration::getBbCodeImage($firstPost['message'], $contentData, $threadModel);
            if (empty($image)) {
                continue;
            }

            $dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
            $dw->setExistingData($threadRef, true);
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
}