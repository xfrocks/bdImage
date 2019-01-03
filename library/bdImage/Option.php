<?php

class bdImage_Option
{
    public static function get($key, $subKey = null)
    {
        if (is_array($subKey)) {
            if (count($subKey) === 1) {
                $subKey = reset($subKey);
            } else {
                $subKey = null;
            }
        }

        return XenForo_Application::getOptions()->get('bdImage_' . $key, $subKey);
    }

    public static function getThumbnailRules()
    {
        static $parsedRules = null;

        if ($parsedRules === null) {
            $parsedRules = array();
            $lines = self::get('thumbnailRules');
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
}
