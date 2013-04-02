<?php

class bdImage_XenForo_Route_Prefix_Attachments extends XFCP_bdImage_XenForo_Route_Prefix_Attachments
{
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$link = parent::buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, $extraParams);
		
		if (empty($action) OR utf8_strtolower($action) === 'index')
		{
			// force attachment link to have the .jpg extension + absolute uri
			return new XenForo_Link(XenForo_Link::convertUriToAbsoluteUri(rtrim($link, '/') . '/image.jpg', true), false);
		}
		else 
		{
			return $link;
		}
	}
}