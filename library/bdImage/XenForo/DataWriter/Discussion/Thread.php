<?php

class bdImage_XenForo_DataWriter_Discussion_Thread extends XFCP_bdImage_XenForo_DataWriter_Discussion_Thread
{
    public function rebuildDiscussionCounters($replyCount = false, $firstPostId = false, $lastPostId = false)
    {
        /** @var bdImage_XenForo_Model_Post $postModel */
        $postModel = $this->_getPostModel();
        $postModel->bdImage_setCachingPosts(true);

        parent::rebuildDiscussionCounters($replyCount, $firstPostId, $lastPostId);

        $imageData = bdImage_Integration::unpackData($this->get('bdimage_image'));

        if (bdImage_Option::get('threadAuto')
            && $this->get('first_post_id') > 0
            && empty($imageData['url'])
        ) {
            // the parent will call XenForo_Model_Post::getPostsByIds or
            // XenForo_Model_Post::getPostsInThread
            // to get the first post data, by calling
            // bdImage_XenForo_Model_Post::bdImage_setCachingPosts
            // we have enabled the caching of those methods so no extra queries are required
            // to work
            $firstPost = $postModel->bdImage_getCachedPostById($this->get('first_post_id'));

            if (!empty($firstPost)) {
                $contentData = array(
                    'contentType' => 'post',
                    'contentId' => $firstPost['post_id'],
                    'attachmentHash' => false,
                    'allAttachments' => !!bdImage_Option::get('allAttachments'),
                );
                $image = bdImage_Integration::getBbCodeImage($firstPost['message'], $contentData, $this);

                $this->set('bdimage_image', $image);
            } else {
                $this->set('bdimage_image', '');
            }
        }

        $postModel->bdImage_setCachingPosts(false);
    }

    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_thread']['bdimage_image'] = array(
            'type' => XenForo_DataWriter::TYPE_STRING,
            'default' => ''
        );

        return $fields;
    }

    protected function _discussionPreSave()
    {
        if (isset($GLOBALS[bdImage_Listener::XENFORO_CONTROLLERPUBLIC_THREAD_SAVE])) {
            $GLOBALS[bdImage_Listener::XENFORO_CONTROLLERPUBLIC_THREAD_SAVE]->bdImage_actionSave($this);
        }

        if (bdImage_Option::get('threadAuto')
            && $this->_firstMessageDw
        ) {
            /** @var bdImage_XenForo_DataWriter_DiscussionMessage_Post $firstMessageDw */
            $firstMessageDw = $this->_firstMessageDw;
            $image = $firstMessageDw->bdImage_getImage();
            $this->set('bdimage_image', $image);

            // tell the post data writer not to update the thread again
            $this->_firstMessageDw->setOption(
                bdImage_XenForo_DataWriter_DiscussionMessage_Post::OPTION_SKIP_THREAD_AUTO, true);
        }

        parent::_discussionPreSave();
    }

}
