<?php

/* @var $app XenForo_Application */
$app = XenForo_Application::getInstance();
$path = $app->getRootDir() . '/library/XenForo/Upload.php';
$contents = file_get_contents($path);

// remove <?php
$contents = substr($contents, 5);

// rename class
$contents = str_replace(
    'class XenForo_Upload',
    'class _bdImage_XenForo_Patch_Upload',
    $contents
);

eval($contents);

abstract class bdImage_XenForo_Patch_Upload extends _bdImage_XenForo_Patch_Upload
{
    protected function _checkImageState()
    {
        $GLOBALS['bdImage_XenForo_Patch_Image_Abstract::canResize']++;

        try {
            parent::_checkImageState();
        } catch (Throwable $t) {
            throw $t;
        } finally {
            $GLOBALS['bdImage_XenForo_Patch_Image_Abstract::canResize']--;
        }
    }
}

eval('class XenForo_Upload extends bdImage_XenForo_Patch_Upload {}');

if (false) {
    abstract class _bdImage_XenForo_Patch_Upload extends XenForo_Upload
    {
    }
}
