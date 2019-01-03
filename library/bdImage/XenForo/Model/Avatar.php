<?php

class bdImage_XenForo_Model_Avatar extends XFCP_bdImage_XenForo_Model_Avatar
{
    public function applyAvatar(
        $userId,
        $fileName,
        $imageType = false,
        $width = false,
        $height = false,
        $permissions = false
    ) {
        if (bdImage_Listener::$maxImageResizePixelCountEq1) {
            $GLOBALS['bdImage_Image_Abstract::canResize']++;
        }

        try {
            return parent::applyAvatar($userId, $fileName, $imageType, $width, $height, $permissions);
        } catch (Exception $e) {
            throw $e;
        } finally {
            $GLOBALS['bdImage_Image_Abstract::canResize']--;
        }
    }
}
