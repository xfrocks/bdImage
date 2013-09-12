<?php

class bdImage_XenForo_ControllerPublic_Post extends XFCP_bdImage_XenForo_ControllerPublic_Post
{
	public function actionEdit()
	{
		$response = parent::actionEdit();

		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$params = &$response->params;

			$contentData = array(
				'contentType' => 'post',
				'contentId' => $params['post']['post_id'],
				'attachmentHash' => false,
				'allAttachments' => true,
			);
			$params['bdImage_images'] = bdImage_Integration::getBbCodeImages($params['post']['message'], $contentData, null);
		}

		return $response;
	}

	public function actionSave()
	{
		$GLOBALS[bdImage_Listener::XENFORO_CONTROLLERPUBLIC_POST_SAVE] = $this;

		return parent::actionSave();
	}

	public function bdImage_actionSave(XenForo_DataWriter_DiscussionMessage_Post $postDw)
	{
		$picker = $this->getHelper('bdImage_ControllerHelper_Picker');
		$picked = $picker->getPickedImage();

		if ($picked !== false)
		{
			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			$threadDw->setExistingData($postDw->get('thread_id'));
			
			if (!empty($picked))
			{
				$threadDw->set('bdimage_image', bdImage_Integration::getImageFromUri($picked, array('_locked' => true)));
			}
			else
			{
				$threadDw->set('bdimage_image', '');
			}
			
			$threadDw->save();
		}
	}

}
