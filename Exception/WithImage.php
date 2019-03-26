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
        /** @noinspection PhpParamsInspection */
        $imageObj->output(IMAGETYPE_JPEG, null, Listener::$imageQuality);

        if (is_callable(array($imageObj, 'bdImage_cleanUp'))) {
            call_user_func(array($imageObj, 'bdImage_cleanUp'));
        }
    }
}
