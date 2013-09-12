<?php

class bdImage_XenForo_Model_Thread extends XFCP_bdImage_XenForo_Model_Thread
{
	protected $_bdImage_lastUpdatedThreadInForumResults = array();
	protected $_bdImage_addThreadCondition = false;

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

	public function bdImage_addThreadCondition($enabled)
	{
		$this->_bdImage_addThreadCondition = $enabled;
	}

	public function prepareThreadConditions(array $conditions, array &$fetchOptions)
	{
		$response = parent::prepareThreadConditions($conditions, $fetchOptions);

		if ($this->_bdImage_addThreadCondition)
		{
			return $this->getConditionsForClause(array(
				$response,
				"thread.bdimage_image <> ''",
			));
		}

		return $response;
	}

}
