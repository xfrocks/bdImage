<?php

class bdImage_Helper_BbCode
{
    /**
     * @param string $bbCode
     * @param array $contentData
     * @param XenForo_DataWriter|XenForo_Model $dwOrModel
     * @return array
     */
    public static function extractImages($bbCode, array $contentData = array(), $dwOrModel = null)
    {
        /** @var bdImage_BbCode_Formatter_Collector $formatter */
        $formatter = XenForo_BbCode_Formatter_Base::create('bdImage_BbCode_Formatter_Collector');
        if (!empty($contentData)) {
            $formatter->setContentData($contentData);
        }
        if (!empty($dw)) {
            $formatter->setDwOrModel($dwOrModel);
        }

        $parser = new XenForo_BbCode_Parser($formatter);
        $parser->render($bbCode);

        return $formatter->getImageDataMany();
    }

    /**
     * @param string $bbCode
     * @param array $contentData
     * @param XenForo_DataWriter|XenForo_Model $dwOrModel
     * @return null|string
     */
    public static function extractImage($bbCode, array $contentData = array(), $dwOrModel = null)
    {
        $imageDataMany = self::extractImages($bbCode, $contentData, $dwOrModel);
        if (empty($imageDataMany)) {
            return null;
        }

        $imageData = reset($imageDataMany);
        if (empty($imageData)) {
            return null;
        }

        $imageUrl = bdImage_Helper_Data::get($imageData, 'url');
        $imageWidth = bdImage_Integration::getImageWidth($imageData);
        $imageHeight = bdImage_Integration::getImageHeight($imageData);

        return bdImage_Helper_Data::pack($imageUrl, $imageWidth, $imageHeight,
            bdImage_Helper_Data::unpack($imageData));
    }

    /**
     * @param string $youtubeId
     * @return array
     */
    public static function extractYouTubeThumbnails($youtubeId)
    {
        $defaultUrls = array(sprintf('http://img.youtube.com/vi/%s/default.jpg', $youtubeId));
        $apiKey = bdImage_Option::get('googleApiKey');
        if (empty($apiKey)) {
            return $defaultUrls;
        }

        $apiUrl = sprintf('https://www.googleapis.com/youtube/v3/videos?id=%s&key=%s&part=snippet',
            $youtubeId, $apiKey);
        $apiResponse = @file_get_contents($apiUrl);
        if (empty($apiResponse)) {
            return $defaultUrls;
        }

        $apiJson = @json_decode($apiResponse, true);
        if (empty($apiJson)) {
            return $defaultUrls;
        }

        if (empty($apiJson['items'][0]['snippet']['thumbnails'])) {
            return $defaultUrls;
        }

        $imageDataMany = array();
        foreach ($apiJson['items'][0]['snippet']['thumbnails'] as $thumbnail) {
            $imageDataMany[] = bdImage_Helper_Data::pack($thumbnail['url'],
                $thumbnail['width'], $thumbnail['height'], array('type' => 'youtube'));
        }

        return $imageDataMany;
    }
}