<?php

class bdImage_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdImage_XenForo_DataWriter_DiscussionMessage_Post
{
    const OPTION_SKIP_THREAD_AUTO = 'bdImage_skipUpdatingThreadImage';

    protected function _getDefaultOptions()
    {
        $options = parent::_getDefaultOptions();

        $options[self::OPTION_SKIP_THREAD_AUTO] = false;

        return $options;
    }

    public function bdImage_getImage()
    {
        $contentData = array(
            'contentType' => 'post',
            'contentId' => $this->get('post_id'),
            'attachmentHash' => $this->getExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_ATTACHMENT_HASH),
            'allAttachments' => !!bdImage_Option::get('allAttachments'),
        );

        return bdImage_Helper_BbCode::extractImage($this->get('message'), $contentData, $this);
    }

    protected function _messagePostSave()
    {
        if (bdImage_Option::get('threadAuto')
            && !$this->getOption(self::OPTION_SKIP_THREAD_AUTO)
            && $this->isChanged('message')
            && $this->get('thread_id') > 0
            && $this->get('position') == 0
        ) {
            $threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread',
                XenForo_DataWriter::ERROR_SILENT);
            $threadDw->setExistingData($this->get('thread_id'));
            if ($this->get('post_id') == $threadDw->get('first_post_id')) {
                $imageData = bdImage_Helper_Data::unpack($threadDw->get('bdimage_image'));

                if (empty($imageData['_locked'])) {
                    $threadDw->set('bdimage_image', $this->bdImage_getImage());
                    $threadDw->save();
                }
            }
        }

        parent::_messagePostSave();
    }

}
