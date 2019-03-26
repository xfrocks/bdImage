<?php

namespace Xfrocks\Image\Exception;

use Xfrocks\Image\Listener;

class WithImage extends \Exception
{
    protected $_imageObj;

    public function __construct($message, \XF\Image\AbstractDriver $imageObj, \Throwable $previous = null)
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
        $imageObj->output(IMAGETYPE_JPEG, Listener::$imageQuality);

        $callable = array($imageObj, 'bdImage_cleanUp');
        if (is_callable($callable)) {
            call_user_func($callable);
        }
    }
}
