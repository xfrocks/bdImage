<?php

/* @var $app XenForo_Application */
$app = XenForo_Application::getInstance();
$path = $app->getRootDir() . '/library/XenForo/Image/Abstract.php';
$contents = file_get_contents($path);

// remove <?php
$contents = substr($contents, 5);

// rename class
$contents = str_replace(
    'class XenForo_Image_Abstract',
    'class _bdImage_XenForo_Patch_Image_Abstract',
    $contents
);

eval($contents);

$GLOBALS['bdImage_XenForo_Patch_Image_Abstract::canResize'] = 0;

abstract class bdImage_XenForo_Patch_Image_Abstract extends _bdImage_XenForo_Patch_Image_Abstract
{
    public static function canResize($width, $height)
    {
        if ($GLOBALS['bdImage_XenForo_Patch_Image_Abstract::canResize'] <= 0) {
            return false;
        }

        if (bdImage_Listener::$maxImageResizePixelOurs === 0) {
            // unlimited resizing
            // $config['bdImage_maxImageResizePixelCount'] to set a limit
            return true;
        }

        return (($width * $height) < bdImage_Listener::$maxImageResizePixelOurs);
    }
}

eval('abstract class XenForo_Image_Abstract extends bdImage_XenForo_Patch_Image_Abstract {}');

if (false) {
    abstract class _bdImage_XenForo_Patch_Image_Abstract extends XenForo_Image_Abstract
    {
    }
}
