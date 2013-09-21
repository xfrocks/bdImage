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
		$realpath = realpath($url);
		if (file_exists($realpath))
		{
			return $realpath;
		}

		// try relative to XenForo root
		$realpath = realpath(XenForo_Application::getInstance()->getRootDir() . '/' . $url);
		if (file_exists($realpath))
		{
			return $realpath;
		}

		return false;
	}

	public static function getCachePath($uri, $size, $mode, $hash, $pathPrefix = 'cache')
	{
		if (XenForo_Helper_File::getFileExtension($uri) === 'png')
		{
			$ext = 'png';
		}
		else
		{
			$ext = 'jpg';
		}

		$divider = substr(md5($hash), 0, 2);

		return sprintf('%s/%s_%s/%s/%s.%s', $pathPrefix, $size, $mode, $divider, $hash, $ext);
	}

	public static function getOriginalCachePath($uri, $pathPrefix = 'cache')
	{
		return sprintf('%s/%s/%s.orig', $pathPrefix, gmdate('Ymd'), md5($uri));
	}

	public static function getImage($imageData)
	{
		$imageData = self::unpackData($imageData);

		return $imageData['url'];
	}

	public static function buildThumbnailLink($imageData, $size, $mode = self::MODE_CROP_EQUAL)
	{
		$imageData = self::unpackData($imageData);
		$hash = self::computeHash($imageData['url'], $size, $mode);

		$cachePath = bdImage_Integration::getCachePath($imageData['url'], $size, $mode, $hash);
		$fullCachePath = sprintf('%s/bdImage/%s', XenForo_Helper_File::getExternalDataPath(), $cachePath);
		if (file_exists($fullCachePath))
		{
			$thumbnailUrl = sprintf('%s/bdImage/%s', XenForo_Application::$externalDataUrl, $cachePath);
		}

		if (empty($thumbnailUrl))
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

			$thumbnailUrl = sprintf('%s/bdImage/thumbnail.php?url=%s&size=%d&mode=%s&hash=%s', XenForo_Application::$externalDataUrl, rawurlencode($imageData['url']), intval($size), $mode, $hash);
		}

		return XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
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
				require_once (dirname(__FILE__) . '/ThirdParties/Fastimage.php');
				$originalCachePath = self::getOriginalCachePath($uri);
				if (file_exists($originalCachePath))
				{
					$image = new FastImage($originalCachePath);
				}
				else
				{
					$image = new FastImage($uri);
				}
				set_time_limit(5);
				list($width, $height) = $image->getSize();
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
