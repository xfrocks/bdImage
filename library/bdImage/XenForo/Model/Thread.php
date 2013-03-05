<?php
class bdImage_XenForo_Model_Thread extends XFCP_bdImage_XenForo_Model_Thread
{
	protected $_bdImage_lastUpdatedThreadInForumResults = array();
		
	public function getLastUpdatedThreadInForum($forumId, array $fetchOptions = array())
	{
		if (empty($fetchOptions) AND isset($this->_bdImage_lastUpdatedThreadInForumResults[$forumId]))
		{
			// no fetch options and an existing result exists, use it now and save a query
			return $this->_bdImage_lastUpdatedThreadInForumResults[$forumId];
		}
		
		$thread = parent::getLastUpdatedThreadInForum($forumId, $fetchOptions);
		
		$this->_bdImage_lastUpdatedThreadInForumResults[$forumId] = $thread;
		
		return $thread;
	}
}