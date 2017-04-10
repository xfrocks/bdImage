<?php

class bdImage_Exception_WithImage extends XenForo_Exception
{
    protected $_imageObj;

    public function __construct($message, XenForo_Image_Abstract $imageObj)
    {
        parent::__construct($message);

        $this->_imageObj = $imageObj;
    }

    public function output()
    {
        header('Content-Type: image/jpeg');
        $this->_imageObj->output(IMAGETYPE_JPEG, null, bdImage_Listener::$imageQuality);
    }
}