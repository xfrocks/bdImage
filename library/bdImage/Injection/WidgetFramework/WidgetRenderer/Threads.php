<?php
class bdImage_Injection_WidgetFramework_WidgetRenderer_Threads
{
	public static function wf_widget_threads($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		$threads = false;
		$params = $template->getParams();
		
		if (!empty($params['new'])) $threads =& $params['new'];
		elseif (!empty($params['popular'])) $threads =& $params['popular'];
		elseif (!empty($params['mostReplied'])) $threads =& $params['mostReplied'];
		elseif (!empty($params['mostLiked'])) $threads =& $params['mostLiked'];
		elseif (!empty($params['polls'])) $threads =& $params['polls'];
		
		if ($threads !== false)
		{
			foreach ($threads as &$thread)
			{
				if (isset($thread['bdimage_image']))
				{
					$imageData = $thread['bdimage_image'];
					if (bdImage_Integration::hasImageUrl($imageData))
					{
						self::_swapAvatarToThumbnail($thread, $imageData, $content);
					}
				}
			}
		}
	}
	
	protected static function _swapAvatarToThumbnail(array &$thread, $imageData, &$html)
	{
		$threadClass = sprintf('class="thread-%d ', $thread['thread_id']);
		$avatarClass = 'class="avatar Av';
		$avatarStart = '<a';
		$avatarEnd = '</a>';
		
		$threadClassPos = strpos($html, $threadClass);
		if ($threadClassPos === false) return false;
		
		$avatarClassPos = strpos($html, $avatarClass, $threadClassPos);
		if ($avatarClassPos === false) return false;
		
		$avatarStartPos = strrpos($html, $avatarStart, $avatarClassPos - strlen($html));
		if ($avatarStartPos === false) return false;
		
		$avatarEndPos = strpos($html, $avatarEnd, $avatarStartPos);
		if ($avatarEndPos === false) return false;
		
		$replacement = sprintf('<a href="%s" class="avatar NoOverlay"><img src="%s" width="%d" height="%d" /></a>',
			XenForo_Link::buildPublicLink('threads', $thread),
			bdImage_Integration::buildThumbnailLink($imageData, 48),
			48, 48
		);
		$html = substr_replace($html, $replacement, $avatarStartPos, $avatarEndPos + strlen($avatarEnd) - $avatarStartPos);
		return true;
	}
}