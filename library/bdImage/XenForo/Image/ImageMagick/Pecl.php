<?php

class bdImage_XenForo_Image_ImageMagick_Pecl extends XFCP_bdImage_XenForo_Image_ImageMagick_Pecl
{
    protected $_bdImage_optimizeOutput = false;

    public function bdImage_optimizeOutput($enabled)
    {
        $this->_bdImage_optimizeOutput = $enabled;

        // drop frames asap to save time resizing / cropping
        $this->_bdImage_dropFrames();
    }

    public function output($outputType, $outputFile = null, $quality = 85)
    {
        switch ($outputType) {
            case IMAGETYPE_GIF:
                if ($this->_bdImage_optimizeOutput) {
                    $bits = 8;

                    if ($this->_image->getImageColors() > pow(2, $bits)) {
                        $this->_image->quantizeImage(pow(2, $bits), Imagick::COLORSPACE_RGB, 0, false, false);
                        $this->_image->setImageDepth($bits);
                    }
                }
                break;
            case IMAGETYPE_JPEG:
                if ($this->_bdImage_optimizeOutput) {
                    $this->_image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                }
                break;
        }

        return parent::output($outputType, $outputFile, $quality);
    }

    protected function _bdImage_dropFrames()
    {
        $dropStep = 3;
        $maxFrames = 10;

        if ($this->_image->count() > $dropStep) {
            if ($this->_image->count() / $dropStep > $maxFrames) {
                $dropStep = floor($this->_image->count() / $maxFrames);
            }

            $newImage = new Imagick();
            $i = 0;
            foreach ($this->_image as $frame) {
                if ($i % $dropStep === 0) {
                    $delay = $frame->getImageDelay();
                    $frame->setImageDelay($delay * $dropStep);
                    $newImage->addImage($frame->getImage());
                }
                $i++;
            }
            $this->_image->destroy();
            $this->_image = $newImage;
        }
    }
}
