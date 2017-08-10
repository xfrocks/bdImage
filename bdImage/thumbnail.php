<?php

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
$requestPathRegex = '#' . preg_quote(bdImage_Listener::$generatorDirName, '#') . '/?$#';
$requestPaths['basePath'] = preg_replace($requestPathRegex, '', $requestPaths['basePath']);
$requestPaths['fullBasePath'] = preg_replace($requestPathRegex, '', $requestPaths['fullBasePath']);
XenForo_Application::set('requestPaths', $requestPaths);

bdImage_Helper_Thumbnail::main();
