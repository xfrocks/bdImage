<?php

class bdImage_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdImage_XenForo_DataWriter_DiscussionMessage_Post
{
	public function bdImage_getImage()
	{
		return bdImage_Integration::getBbCodeImage($this->get('message'));
	}
	
	protected function _messagePostSave()
	{
		if ($this->isChanged('message') && $this->get('position') == 0)
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