<?php

class bdImage_XenForo_DataWriter_Discussion_Thread extends XFCP_bdImage_XenForo_DataWriter_Discussion_Thread
{
	public function rebuildDiscussionCounters($replyCount = false, $firstPostId = false, $lastPostId = false)
	{
		$postModel = $this->_getPostModel();
		$postModel->bdImage_setCachingPosts(true);

		$result = parent::rebuildDiscussionCounters($replyCount, $firstPostId, $lastPostId);

		if ($this->get('first_post_id') > 0)
		{
			// the parent will call XenForo_Model_Post::getPostsByIds or XenForo_Model_Post::getPostsInThread
			// to get the first post data, by calling bdImage_XenForo_Model_Post::bdImage_setCachingPosts
			// we have enabled the caching of those methods so no extra queries are required to work
			$firstPost = $postModel->bdImage_getCachedPostById($this->get('first_post_id'));

			// we can trigger bdImage_Integration::getBbCodeImage ourselves but it's better for compatibility purpose
			// to let the data writer do it...
			// TODO: refactor this code if it cause too much trouble
			// $postDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
			// $postDw->setExistingData($firstPost, true);
			// $image = $postDw->bdImage_getImage();

			$contentData = array(
					'contentType' => 'post',
					'contentId' => $firstPost['post_id'],
					'attachmentHash' => false,
			);
			$image = bdImage_Integration::getBbCodeImage($firstPost['message'], $this, $contentData);

			$this->set('bdimage_image', $image);
		}

		$postModel->bdImage_setCachingPosts(false);

		return $result;
	}

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