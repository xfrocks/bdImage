<?php

class bdImage_BbCode_Formatter_Collector extends XenForo_BbCode_Formatter_Base
{
	protected $_imageUrls = array();
	
	public function getTags()
	{
		return array(
			'img' => array(
				'hasOption' => false,
				'plainChildren' => true,
				'callback' => array($this, 'renderTagImage')
			)
		);
	}
	
	public function preLoadData()
	{
		$this->_imageTemplate = '%1$s';
		
		return parent::preLoadData();
	}
	
	public function renderTagImage(array $tag, array $rendererStates)
	{
		$rendered = parent::renderTagImage($tag, $rendererStates);
		
		$parsedUrl = parse_url($rendered);
		
		if ($parsedUrl !== false)
		{
			// look like we got the image URL
			$this->_imageUrls[] = $rendered;
		}
	}
	
	public function getImageUrls()
	{
		return $this->_imageUrls;
	}
}