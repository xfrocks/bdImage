<?php

class bdImage_Helper_File
{
	public static function existsAndNotEmpty($path)
	{
		return is_file($path) AND filesize($path) > 0;
	}

}
