<?php

class bdImage_Helper_LazyThumbnailer
{
    protected $_imageData;
    protected $_size;
    protected $_mode;

    public function __construct($imageData, $size, $mode = bdImage_Integration::MODE_CROP_EQUAL)
    {
        $this->_imageData = $imageData;
        $this->_size = $size;
        $this->_mode = $mode;
    }

    public function __toString()
    {
        return bdImage_Integration::buildThumbnailLink($this->_imageData, $this->_size, $this->_mode);
    }
}
