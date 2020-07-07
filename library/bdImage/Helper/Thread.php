<?php

class bdImage_Helper_Thread
{
    /**
     * @param $thread
     * @param $pickedImage
     * @return bool
     * @throws XenForo_Exception
     */
    public static function saveThreadImage($thread, $pickedImage)
    {
        if (empty($thread)) {
            throw new XenForo_Exception('thread cannot empty');
        }

        if (!is_string($pickedImage)) {
            throw new XenForo_Exception('$image must be a packed string');
        }

        /** @var bdImage_XenForo_DataWriter_Discussion_Thread $threadDw */
        $threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
        $threadDw->setExistingData($thread, true);
        $threadDw->bdImage_setThreadImage($pickedImage);
        $threadDw->save();

        $pickedImageUnpacked = $threadDw->bdImage_getThreadImage();
        $logAction = 'bdImage_set';
        $logParams = array();
        if (!empty($pickedImageUnpacked[bdImage_Helper_Data::IMAGE_URL])) {
            if (!empty($pickedImageUnpacked['is_cover'])) {
                $logAction = 'bdImage_setCover';
            }
        } else {
            $logAction = 'bdImage_remove';
        }
        XenForo_Model_Log::logModeratorAction('thread', $thread, $logAction);
        return true;
    }
}
