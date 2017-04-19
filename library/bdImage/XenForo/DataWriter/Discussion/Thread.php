<?php

class bdImage_XenForo_DataWriter_Discussion_Thread extends XFCP_bdImage_XenForo_DataWriter_Discussion_Thread
{
    /**
     * @return array
     */
    public function bdImage_getThreadImage()
    {
        return bdImage_Helper_Data::unpack($this->get('bdimage_image'));
    }

    /**
     * @param string $image
     * @return bool
     * @throws XenForo_Exception
     */
    public function bdImage_setThreadImage($image)
    {
        if (!is_string($image)) {
            throw new XenForo_Exception('$image must be a packed string');
        }

        $existing = $this->get('bdimage_image');
        if (!empty($existing)) {
            $image = bdImage_Helper_Data::mergeAndPack($existing, $image);
        }

        return $this->set('bdimage_image', $image);
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
            $image = $firstMessageDw->bdImage_extractImage();
            $this->bdImage_setThreadImage($image);

            // tell the post data writer not to update the thread again
            $this->_firstMessageDw->setOption(
                bdImage_XenForo_DataWriter_DiscussionMessage_Post::OPTION_SKIP_THREAD_AUTO, true);
        }

        parent::_discussionPreSave();
    }

}
