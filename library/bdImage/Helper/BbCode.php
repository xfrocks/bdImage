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
        if (!is_array($imageDataMany)) {
            return null;
        }

        $autoCoverRules = null;
        if (!empty($contentData['autoCover'])) {
            $autoCoverRules = self::parseRules($contentData['autoCover']);
        }

        $image = null;
        foreach ($imageDataMany as $imageData) {
            $imageUrl = bdImage_Helper_Data::get($imageData, bdImage_Helper_Data::IMAGE_URL);
            if (empty($imageUrl)) {
                continue;
            }

            $imageSize = bdImage_Helper_Image::getSize($imageData);
            if ($imageSize === false) {
                continue;
            }

            $unpackedImageData = bdImage_Helper_Data::unpack($imageData);
            $unpackedImageData[bdImage_Helper_Data::IMAGE_WIDTH] = $imageSize[0];
            $unpackedImageData[bdImage_Helper_Data::IMAGE_HEIGHT] = $imageSize[1];
            if ($image === null) {
                $image = bdImage_Helper_Data::pack($imageUrl, 0, 0, $unpackedImageData);
            }

            if (is_array($autoCoverRules)) {
                if (self::checkRules($unpackedImageData, $autoCoverRules)) {
                    $unpackedImageData['is_cover'] = true;
                    $coverImage = bdImage_Helper_Data::pack($imageUrl, 0, 0, $unpackedImageData);
                    return $coverImage;
                }
            } else {
                return $image;
            }
        }

        return $image;
    }

    /**
     * @param string $youtubeId
     * @return array
     */
    public static function extractYouTubeThumbnails($youtubeId)
    {
        $defaultUrls = array(
            bdImage_Helper_Data::pack(sprintf('http://img.youtube.com/vi/%s/default.jpg',
                $youtubeId), 0, 0, array('type' => 'youtube')),
        );
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
                    if ($ruleValue[0] / $ruleValue[1] !== $imageData[bdImage_Helper_Data::IMAGE_WIDTH] / $imageData[bdImage_Helper_Data::IMAGE_HEIGHT]) {
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