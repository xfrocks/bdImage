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

}
