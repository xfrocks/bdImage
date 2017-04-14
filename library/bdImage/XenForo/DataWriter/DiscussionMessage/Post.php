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

    public function bdImage_extractImage($getAll = false)
    {
        $bbCode = $this->get('message');
        $contentData = array(
            'contentType' => 'post',
            'contentId' => $this->get('post_id'),
            'attachmentHash' => $this->getExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_ATTACHMENT_HASH),
            'allAttachments' => $getAll || !!bdImage_Option::get('allAttachments'),
        );

        if ($getAll) {
            return bdImage_Helper_BbCode::extractImages($bbCode, $contentData, $this);
        } else {
            return bdImage_Helper_BbCode::extractImage($bbCode, $contentData, $this);
        }
    }

    protected function _messagePostSave()
    {
        if (bdImage_Option::get('threadAuto')
            && !$this->getOption(self::OPTION_SKIP_THREAD_AUTO)
            && $this->isChanged('message')
            && $this->get('thread_id') > 0
            && $this->get('position') == 0
        ) {
            /** @var bdImage_XenForo_DataWriter_Discussion_Thread $threadDw */
            $threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread',
                XenForo_DataWriter::ERROR_SILENT);
            $threadDw->setExistingData($this->get('thread_id'));
            if ($this->get('post_id') == $threadDw->get('first_post_id')) {
                $existingImage = $threadDw->bdImage_getThreadImage();

                if (empty($existingImage['_locked'])) {
                    $image = $this->bdImage_extractImage();
                    $threadDw->bdImage_setThreadImage($image);
                    $threadDw->save();
                }
            }
        }

        parent::_messagePostSave();
    }

}
