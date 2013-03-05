<?php

class bdImage_XenForo_DataWriter_Discussion_Thread extends XFCP_bdImage_XenForo_DataWriter_Discussion_Thread
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		
		$fields['xf_thread']['bdimage_image'] = array('type' => XenForo_DataWriter::TYPE_STRING, 'default' => '');
		
		return $fields;
	}
	
	protected function _discussionPreSave()
	{
		if ($this->_firstMessageDw)
		{
			$image = $this->_firstMessageDw->bdImage_getImage();
			$this->set('bdimage_image', $image);
			
			// tell the post data writer not to update the thread again
			$this->_firstMessageDw->setOption(bdImage_XenForo_DataWriter_DiscussionMessage_Post::OPTION_SKIP_UPDATING_THREAD_IMAGE, true);
		}
		
		return parent::_discussionPreSave();
	}
}