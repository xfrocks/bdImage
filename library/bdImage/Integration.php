<?php

class bdImage_Integration
{
	// used in getImageWidth and getImageHeight
	// to cache image sizes calculated
	protected static $_imageSizes = array();

	const MODE_CROP_EQUAL = 'ce';
	const MODE_STRETCH_WIDTH = 'sw';
	const MODE_STRETCH_HEIGHT = 'sh';

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
			return '';
		}

		$imageUrl = array_shift($imageUrls);
		if (empty($imageUrl))
		{
			return '';
		}

		list($imageWidth, $imageHeight) = self::_getImageSize($imageUrl);
		return self::_packData($imageUrl, $imageWidth, $imageHeight);
	}

	public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
	{
		// check for thumbnail.php file to make sure it exists and updated
		$origPath = sprintf('%s/thumbnail.php', dirname(__FILE__));
		$path = sprintf('%s/bdImage/thumbnail.php', XenForo_Helper_File::getExternalDataPath());
		if (!file_exists($path) OR filemtime($path) < filemtime($origPath))
		{
			XenForo_Helper_File::createDirectory(dirname($path), true);
			copy(dirname(__FILE__) . '/thumbnail.php', $path);
			XenForo_Helper_File::makeWritableByFtpUser($path);
		}

		$imageData = self::_unpackData($imageData);

		return sprintf('%s/bdImage/thumbnail.php?url=%s&size=%d&mode=%s&hash=%s',
		XenForo_Application::$externalDataUrl,
		rawurlencode($imageData['url']),
		intval($size),
		$mode,
		self::computeHash($imageData['url'], $size, $mode)
		);
	}

	public static function getImgAttributes($imageData, $size, $mode)
	{
		$width = false;
		$height = false;

		$imageData = self::_unpackData($imageData);
		if (!empty($imageData['width']) AND !empty($imageData['height']))
		{
			switch ($mode)
			{
				case self::MODE_CROP_EQUAL:
					$width = $size;
					$height = $size;
					break;
				case self::MODE_STRETCH_WIDTH:
					$height = $size;
					$width = $height / $imageData['height'] * $imageData['width'];
					break;
				case self::MODE_STRETCH_HEIGHT:
					$width = $size;
					$height = $width / $imageData['width'] * $imageData['height'];
					break;
			}
		}

		if (!empty($width) AND !empty($height))
		{
			return sprintf(' width="%d" height="%d"', $width, $height);
		}
		else
		{
			return '';
		}
	}

	public static function computeHash($imageUrl, $size, $mode)
	{
		return md5(md5($imageUrl) . intval($size) . $mode . XenForo_Application::getConfig()->get('globalSalt'));
	}


	public static function getImageWidth($imageData)
	{
		if (!isset(self::$_imageSizes[$imageData]))
		{
			self::$_imageSizes[$imageData] = self::_getImageSize($imageData, false);
		}

		if (is_array(self::$_imageSizes[$imageData]))
		{
			return self::$_imageSizes[$imageData][0];
		}

		return false;
	}

	public static function getImageHeight($imageData)
	{
		if (!isset(self::$_imageSizes[$imageData]))
		{
			self::$_imageSizes[$imageData] = self::_getImageSize($imageData, false);
		}

		if (is_array(self::$_imageSizes[$imageData]))
		{
			return self::$_imageSizes[$imageData][1];
		}

		return false;
	}

	protected static function _packData($url, $width, $height)
	{
		$data = array('url' => $url);

		if (!empty($width) AND !empty($height))
		{
			$data['width'] = $width;
			$data['height'] = $height;
		}

		if (count($data) == 1)
		{
			// no need to pack data
			return $data['url'];
		}
		else
		{
			// use JSON to pack it
			return json_encode($data);
		}
	}

	protected static function _unpackData($rawImageData)
	{
		$imageData = @json_decode($rawImageData, true);
		$result = array();

		if (!empty($imageData))
		{
			if (!empty($imageData['url']))
			{
				$result['url'] = $imageData['url'];
			}

			if (!empty($imageData['width']) AND !empty($imageData['height']))
			{
				$result['width'] = $imageData['width'];
				$result['height'] = $imageData['height'];
			}
		}
		else
		{
			// it looks like the raw image data is just the image url
			// may be from an earlier version?
			$result['url'] = $rawImageData;
		}

		return $result;
	}

	protected static function _getImageSize($imageData, $doFetch = true)
	{
		$imageData = self::_unpackData($imageData);

		$uri = $imageData['url'];
		if (!Zend_Uri::check($uri))
		{
			// the url is not a valid uri, it should be a relative path from internal_data directory
			$uri = XenForo_Helper_File::getInternalDataPath() . $imageData['url'];
		}

		$width = false;
		$height = false;
		if (!empty($imageData['width']) AND !empty($imageData['height']))
		{
			$width = $imageData['width'];
			$height = $imageData['height'];
		}

		if ((empty($width) OR empty($height)) AND $doFetch)
		{
			// no size data has been found, we have to fetch the image to obtain it
			require_once(dirname(__FILE__) . '/ThirdParties/Fastimage.php');
			$image = new FastImage($uri);
			set_time_limit(5);
			list($width, $height) = $image->getSize();
		}

		if (!empty($width) AND !empty($height))
		{
			return array($width, $height);
		}
		else
		{
			return false;
		}
	}
}