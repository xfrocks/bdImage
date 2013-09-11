<?php

class bdImage_XenForo_DataWriter_Forum extends XFCP_bdImage_XenForo_DataWriter_Forum
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		
		$fields['xf_forum']['bdimage_last_post_image'] = array('type' => XenForo_DataWriter::TYPE_STRING, 'default' => '');
		
		return $fields;
	}
	
	public function updateCountersAfterDiscussionSave(XenForo_DataWriter_Discussion $discussionDw, $forceInsert = false)
	{
		$result = parent::updateCountersAfterDiscussionSave($discussionDw, $forceInsert);
		
		if ($this->get('last_post_id') == $discussionDw->get('last_post_id'))
		{
			// the last post info has been updated with data from the thread
			// we will copy the thread's image too
			$this->set('bdimage_last_post_image', $discussionDw->get('bdimage_image')); 
		}
		
		return $result;
	}
	
	public function updateLastPost()
	{
		$result = parent::updateLastPost();
		
		if ($this->get('last_post_id') > 0)
		{
			// the parent implementation will call XenForo_Model_Thread::getLastUpdatedThreadInForum itself
			// but the result is cached so we don't make much impact here by calling it again
			$lastPost = $this->getModelFromCache('XenForo_Model_Thread')->getLastUpdatedThreadInForum($this->get('node_id'));
			
			if ($lastPost)
			{
				$this->set('bdimage_last_post_image', $lastPost['bdimage_image']);
			}
		}
	}
}