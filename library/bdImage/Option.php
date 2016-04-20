<?php

class bdImage_Option
{
    public static function get($key, $subKey = null)
    {
        return XenForo_Application::getOptions()->get(sprintf('bdImage_%s', $key), $subKey);
    }

}
