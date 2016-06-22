<?php

class bdImage_Helper_File
{
    public static function existsAndNotEmpty($path)
    {
        if (!is_string($path) || strlen($path) === 0) {
            return false;
        }

        return is_file($path) && filesize($path) > 0;
    }

}
