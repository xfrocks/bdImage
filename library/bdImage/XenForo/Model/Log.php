<?php
class bdImage_XenForo_Model_Log extends XFCP_bdImage_XenForo_Model_Log
{
	public function pruneModeratorLogEntries($pruneDate = null)
	{
		$this->bdImage_pruneCache();
		
		return parent::pruneModeratorLogEntries($pruneDate);
	}
	
	public function bdImage_pruneCache()
	{
		$cacheDirPath = sprintf('%s/bdImage/cache', XenForo_Helper_File::getExternalDataPath());
		
		$contents = glob(sprintf('%s/*', $cacheDirPath));
		foreach ($contents as $contentPath)
		{
			if (is_dir($contentPath))
			{
				$dirName = basename($contentPath);
				if (preg_match('/(\d{4})(\d{2})(\d{2})/', $dirName, $matches))
				{
					$timestamp = gmmktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
					if (XenForo_Application::$time - $timestamp > 86400*2)
					{
						self::_bdImage_doRmRf($contentPath);
					}
				}
			}
		}
	}
	
	public function _bdImage_doRmRf($dirPath)
	{
		// TODO: should we use system()? It will be much faster!
		
		if (!is_dir($dirPath) OR !is_writable($dirPath))
		{
			return false;
		}
		
		$contents = glob(sprintf('%s/*', $dirPath));
		foreach ($contents as $contentPath)
		{
			if (is_file($contentPath))
			{
				@unlink($contentPath);
			}
			else
			{
				self::_bdImage_doRmRf($contentPath);
			}
		}
		
		$result = @unlink($dirPath);
		
		return $result;
	}
}