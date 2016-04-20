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
if (file_exists($parentOfDirOfFile . $pathToCheck)) {
    $fileDir = $parentOfDirOfFile;
}
if ($fileDir === false
    && !empty($scriptFilename)
) {
    $parentOfDirOfScriptFilename = dirname(dirname($scriptFilename));
    if (file_exists($parentOfDirOfScriptFilename . $pathToCheck)) {
        $fileDir = $parentOfDirOfScriptFilename;
    }
}
if ($fileDir === false) {
    die('XenForo path could not be figured out...');
}

/** @noinspection PhpIncludeInspection */
require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');
XenForo_Application::initialize($fileDir . '/library', $fileDir);

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

$requestPaths = XenForo_Application::get('requestPaths');
$requestPathRegex = '#' . preg_quote(bdImage_Integration::$generatorDirName, '#') . '/?$#';
$requestPaths['basePath'] = preg_replace($requestPathRegex, '', $requestPaths['basePath']);
$requestPaths['fullBasePath'] = preg_replace($requestPathRegex, '', $requestPaths['fullBasePath']);
XenForo_Application::set('requestPaths', $requestPaths);

if (empty($size)
    || bdImage_Helper_Data::computeHash($url, $size, $mode) != $hash
) {
    // invalid request, we may issue 401 but this is more of a security feature
    // so we are issuing 403 response now...
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$uri = bdImage_Integration::getAccessibleUri($url);
if (empty($uri)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

try {
    $thumbnailUri = bdImage_Helper_Thumbnail::getThumbnailUri($uri, $size, $mode, $hash);
} catch (XenForo_Exception $e) {
    if (XenForo_Application::debugMode()) {
        XenForo_Error::logException($e, false);
    }

    header('HTTP/1.0 500 Internal Server Error');
    exit;
}

header('Location: ' . $thumbnailUri, true, 302);
exit;
