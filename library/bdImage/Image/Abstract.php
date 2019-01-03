<?php

/* @var $app XenForo_Application */
$app = XenForo_Application::getInstance();
$path = $app->getRootDir() . '/library/XenForo/Image/Abstract.php';
$contents = file_get_contents($path);

// remove <?php
$contents = substr($contents, 5);

// rename class
$contents = str_replace('class XenForo_Image_Abstract', 'class _XenForo_Image_Abstract', $contents);

eval($contents);

$GLOBALS['bdImage_Image_Abstract::canResize'] = 0;

abstract class bdImage_Image_Abstract extends _XenForo_Image_Abstract
{
    public static function canResize($width, $height)
    {
        return $GLOBALS['bdImage_Image_Abstract::canResize'] > 0;
    }
}

eval('abstract class XenForo_Image_Abstract extends bdImage_Image_Abstract {}');

if (false) {
    abstract class _XenForo_Image_Abstract extends XenForo_Image_Abstract
    {
    }
}
