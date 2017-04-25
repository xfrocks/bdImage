<?php

class bdImage_Exception_WithImage extends Exception
{
    protected $_imageObj;

    public function __construct($message, XenForo_Image_Abstract $imageObj, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->_imageObj = $imageObj;
    }

    public function output()
    {
        $imageObj = $this->_imageObj;
        if (!$imageObj) {
            return;
        }

        header('Content-Type: image/jpeg');
        $imageObj->output(IMAGETYPE_JPEG, null, bdImage_Listener::$imageQuality);

        if (is_callable(array($imageObj, 'bdImage_cleanUp'))) {
            call_user_func(array($imageObj, 'bdImage_cleanUp'));
        }
    }
}