<?php

class bdImage_CronEntry_CleanUp
{
	public static function pruneFiles()
	{
		$fileTtlDays = bdImage_Option::get('fileTtlDays');
		if ($fileTtlDays == 0)
		{
			return;
		}

		$cutOff = XenForo_Application::$time - $fileTtlDays * 86400;

		$dirPath = sprintf('%s/bdImage/cache', XenForo_Helper_File::getExternalDataPath());
		self::pruneFilesInDir($dirPath, $cutOff);
	}

	public static function pruneFilesInDir($dirPath, $cutOff)
	{
		$dirPath = rtrim($dirPath, '/');
		$subDirPaths = array();
		$filePaths = array();
		$deleteSelf = true;

		$dh = opendir($dirPath);
		while ($file = readdir($dh))
		{
			if ($file == '.' OR $file == '..')
			{
				continue;
			}

			$filePath = sprintf('%s/%s', $dirPath, $file);

			if (is_file($filePath))
			{
				$filePaths[] = $filePath;
			}
			else
			{
				$subDirPaths[] = $filePath;
			}
		}
		closedir($dh);

		foreach ($subDirPaths as $subDirPath)
		{
			if (self::pruneFilesInDir($subDirPath, $cutOff))
			{
				// sub dir is deleted
			}
			else
			{
				// sub dir is kept
				$deleteSelf = false;
			}
		}

		foreach ($filePaths as $filePath)
		{
			if (filemtime($filePath) < $cutOff)
			{
				unlink($filePath);
			}
			else
			{
				$deleteSelf = false;
			}
		}

		if ($deleteSelf)
		{
			rmdir($dirPath);
			return true;
		}
		else
		{
			return false;
		}
	}

}
