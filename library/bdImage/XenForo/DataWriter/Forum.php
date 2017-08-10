<?php

class bdImage_XenForo_DataWriter_Forum extends XFCP_bdImage_XenForo_DataWriter_Forum
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_forum']['bdimage_last_post_image'] = array(
            'type' => XenForo_DataWriter::TYPE_STRING,
            'default' => ''
        );

        return $fields;
    }

    public function updateCountersAfterDiscussionSave(XenForo_DataWriter_Discussion $discussionDw, $forceInsert = false)
    {
        parent::updateCountersAfterDiscussionSave($discussionDw, $forceInsert);

        $this->set('bdimage_last_post_image', '');
        if ($this->get('last_post_id') == $discussionDw->get('last_post_id')
            && bdImage_Option::get('forumLastPostImage') > 0
        ) {
            $this->set('bdimage_last_post_image', $discussionDw->get('bdimage_image'));
        }
    }

    public function updateLastPost()
    {
        parent::updateLastPost();

        $this->set('bdimage_last_post_image', '');
        if ($this->get('last_post_id') > 0
            && bdImage_Option::get('forumLastPostImage') > 0
        ) {
            /** @var XenForo_Model_Thread $threadModel */
            $threadModel = $this->getModelFromCache('XenForo_Model_Thread');
            $threads = $threadModel->getThreads(array(
                'node_id' => $this->get('node_id'),
                'last_post_date' => array('=', $this->get('last_post_date'))
            ));

            foreach ($threads as $thread) {
                if ($thread['last_post_id'] == $this->get('last_post_id')) {
                    $this->set('bdimage_last_post_image', $thread['bdimage_image']);
                }
            }
        }
    }
}
