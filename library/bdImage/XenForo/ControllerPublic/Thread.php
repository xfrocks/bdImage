<?php

class bdImage_XenForo_ControllerPublic_Thread extends XFCP_bdImage_XenForo_ControllerPublic_Thread
{
	public function actionEdit()
	{
		$response = parent::actionEdit();

		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$params = &$response->params;

			$firstPost = $this->getModelFromCache('XenForo_Model_Post')->getPostById($params['thread']['first_post_id']);

			$contentData = array(
				'contentType' => 'post',
				'contentId' => $firstPost['post_id'],
				'attachmentHash' => false,
				'allAttachments' => true,
			);
			$params['bdImage_images'] = bdImage_Integration::getBbCodeImages($firstPost['message'], $contentData, null);
		}

		return $response;
	}

	public function actionSave()
	{
		$GLOBALS[bdImage_Listener::XENFORO_CONTROLLERPUBLIC_THREAD_SAVE] = $this;

		return parent::actionSave();
	}

	public function bdImage_actionSave(XenForo_DataWriter_Discussion_Thread $threadDw)
	{
		$picker = $this->getHelper('bdImage_ControllerHelper_Picker');
		$picked = $picker->getPickedImage();

		if (!empty($picked))
		{
			$threadDw->set('bdimage_image', bdImage_Integration::getImageFromUri($picked, array('_locked' => true)));
		}
	}

}
