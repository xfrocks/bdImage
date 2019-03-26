<?php

namespace Xfrocks\Image\Util;

use Xfrocks\Image\Integration;
use Xfrocks\Image\Listener;
use Xfrocks\Image\Uti\Thumbnail;

class ThumbnailRules
{
    public static function builtIn($imageUrl, $size, $mode)
    {
        $hash = Data::computeHash($imageUrl, $size, $mode);

        if (!Listener::$skipCacheCheck) {
            $cachePath = File::getCachePath($imageUrl, $size, $mode, $hash);
            list(, , $cacheFileHash) = File::getCacheFileHash($cachePath);
            if ($cacheFileHash) {
                $thumbnailUrl = sprintf(
                    '%s?%s',
                    File::getCacheUrl($imageUrl, $size, $mode, $hash),
                    $cacheFileHash
                );
            }
        }

        $thumbnailUrl = null;
        if (empty($thumbnailUrl)) {
//            $thumbnailUrl = Thumbnail::buildPhpLink($imageUrl, $size, $mode);
        }

//        return XenForo_Link::convertUriToAbsoluteUri($thumbnailUrl, true);
        return $thumbnailUrl;
    }

    public static function check($matcher, array $kvPairs, $imageUrl, $size, $mode)
    {
        if (!preg_match($matcher, $imageUrl)) {
            return null;
        }

        if (isset($kvPairs['sed'])) {
            if (preg_match('/^s#([^#]+)#([^#]+)#(\w*)$/', $kvPairs['sed'], $sedMatches)) {
                $search = "#{$sedMatches[1]}#{$sedMatches[3]}";
                $replace = $sedMatches[2];
                $imageUrl = preg_replace($search, $replace, $imageUrl);
            }
        }

        if (!isset($kvPairs['engine'])) {
            return null;
        }

        switch ($kvPairs['engine']) {
            case 'imageproxy':
                if (empty($kvPairs['imageproxy_root']) ||
                    !isset($kvPairs['imageproxy_key'])
                ) {
                    return null;
                }

                switch ($mode) {
                    case Integration::MODE_STRETCH_WIDTH:
                        $options = sprintf('x%d', $size);
                        break;
                    case Integration::MODE_STRETCH_HEIGHT:
                        $options = sprintf('%dx', $size);
                        break;
                    case Integration::MODE_CROP_EQUAL:
                        $options = sprintf('%d', $size);
                        break;
                    default:
                        $options = sprintf('%dx%d', $size, $mode);
                }

                return self::imageproxy(
                    $kvPairs['imageproxy_root'],
                    $kvPairs['imageproxy_key'],
                    $imageUrl,
                    $options
                );
            case 'imgproxy':
                if (empty($kvPairs['imgproxy_root']) ||
                    empty($kvPairs['imgproxy_key']) ||
                    empty($kvPairs['imgproxy_salt'])
                ) {
                    return null;
                }

                switch ($mode) {
                    case Integration::MODE_STRETCH_WIDTH:
                        $options = sprintf('h:%d', $size);
                        break;
                    case Integration::MODE_STRETCH_HEIGHT:
                        $options = sprintf('w:%d', $size);
                        break;
                    case Integration::MODE_CROP_EQUAL:
                        $options = sprintf('rs:fill:%1$d:%1$d:0', $size);
                        break;
                    default:
                        $options = sprintf('rs:fill:%d:%d:0', $size, $mode);
                }

                return self::imgproxy(
                    $kvPairs['imgproxy_root'],
                    $kvPairs['imgproxy_key'],
                    $kvPairs['imgproxy_salt'],
                    $imageUrl,
                    $options
                );
        }

        return null;
    }

    public static function imageproxy($root, $key, $imageUrl, $options)
    {
        # https://github.com/willnorris/imageproxy/wiki/URL-signing
        $signature = '';
        if ($key !== '') {
            $signature = ',' . strtr(base64_encode(hash_hmac('sha256', $imageUrl, $key, true)), '/+', '_-');
        }

        return sprintf('%s/%s%s/%s', $root, $options, $signature, $imageUrl);
    }

    public static function imgproxy($root, $key, $salt, $imageUrl, $options)
    {
        # https://tools.ietf.org/html/rfc3986#section-2.2: gen-delims
        # https://tools.ietf.org/html/rfc3986#section-2.3: unreserved
        if (preg_match('#^[:/\[\]@A-Za-z0-9\-\._~]+$#', $imageUrl)) {
            $path = "/$options/plain/$imageUrl";
        } else {
            $imageUrlB64 = self::_imgproxyBase64($imageUrl);
            $path = "/$options/$imageUrlB64";
        }

        # https://github.com/DarthSim/imgproxy/blob/master/docs/signing_the_url.md
        $signature = self::_imgproxyBase64(hash_hmac(
            'sha256',
            pack('H*', $salt) . $path,
            pack('H*', $key),
            true
        ));

        return sprintf('%s/%s%s', $root, $signature, $path);
    }

    public static function getThumbnailRules()
    {
        static $parsedRules = null;

        if ($parsedRules === null) {
            $parsedRules = array();
            $lines = \XF::app()->options()->bdImage_thumbnailRules;

            foreach (explode("\n", $lines) as $line) {
                $parts = explode(' ', $line);
                $matcher = array_shift($parts);
                $kvPairs = array();

                foreach ($parts as $part) {
                    $keyAndValue = explode('=', $part, 2);
                    if (count($keyAndValue) === 2) {
                        list($key, $value) = $keyAndValue;
                        $kvPairs[$key] = $value;
                    }
                }

                if (count($kvPairs) === 0) {
                    continue;
                }

                $parsedRules[$matcher] = $kvPairs;
            }
        }

        return $parsedRules;
    }

    protected static function _imgproxyBase64($str)
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }
}
