<?php

class bdImage_XenForo_Image_Gd extends XFCP_bdImage_XenForo_Image_Gd
{
    protected $_bdImage_optimizeOutput = false;

    public function bdImage_optimizeOutput($enabled)
    {
        $this->_bdImage_optimizeOutput = $enabled;
    }

    public function output($outputType, $outputFile = null, $quality = 85)
    {
        switch ($outputType) {
            case IMAGETYPE_GIF:
                if ($this->_bdImage_optimizeOutput) {
                    imagetruecolortopalette($this->_image, false, 256);
                }
                break;
            case IMAGETYPE_JPEG:
                if ($this->_bdImage_optimizeOutput) {
                    imageinterlace($this->_image, 1);
                }
                break;
        }

        return parent::output($outputType, $outputFile, $quality);
    }

    public function bdImage_cleanUp()
    {
        imagedestroy($this->_image);
    }
}
