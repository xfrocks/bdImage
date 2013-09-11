<?php

class bdImage_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdImage_XenForo_DataWriter_DiscussionMessage_Post
{
	const OPTION_SKIP_UPDATING_THREAD_IMAGE = 'bdImage_skipUpdatingThreadImage';
	
	protected function _getDefaultOptions()
	{
		$options = parent::_getDefaultOptions();
		
		$options[self::OPTION_SKIP_UPDATING_THREAD_IMAGE] = false;
		
		return $options;
	}
	
	public function bdImage_getImage()
	{
		$contentData = array(
			'contentType' => 'post',
			'contentId' => $this->get('post_id'),
			'attachmentHash' => $this->getExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_ATTACHMENT_HASH),
		);
		
		return bdImage_Integration::getBbCodeImage($this->get('message'), $this, $contentData);
	}
	
	protected function _messagePostSave()
	{
		$optionSkip = $this->getOption(self::OPTION_SKIP_UPDATING_THREAD_IMAGE);
		if ($this->isChanged('message') AND $this->get('position') == 0 AND empty($optionSkip))
		{
			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			$threadDw->setExistingData($this->get('thread_id'));
			if ($this->get('post_id') == $threadDw->get('first_post_id'))
			{
				$threadDw->set('bdimage_image', $this->bdImage_getImage());
				$threadDw->save();
			}
		}
		
		return parent::_messagePostSave();
	}
}