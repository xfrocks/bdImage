<?php

class bdImage_XenForo_Image_ImageMagick_Pecl extends XFCP_bdImage_XenForo_Image_ImageMagick_Pecl
{
    protected $_bdImage_outputProgressiveJpeg = false;

    public function bdImage_outputProgressiveJpeg($enabled)
    {
        $this->_bdImage_outputProgressiveJpeg = $enabled;
    }

    public function output($outputType, $outputFile = null, $quality = 85)
    {
        switch ($outputType) {
            case IMAGETYPE_JPEG:
                if ($this->_bdImage_outputProgressiveJpeg) {
                    $this->_image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                }
                break;
        }

        return parent::output($outputType, $outputFile, $quality);
    }

}
