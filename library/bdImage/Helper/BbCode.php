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
        if (!is_array($imageDataMany) || count($imageDataMany) === 0) {
            return null;
        }

        $autoCoverRules = null;
        if (!empty($contentData['autoCover'])) {
            $autoCoverRules = self::parseRules($contentData['autoCover']);
        }

        foreach ($imageDataMany as $imageData) {
            $unpacked = bdImage_Helper_Data::unpack($imageData);
            if (empty($unpacked[bdImage_Helper_Data::IMAGE_URL])) {
                continue;
            }

            $imageUrl = $unpacked[bdImage_Helper_Data::IMAGE_URL];
            $imageSize = bdImage_Integration::getSize($unpacked, false);
            if ($imageSize === false) {
                // ignore without-sizing image for now
                // basically we try to use attachment first
                continue;
            }

            if (is_array($autoCoverRules)) {
                if (self::checkRules($unpacked, $autoCoverRules)) {
                    $unpacked['is_cover'] = true;
                    $coverImage = bdImage_Helper_Data::pack($imageUrl, 0, 0, $unpacked);
                    return $coverImage;
                }
            } else {
                return $imageData;
            }
        }

        // fallback, just use the first image data available
        $firstImageData = reset($imageDataMany);
        $unpacked = bdImage_Helper_Data::unpack($firstImageData);
        if (empty($unpacked[bdImage_Helper_Data::IMAGE_URL])) {
            return null;
        }

        $imageUrl = $unpacked[bdImage_Helper_Data::IMAGE_URL];
        $imageSize = bdImage_Integration::getSize($unpacked);
        if ($imageSize === false) {
            return null;
        }

        $unpacked[bdImage_Helper_Data::IMAGE_WIDTH] = $imageSize[0];
        $unpacked[bdImage_Helper_Data::IMAGE_HEIGHT] = $imageSize[1];
        $firstImageData = bdImage_Helper_Data::pack($imageUrl, 0, 0, $unpacked);

        return $firstImageData;
    }

    /**
     * @param string $youtubeId
     * @return array
     */
    public static function extractYouTubeThumbnails($youtubeId)
    {
        $apiKey = bdImage_Option::get('googleApiKey');
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
        if (empty($apiJson)) {
            return self::prepareDefaultYouTubeThumbnails($youtubeId);
        }

        if (empty($apiJson['items'][0]['snippet']['thumbnails'])) {
            return self::prepareDefaultYouTubeThumbnails($youtubeId);
        }

        $imageDataMany = array();
        foreach ($apiJson['items'][0]['snippet']['thumbnails'] as $thumbnail) {
            if (empty($thumbnail['width'])
                || empty($thumbnail['height'])
            ) {
                continue;
            }

            $imageDataMany[] = bdImage_Helper_Data::pack(
                $thumbnail['url'],
                $thumbnail['width'],
                $thumbnail['height'],
                array('type' => 'youtube')
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
            $imageSize = bdImage_ShippableHelper_ImageSize::calculate($candidate);
            if (empty($imageSize['width'])
                || empty($imageSize['height'])
            ) {
                continue;
            }

            $prepared[] = bdImage_Helper_Data::pack(
                $candidate,
                $imageSize['width'],
                $imageSize['height'],
                array('type' => 'youtube')
            );
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
                        || empty($imageData[bdImage_Helper_Data::IMAGE_WIDTH])
                        || empty($imageData[bdImage_Helper_Data::IMAGE_HEIGHT])
                    ) {
                        return false;
                    }
                    $ratio = $imageData[bdImage_Helper_Data::IMAGE_WIDTH] / $imageData[bdImage_Helper_Data::IMAGE_HEIGHT];
                    if ($ruleValue[0] / $ruleValue[1] !== $ratio) {
                        return false;
                    }
                    break;
                case 'width':
                    if (empty($imageData[bdImage_Helper_Data::IMAGE_WIDTH])
                        || $imageData[bdImage_Helper_Data::IMAGE_WIDTH] < $ruleValue
                    ) {
                        return false;
                    }
                    break;
                case 'height':
                    if (empty($imageData[bdImage_Helper_Data::IMAGE_HEIGHT])
                        || $imageData[bdImage_Helper_Data::IMAGE_HEIGHT] < $ruleValue
                    ) {
                        return false;
                    }
                    break;
                default:
                    XenForo_Error::logError('Unrecognized rule key %s', $ruleKey);
                    return false;
            }
        }

        return true;
    }
}
