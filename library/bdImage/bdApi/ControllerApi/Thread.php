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

    public function actionPostImage()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

        $post = $ftpHelper->getPostOrError($thread['first_post_id']);
        if (!$this->_getPostModel()->canEditPost($post, $thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        /** @var bdImage_ControllerHelper_Picker $picker */
        $picker = $this->getHelper('bdImage_ControllerHelper_Picker');
        $pickedImage = $picker->getPickedData(bdImage_ControllerHelper_Picker::EMPTY_PREFIX);

        if (!is_string($pickedImage)) {
            return $this->responseError(new Xenforo_Phrase('bdimage_image_not_found'), 400);
        }

        bdImage_Helper_Thread::saveThreadImage($thread, $pickedImage);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }
}
