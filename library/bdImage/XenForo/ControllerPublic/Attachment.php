<?php

class bdImage_XenForo_ControllerPublic_Attachment extends XFCP_bdImage_XenForo_ControllerPublic_Attachment
{
	public function actionImage()
	{
		return $this->responseReroute('XenForo_ControllerPublic_Attachment', 'index');
	}

}
