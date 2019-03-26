<?php

namespace Xfrocks\Image\Util;

use Xfrocks\Image\BbCode\Renderer\Collector;
use Xfrocks\Image\Integration;

class BbCode
{
    /**
     * @param string $bbCodeString
     * @param array $contentData
     * @return array
     */
    public static function extractImages($bbCodeString, array $contentData = [])
    {
        $bbCode = \XF::app()->bbCode();
        /** @var Collector $collector */
        $collector = $bbCode->renderer('Xfrocks\Image:Collector');
        if (!empty($contentData)) {
            $collector->setContentData($contentData);
        }

        $collector->render($bbCodeString, $bbCode->parser(), $bbCode->rules('post'));

        return $collector->getImageDataMany();
    }

    /**
     * @param string $bbCode
     * @param array $contentData
     * @return null|string
     */
    public static function extractImage($bbCode, array $contentData = [])
    {
        $imageDataMany = self::extractImages($bbCode, $contentData);
        if (!is_array($imageDataMany) || count($imageDataMany) === 0) {
            return null;
        }

        $autoCoverRules = null;
        if (!empty($contentData['autoCover'])) {
            $autoCoverRules = self::parseRules($contentData['autoCover']);
        }

        foreach ($imageDataMany as $imageData) {
            $unpacked = Data::unpack($imageData);
            if (empty($unpacked[Data::IMAGE_URL])) {
                continue;
            }

            $imageUrl = $unpacked[Data::IMAGE_URL];
            $imageSize = Integration::getSize($unpacked, false);
            if ($imageSize === false) {
                // ignore without-sizing image for now
                // basically we try to use attachment first
                continue;
            }

            if (is_array($autoCoverRules)) {
                if (self::checkRules($unpacked, $autoCoverRules)) {
                    $unpacked['is_cover'] = true;
                    $coverImage = Data::pack($imageUrl, 0, 0, $unpacked);
                    return $coverImage;
                }
            } else {
                return $imageData;
            }
        }

        // fallback, just use the first image data available
        $firstImageData = reset($imageDataMany);
        $unpacked = Data::unpack($firstImageData);
        if (empty($unpacked[Data::IMAGE_URL])) {
            return null;
        }

        $imageUrl = $unpacked[Data::IMAGE_URL];
        $imageSize = Integration::getSize($unpacked);
        if ($imageSize === false) {
            return null;
        }

        $unpacked[Data::IMAGE_WIDTH] = $imageSize[0];
        $unpacked[Data::IMAGE_HEIGHT] = $imageSize[1];
        $firstImageData = Data::pack($imageUrl, 0, 0, $unpacked);

        return $firstImageData;
    }

    /**
     * @param string $youtubeId
     * @return array
     */
    public static function extractYouTubeThumbnails($youtubeId)
    {
        $apiKey = \XF::app()->options()->bdImage_googleApiKey;
        if (empty($apiKey)) {
            return self::prepareDefaultYouTubeThumbnails($youtubeId);
        }

        $apiUrl = sprintf(
            'https://www.googleapis.com/youtube/v3/videos?id=%s&key=%s&part=snippet',
            $youtubeId,
            $apiKey
        );
        $apiResponse = @file_get_contents($apiUrl);
        if (empty($apiResponse)) {
            return self::prepareDefaultYouTubeThumbnails($youtubeId);
        }

        $apiJson = @json_decode($apiResponse, true);
        if (empty($apiJson) || empty($apiJson['items'][0]['snippet']['thumbnails'])) {
            return self::prepareDefaultYouTubeThumbnails($youtubeId);
        }

        $snippetRef =& $apiJson['items'][0]['snippet'];
        $filename = 'youtube_' . $youtubeId;
        if (!empty($snippetRef['title'])) {
            $filename = $snippetRef['title'];
        }

        $imageDataMany = array();
        foreach ($snippetRef['thumbnails'] as $thumbnail) {
            if (empty($thumbnail['width'])
                || empty($thumbnail['height'])
            ) {
                continue;
            }

            $imageDataMany[] = Data::pack(
                $thumbnail['url'],
                $thumbnail['width'],
                $thumbnail['height'],
                array(
                    'type' => 'youtube',
                    'filename' => $filename,
                )
            );
        }

        return $imageDataMany;
    }

    /**
     * @param string $youtubeId
     * @return array
     */
    public static function prepareDefaultYouTubeThumbnails($youtubeId)
    {
        $prepared = array();
        $candidates = array(
            sprintf('http://img.youtube.com/vi/%s/default.jpg', $youtubeId),
        );

        foreach ($candidates as $candidate) {
//            $imageSize = bdImage_ShippableHelper_ImageSize::calculate($candidate);
//            if (empty($imageSize['width'])
//                || empty($imageSize['height'])
//            ) {
//                continue;
//            }
//
//            $prepared[] = bdImage_Helper_Data::pack(
//                $candidate,
//                $imageSize['width'],
//                $imageSize['height'],
//                array(
//                    'type' => 'youtube',
//                    'filename' => 'youtube_' . $youtubeId,
//                )
//            );
        }

        return $prepared;
    }

    /**
     * @param string $input
     * @return array|null
     */
    public static function parseRules($input)
    {
        if (empty($input)) {
            return null;
        }

        $rules = array();
        foreach (preg_split('#\s#', $input) as $ruleLine) {
            if (empty($ruleLine)) {
                continue;
            }

            $equalPos = strpos($ruleLine, '=');
            if ($equalPos === false) {
                continue;
            }

            $ruleKey = substr($ruleLine, 0, $equalPos);
            $ruleValue = substr($ruleLine, $equalPos + 1);
            switch ($ruleKey) {
                case 'ratio':
                    $ratio = explode(':', $ruleValue);
                    if (count($ratio) !== 2) {
                        $ruleValue = null;
                        break;
                    }
                    $ratio = array_map('intval', $ratio);
                    if ($ratio[0] === 0 || $ratio[1] === 0) {
                        $ruleValue = null;
                        break;
                    }
                    $ruleValue = $ratio;
                    break;
                case 'width':
                case 'height':
                    $ruleValue = intval($ruleValue);
                    break;
                default:
                    $ruleValue = trim($ruleValue);
            }

            if (strlen($ruleKey) === 0 || empty($ruleValue)) {
                continue;
            }
            $rules[$ruleKey] = $ruleValue;
        }

        return $rules;
    }

    /**
     * @param array $imageData
     * @param array $rules
     * @return bool
     */
    public static function checkRules(array $imageData, array $rules)
    {
        foreach ($rules as $ruleKey => $ruleValue) {
            switch ($ruleKey) {
                case 'prefix':
                    if (!isset($imageData['filename'])) {
                        return false;
                    }
                    if (substr_compare($imageData['filename'], $ruleValue, 0, strlen($ruleValue)) !== 0) {
                        return false;
                    }
                    break;
                case 'ratio':
                    if (empty($ruleValue[1])
                        || empty($imageData[Data::IMAGE_WIDTH])
                        || empty($imageData[Data::IMAGE_HEIGHT])
                    ) {
                        return false;
                    }
                    $ratio = $imageData[Data::IMAGE_WIDTH] / $imageData[Data::IMAGE_HEIGHT];
                    if ($ruleValue[0] / $ruleValue[1] !== $ratio) {
                        return false;
                    }
                    break;
                case 'width':
                    if (empty($imageData[Data::IMAGE_WIDTH])
                        || $imageData[Data::IMAGE_WIDTH] < $ruleValue
                    ) {
                        return false;
                    }
                    break;
                case 'height':
                    if (empty($imageData[Data::IMAGE_HEIGHT])
                        || $imageData[Data::IMAGE_HEIGHT] < $ruleValue
                    ) {
                        return false;
                    }
                    break;
                default:
                    \XF::logError('Unrecognized rule key %s', $ruleKey);
                    return false;
            }
        }

        return true;
    }
}
