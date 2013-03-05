<?php
class bdImage_XenForo_Model_Thread extends XFCP_bdImage_XenForo_Model_Thread
{
	protected $_lastUpdatedThreadInForumResults = array();
		
	public function getLastUpdatedThreadInForum($forumId, array $fetchOptions = array())
	{
		if (empty($fetchOptions) AND isset($this->_lastUpdatedThreadInForumResults[$forumId]))
		{
			// no fetch options and an existing result exists, use it now and save a query
			return $this->_lastUpdatedThreadInForumResults[$forumId];
		}
		
		$thread = parent::getLastUpdatedThreadInForum($forumId, $fetchOptions);
		
		$this->_lastUpdatedThreadInForumResults[$forumId] = $thread;
		
		return $thread;
	}
}