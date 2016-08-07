<?php

class bdImage_XenForo_DataWriter_Discussion_Thread extends XFCP_bdImage_XenForo_DataWriter_Discussion_Thread
{
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
