<?php

class bdImage_Helper_YouTube
{
    public static function extractImageDataMany($youtubeId)
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