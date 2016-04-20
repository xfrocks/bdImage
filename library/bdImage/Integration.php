<?php

class bdImage_Integration
{
	// used in getImageWidth and getImageHeight
	// to cache image sizes calculated
	protected static $_imageSizes = array();

	const MODE_CROP_EQUAL = 'ce';
	const MODE_STRETCH_WIDTH = 'sw';
	const MODE_STRETCH_HEIGHT = 'sh';

	public static function getBbCodeImages($bbCode, array $contentData = array(), $dwOrModel = null)
	{
		$formatter = XenForo_BbCode_Formatter_Base::create('bdImage_BbCode_Formatter_Collector');
		if (!empty($contentData))
		{
			$formatter->setContentData($contentData);
		}
		if (!empty($dw))
		{
			$formatter->setDwOrModel($dwOrModel);
		}

		$parser = new XenForo_BbCode_Parser($formatter);

		$result = $parser->render($bbCode);

		return $formatter->getImageUrls();
	}

	public static function getBbCodeImage($bbCode, array $contentData = array(), $dwOrModel = null)
	{
		$images = self::getBbCodeImages($bbCode, $contentData, $dwOrModel);
		if (empty($images))
		{
			return '';
		}

		$image = array_shift($images);
		if (empty($image))
		{
			return '';
		}

		list($imageWidth, $imageHeight) = self::_getImageSize($image);
		return self::_packData(self::getSafeImageUrl($image), $imageWidth, $imageHeight);
	}

	public static function getImageFromUri($uri, array $extraData = array())
	{
		list($imageWidth, $imageHeight) = self::_getImageSize($uri);
		return self::_packData(self::getSafeImageUrl($uri), $imageWidth, $imageHeight, $extraData);
	}

	public static function getSafeImageUrl($uri)
	{
		if (empty($uri))
		{
			// nothing to do here
			return $uri;
		}

		if (Zend_Uri::check($uri))
		{
			// uri, return asap
			return $uri;
		}
		else
		{
			$realpath = realpath($uri);
			$rootRealpath = realpath(XenForo_Application::getInstance()->getRootDir());

			if (substr($realpath, 0, strlen($rootRealpath)) == $rootRealpath)
			{
				// hide the root path
				return ltrim(substr($realpath, strlen($rootRealpath)), '/');
			}
			else
			{
				// unable to hide anything...
				return $uri;
			}
		}
	}

	public static function getAccessibleUri($url)
	{
		if (empty($url))
		{
			return false;
		}

		if (Zend_Uri::check($url))
		{
			return $url;
		}

		// the url is not a valid uri, could be a path...
		$path = $url;
		if (bdImage_Helper_File::existsAndNotEmpty($path))
		{
			return $path;
		}

		// try relative to XenForo root
		$path = XenForo_Application::getInstance()->getRootDir() . '/' . $path;
		if (bdImage_Helper_File::existsAndNotEmpty($path))
		{
			return realpath($path);
		}

		return false;
	}

	public static function getCachePath($uri, $size, $mode, $hash, $pathPrefix = false)
	{
		if ($pathPrefix === false)
		{
			$pathPrefix = XenForo_Helper_File::getExternalDataPath();
		}

		if (XenForo_Helper_File::getFileExtension($uri) === 'png')
		{
			$ext = 'png';
		}
		else
		{
			$ext = 'jpg';
		}

		$divider = substr(md5($hash), 0, 2);

		return sprintf('%s/bdImage/cache/%s_%s/%s/%s.%s', $pathPrefix, $size, $mode, $divider, $hash, $ext);
	}

	public static function getCacheUrl($uri, $size, $mode, $hash)
	{
		$url = self::getCachePath($uri, $size, $mode, $hash, XenForo_Application::$externalDataUrl);
		$url = XenForo_Link::convertUriToAbsoluteUri($url, true);

		return $url;
	}

	public static function getOriginalCachePath($uri, $pathPrefix = false)
	{
		if ($pathPrefix === false)
		{
			$pathPrefix = XenForo_Helper_File::getInternalDataPath();
		}

		return sprintf('%s/bdImage/cache/%s/%s.orig', $pathPrefix, gmdate('Ym'), md5($uri));
	}

	public static function getImage($imageData)
	{
		$imageData = self::unpackData($imageData);

		return $imageData['url'];
	}

	public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
	{
		$imageData = self::unpackData($imageData);

		if (!defined('BDIMAGE_IS_WORKING'))
		{
			return $imageData['url'];
		}

		$hash = self::computeHash($imageData['url'], $size, $mode);

		$cachePath = bdImage_Integration::getCachePath($imageData['url'], $size, $mode, $hash);
		if (bdImage_Helper_File::existsAndNotEmpty($cachePath))
		{
			$thumbnailUrl = bdImage_Integration::getCacheUrl($imageData['url'], $size, $mode, $hash);
		}

		if (empty($thumbnailUrl))
		{
			$boardUrl = XenForo_Application::getOptions()->get('boardUrl');
			$thumbnailUrl = sprintf('%s/bdImage/thumbnail.php?url=%s&size=%d&mode=%s&hash=%s', rtrim($boardUrl, '/'), rawurlencode($imageData['url']), intval($size), $mode, $hash);
		}

		return XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
	}

	public static function buildFullSizeLink($imageData)
	{
		$imageData = self::unpackData($imageData);

		if (!defined('BDIMAGE_IS_WORKING'))
		{
			return $imageData['url'];
		}
		$url = $imageData['url'];

		if (Zend_Uri::check($url))
		{
			// it is an uri already, return asap
			return $url;
		}

		$size = self::_getImageSize($imageData);
		if (empty($size))
		{
			// too bad, we cannot determine the size
			return $url;
		}

		return self::buildThumbnailLink($imageData, $size[0], $size[1]);
	}

	public static function getImgAttributes($imageData, $size, $mode = self::MODE_CROP_EQUAL)
	{
		$width = false;
		$height = false;

		$imageWidth = self::getImageWidth($imageData);
		$imageHeight = self::getImageHeight($imageData);
		if (!empty($imageWidth) AND !empty($imageHeight))
		{
			switch ($mode)
			{
				case self::MODE_CROP_EQUAL:
					$width = $size;
					$height = $size;
					break;
				case self::MODE_STRETCH_WIDTH:
					$height = $size;
					$width = $height / $imageHeight * $imageWidth;
					break;
				case self::MODE_STRETCH_HEIGHT:
					$width = $size;
					$height = $width / $imageWidth * $imageHeight;
					break;
				default:
					if (is_numeric($mode))
					{
						$width = $size;
						$height = $mode;
					}
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
			self::$_imageSizes[$imageData] = self::_getImageSize($imageData);
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
			self::$_imageSizes[$imageData] = self::_getImageSize($imageData);
		}

		if (is_array(self::$_imageSizes[$imageData]))
		{
			return self::$_imageSizes[$imageData][1];
		}

		return false;
	}

	public static function unpackData($rawImageData)
	{
		if (is_array($rawImageData))
		{
			$imageData = $rawImageData;
		}
		else
		{
			$imageData = @json_decode($rawImageData, true);
		}
		$result = array();

		if (!empty($imageData))
		{
			$result = $imageData;

			if (!isset($result['url']))
			{
				$result['url'] = false;
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

	protected static function _packData($url, $width, $height, array $extraData = array())
	{
		$data = array('url' => $url);

		if (!empty($width) AND !empty($height))
		{
			$data['width'] = $width;
			$data['height'] = $height;
		}

		// should we check for overriden values?
		$data = array_merge($data, $extraData);

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

	protected static function _getImageSize($imageData, $doFetch = true)
	{
		$imageData = self::unpackData($imageData);

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
			$uri = self::getAccessibleUri($imageData['url']);
			if (!empty($uri))
			{
				$imageSize = bdImage_Helper_ShippableHelper_ImageSize::calculate($uri);
				if (!empty($imageSize['width'])) {
					$width = $imageSize['width'];
				}
				if (!empty($imageSize['height'])) {
					$height = $imageSize['height'];
				}
			}
		}

		if (!empty($width) AND !empty($height))
		{
			return array(
				$width,
				$height
			);
		}
		else
		{
			return false;
		}
	}

}
