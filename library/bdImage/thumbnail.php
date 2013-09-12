<?php

$url = empty($_REQUEST['url']) ? false : $_REQUEST['url'];
$size = intval(empty($_REQUEST['size']) ? 0 : $_REQUEST['size']);
$mode = empty($_REQUEST['mode']) ? '' : $_REQUEST['mode'];
$hash = empty($_REQUEST['hash']) ? false : $_REQUEST['hash'];

$fileDir = dirname(dirname(dirname(__FILE__)));
require ($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');
XenForo_Application::initialize($fileDir . '/library', $fileDir);

if (empty($size) OR bdImage_Integration::computeHash($url, $size, $mode) != $hash)
{
	// invalid request, we may issue 401 but this is more of a security feature
	// so we are issuing 403 response now...
	header("HTTP/1.0 403 Forbidden");
	exit ;
}

$uri = bdImage_Integration::getAccessibleUri($url);
$path = bdImage_Integration::getCachePath($uri, $size, $mode, $hash);
$originalCachePath = bdImage_Integration::getOriginalCachePath($uri);

if (!file_exists($path))
{
	// this is the first time this url has been requested
	// we will have to fetch the image, then resize as needed
	$inputType = IMAGETYPE_JPEG;
	// default to use JPEG
	$ext = XenForo_Helper_File::getFileExtension($uri);
	switch ($ext)
	{
		case 'gif':
			$inputType = IMAGETYPE_GIF;
			break;
		case 'jpg':
		case 'jpeg':
			$inputType = IMAGETYPE_JPEG;
			break;
		case 'png':
			$inputType = IMAGETYPE_PNG;
			break;
		case 'data':
			// this is our attachment extension
			$inputType = IMAGETYPE_JPEG;
			// we have to read the magic bytes to determine the correct file type
			$fh = fopen($uri, 'rb');
			if (!empty($fh))
			{
				$data = fread($fh, 4);

				if (!empty($data) AND strlen($data) == 4)
				{
					if (strcmp($data, 'GIF8') === 0)
					{
						$inputType = IMAGETYPE_GIF;
					}
					elseif (strcmp(substr($data, 1, 3), 'PNG') === 0)
					{
						$inputType = IMAGETYPE_PNG;
					}
				}

				fclose($fh);
			}
			break;
	}

	if (Zend_Uri::check($uri))
	{
		// this is a remote uri, try to cache it first
		if (!file_exists($originalCachePath))
		{
			XenForo_Helper_File::createDirectory('./' . dirname($originalCachePath), true);
			file_put_contents($originalCachePath, file_get_contents($uri));
		}
		// switch to use the cached original file
		// doing this will reduce server load when a new image is uploaded and started to
		// appear
		// in different places with different sizes/modes
		$uri = $originalCachePath;
	}

	if (class_exists('Imagick'))
	{
		$image = XenForo_Image_Imagemagick_Pecl::createFromFileDirect($uri, $inputType);
	}
	else
	{
		$image = XenForo_Image_Gd::createFromFileDirect($uri, $inputType);
	}

	if (empty($image))
	{
		// problem open the url
		// issue a 500 response
		header("HTTP/1.0 500 Internal Server Error");
		exit ;
	}

	switch ($mode)
	{
		case bdImage_Integration::MODE_STRETCH_WIDTH:
			$targetHeight = $size;
			$targetWidth = $targetHeight / $image->getHeight() * $image->getWidth();
			$image->thumbnail($targetWidth, $targetHeight);
			break;
		case bdImage_Integration::MODE_STRETCH_HEIGHT:
			$targetWidth = $size;
			$targetHeight = $targetWidth / $image->getWidth() * $image->getHeight();
			$image->thumbnail($targetWidth, $targetHeight);
			break;
		default:
			if (is_numeric($mode))
			{
				// exact crop
				$origRatio = $image->getWidth() / $image->getHeight();
				$cropRatio = $size / $mode;
				$thumWidth = 0;
				$thumHeight = 0;
				if ($origRatio > $cropRatio)
				{
					$thumHeight = $mode;
					$thumWidth = $mode * $origRatio;
				}
				else
				{
					$thumWidth = $size;
					$thumHeight = $size / $origRatio;
				}

				if ($thumWidth <= $image->getWidth() AND $thumHeight <= $image->getHeight())
				{
					$image->thumbnail($thumWidth, $thumHeight);
					$image->crop(0, 0, $size, $mode);
				}
				else
				{
					// thumbnail requested is larger then the image size
					if ($origRatio > $cropRatio)
					{
						$image->crop(0, 0, $image->getHeight() * $cropRatio, $image->getHeight());
					}
					else
					{
						$image->crop(0, 0, $image->getWidth(), $image->getWidth() / $cropRatio);
					}
				}
			}
			else
			{
				// square crop
				$image->thumbnailFixedShorterSide($size);
				$image->crop(0, 0, $size, $size);
			}
			break;
	}

	XenForo_Helper_File::createDirectory('./' . dirname($path), true);
	$image->output($inputType, $path);
}

header('Location: ' . $path);
exit ;
