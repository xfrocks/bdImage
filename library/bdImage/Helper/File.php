<?php

class bdImage_Helper_File
{
    public static function existsAndNotEmpty($path)
    {
        return is_file($path) && filesize($path) > 0;
    }

}
