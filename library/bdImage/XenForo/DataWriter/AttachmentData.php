<?php

class bdImage_XenForo_DataWriter_AttachmentData extends XFCP_bdImage_XenForo_DataWriter_AttachmentData
{
    protected function _preSave()
    {
        parent::_preSave();

        if (bdImage_Option::get('takeOverAttachThumbnail') &&
            $this->get('height') > 1 &&
            $this->get('width') > 1
        ) {
            $this->bulkSet(array(
                'thumbnail_height' => 1,
                'thumbnail_width' => 1,
            ));
            $this->setExtraData(self::DATA_THUMB_DATA, '');
            $this->setExtraData(self::DATA_TEMP_THUMB_FILE, '');
        }
    }
}
