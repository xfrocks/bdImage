<?php

$url = empty($_REQUEST['url']) ? false : $_REQUEST['url'];
$size = intval(empty($_REQUEST['size']) ? 0 : $_REQUEST['size']);
$mode = empty($_REQUEST['mode']) ? '' : $_REQUEST['mode'];
$hash = empty($_REQUEST['hash']) ? false : $_REQUEST['hash'];

// we have to figure out XenForo path
// dirname(dirname(__FILE__)) should work most of the time
// as it was the way XenForo's index.php does
// however, sometimes it may not work...
// so we have to be creative
$parentOfDirOfFile = dirname(dirname(__FILE__));
$scriptFilename = (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
$pathToCheck = '/library/XenForo/Autoloader.php';
$fileDir = false;
if (file_exists($parentOfDirOfFile . $pathToCheck))
{
	$fileDir = $parentOfDirOfFile;
}
if ($fileDir === false AND !empty($scriptFilename))
{
	$parentOfDirOfScriptFilename = dirname(dirname($scriptFilename));
	if (file_exists($parentOfDirOfScriptFilename . $pathToCheck))
	{
		$fileDir = $parentOfDirOfScriptFilename;
	}
}
if ($fileDir === false)
{
	die('XenForo path could not be figured out...');
}

require ($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');
XenForo_Application::initialize($fileDir . '/library', $fileDir);

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

$requestPaths = XenForo_Application::get('requestPaths');
$requestPaths['basePath'] = preg_replace('#bdImage/?$#', '', $requestPaths['basePath']);
$requestPaths['fullBasePath'] = preg_replace('#bdImage/?$#', '', $requestPaths['fullBasePath']);
XenForo_Application::set('requestPaths', $requestPaths);

if (empty($size) OR bdImage_Integration::computeHash($url, $size, $mode) != $hash)
{
	// invalid request, we may issue 401 but this is more of a security feature
	// so we are issuing 403 response now...
	header("HTTP/1.0 403 Forbidden");
	exit ;
}

$uri = bdImage_Integration::getAccessibleUri($url);
if (empty($uri))
{
	header("HTTP/1.0 404 Not Found");
	exit ;
}

$path = bdImage_Integration::getCachePath($uri, $size, $mode, $hash);
$url = bdImage_Integration::getCacheUrl($uri, $size, $mode, $hash);
$originalCachePath = bdImage_Integration::getOriginalCachePath($uri);

if (!bdImage_Helper_File::existsAndNotEmpty($path))
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
	}

	if (Zend_Uri::check($uri))
	{
		// this is a remote uri, try to cache it first
		if (!bdImage_Helper_File::existsAndNotEmpty($originalCachePath))
		{
			XenForo_Helper_File::createDirectory(dirname($originalCachePath), true);
			file_put_contents($originalCachePath, file_get_contents($uri));
		}

		// switch to use the cached original file
		// doing this will reduce server load when a new image is uploaded and started to
		// appear in different places with different sizes/modes
		$uri = $originalCachePath;
	}

	$image = XenForo_Image_Abstract::createFromFile($uri, $inputType);

	if (empty($image))
	{
		// try to read the magic bytes to determine the correct file type
		$inputTypeRead = $inputType;
		$fh = fopen($uri, 'rb');
		if (!empty($fh))
		{
			$data = fread($fh, 4);

			if (!empty($data) AND strlen($data) == 4)
			{
				if (strcmp($data, 'GIF8') === 0)
				{
					$inputTypeRead = IMAGETYPE_GIF;
				}
				elseif (strcmp(substr($data, 1, 3), 'PNG') === 0)
				{
					$inputTypeRead = IMAGETYPE_PNG;
				}
			}

			fclose($fh);
		}

		if ($inputTypeRead != $inputType)
		{
			// read some other input type, now try to read the image again...
			$inputType = $inputTypeRead;
			$image = XenForo_Image_Abstract::createFromFile($uri, $inputType);
		}
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

	if (is_callable(array(
		$image,
		'bdImage_outputProgressiveJpeg'
	)))
	{
		$image->bdImage_outputProgressiveJpeg(true);
	}

	XenForo_Helper_File::createDirectory(dirname($path), true);

	$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
	$image->output($inputType, $tempFile);

	// have to do this to support stream wrapper ([bd] Data Storage)
	// TODO: try to use copy or rename?!
	file_put_contents($path, file_get_contents($tempFile));
}

header('Location: ' . $url, true, 302);
exit ;
