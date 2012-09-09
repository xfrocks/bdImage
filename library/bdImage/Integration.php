<?php

class bdImage_Integration
{
	public static function getBbCodeImage($bbCode, XenForo_DataWriter $dw = null, array $contentData = null)
	{
		$formatter = XenForo_BbCode_Formatter_Base::create('bdImage_BbCode_Formatter_Collector');
		if (!empty($contentData))
		{
			$formatter->setContentData($contentData);
		}
		if (!empty($dw))
		{
			$formatter->setDataWriter($dw);
		}
		
		$parser = new XenForo_BbCode_Parser($formatter);
		
		$result = $parser->render($bbCode);
		
		$imageUrls = $formatter->getImageUrls();
		
		if (empty($imageUrls))
		{
			return false;
		}
		
		return array_shift($imageUrls);
	}
	
	public static function buildThumbnailLink($imageUrl, $size)
	{
		// check for thumbnail.php file to make sure it exists
		$path = sprintf('%s/bdImage/thumbnail.php',
			XenForo_Helper_File::getExternalDataPath()
		);
		if (!file_exists($path))
		{
			XenForo_Helper_File::createDirectory(dirname($path), true);
			copy(dirname(__FILE__) . '/thumbnail.php', $path);
			XenForo_Helper_File::makeWritableByFtpUser($path);
		}
		
		return sprintf('%s/bdImage/thumbnail.php?url=%s&size=%d&hash=%s',
			XenForo_Application::$externalDataUrl,
			rawurlencode($imageUrl),
			intval($size),
			self::computeHash($imageUrl, $size)
		);
	}
	
	public static function computeHash($imageUrl, $size)
	{
		return md5(md5($imageUrl) . intval($size) . XenForo_Application::getConfig()->get('globalSalt'));
	}
}