<?php

class bdImage_XenForo_Model_Post extends XFCP_bdImage_XenForo_Model_Post
{
	protected $_bdImage_posts = array();
	protected $_bdImage_cachingPosts = false;

	public function bdImage_setCachingPosts($enabled)
	{
		$this->_bdImage_cachingPosts = $enabled;
	}

	public function bdImage_getCachedPostById($postId)
	{
		if (isset($this->_bdImage_posts[$postId]))
		{
			return $this->_bdImage_posts[$postId];
		}
		else
		{
			// this should not happen, we should throw an exception to fix conflict with some
			// other add-on
			$exceptionMessage = 'bdImage_XenForo_Model_Post::bdImage_getCachedPostById could not find the first post. ';
			$exceptionMessage .= 'Please contact pony at xfrocks dot com. ';
			$exceptionMessage .= var_export(array(
				'postId' => $postId,
				'posts' => array_keys($this->_bdImage_posts)
			), true);
			throw new XenForo_Exception($exceptionMessage);
		}
	}

	public function getPostsByIds(array $postIds, array $fetchOptions = array())
	{
		$posts = parent::getPostsByids($postIds, $fetchOptions);

		if (!empty($this->_bdImage_cachingPosts))
		{
			foreach (array_keys($posts) as $postId)
			{
				$this->_bdImage_posts[$postId] = $posts[$postId];
			}
		}

		return $posts;
	}

	public function getPostsInThread($threadId, array $fetchOptions = array())
	{
		$posts = parent::getPostsInThread($threadId, $fetchOptions);

		if (!empty($this->_bdImage_cachingPosts))
		{
			foreach (array_keys($posts) as $postId)
			{
				$this->_bdImage_posts[$postId] = $posts[$postId];
			}
		}

		return $posts;
	}

}
