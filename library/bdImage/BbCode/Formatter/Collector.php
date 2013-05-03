<?php

class bdImage_BbCode_Formatter_Collector extends XenForo_BbCode_Formatter_Base
{
	protected $_imageUrls = array();
	protected $_attachmentIds = array();

	protected $_dw = false;
	protected $_contentData = false;

	public function setDataWriter(XenForo_DataWriter $dw)
	{
		$this->_dw = $dw;
	}

	public function setContentData(array $contentData)
	{
		$this->_contentData = $contentData;
	}

	public function reset()
	{
		$this->_imageUrls = array();
		$this->_attachmentIds = array();
	}

	public function getImageUrls()
	{
		if (!empty($this->_attachmentIds))
		{
			// found some attachment ids...
			if (!empty($this->_dw) AND !empty($this->_contentData))
			{
				$attachmentModel = $this->_dw->getModelFromCache('XenForo_Model_Attachment');
				$attachments = array();

				if (!empty($this->_contentData['contentId']))
				{
					$attachments +=  $attachmentModel->getAttachmentsByContentId($this->_contentData['contentType'], $this->_contentData['contentId']);
				}

				if (!empty($this->_contentData['attachmentHash']))
				{
					$attachments +=  $attachmentModel->getAttachmentsByTempHash($this->_contentData['attachmentHash']);
				}

				foreach ($this->_attachmentIds as $attachmentId)
				{
					if (isset($attachments[$attachmentId]) AND $attachments[$attachmentId]['width'] > 0)
					{
						$dataFilePath = $attachmentModel->getAttachmentDataFilePath($attachments[$attachmentId]);

						if (file_exists($dataFilePath))
						{
							$dataFilePath = str_replace(XenForo_Helper_File::getInternalDataPath(), '', $dataFilePath); // remove the full path for security reason
							$this->_imageUrls[] = $dataFilePath;
						}
						else
						{
							// provide support for [bd] Attachment Store
							$this->_imageUrls[] = XenForo_Link::buildPublicLink('canonical:attachments', $attachments[$attachmentId]);
						}
					}
				}
			}
				
			$this->_attachmentIds = array(); // clear
		}

		return $this->_imageUrls;
	}

	public function getTags()
	{
		return array(
				'img' => array(
						'hasOption' => false,
						'plainChildren' => true,
						'callback' => array($this, 'renderTagImage')
				),
				'attach' => array(
						'plainChildren' => true,
						'callback' => array($this, 'renderTagAttach')
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

	public function renderTagAttach(array $tag, array $rendererStates)
	{
		$id = intval($this->stringifyTree($tag['children']));
		if (!empty($id))
		{
			$this->_attachmentIds[] = $id;
		}
	}
}