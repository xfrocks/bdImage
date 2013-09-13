<?php

class bdImage_BbCode_Formatter_Collector extends XenForo_BbCode_Formatter_Base
{
	protected $_imageUrls = array();
	protected $_attachmentIds = array();

	protected $_dwOrModel = false;
	protected $_contentData = false;

	public function setDwOrModel($dwOrModel)
	{
		$this->_dwOrModel = $dwOrModel;
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
		if (!empty($this->_attachmentIds) OR !empty($this->_contentData['allAttachments']))
		{
			// found some attachment ids...
			if (!empty($this->_contentData))
			{
				$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
				$attachments = array();

				if (!empty($this->_contentData['contentId']))
				{
					$attachments += $attachmentModel->getAttachmentsByContentId($this->_contentData['contentType'], $this->_contentData['contentId']);
				}

				if (!empty($this->_contentData['attachmentHash']))
				{
					$attachments += $attachmentModel->getAttachmentsByTempHash($this->_contentData['attachmentHash']);
				}

				if (!empty($this->_contentData['allAttachments']))
				{
					$this->_attachmentIds = array_keys($attachments);
				}

				foreach ($this->_attachmentIds as $attachmentId)
				{
					if (isset($attachments[$attachmentId]) AND $attachments[$attachmentId]['width'] > 0)
					{
						$dataFilePath = $attachmentModel->getAttachmentDataFilePath($attachments[$attachmentId]);

						if (file_exists($dataFilePath))
						{
							$attachmentUrl = $dataFilePath;
						}
						else
						{
							// provide support for [bd] Attachment Store
							$attachmentUrl = XenForo_Link::buildPublicLink('canonical:attachments', $attachments[$attachmentId]);
						}

						$imageUrlKeyFound = false;
						foreach (array_keys($this->_imageUrls) as $imageUrlKey)
						{
							if (is_array($this->_imageUrls[$imageUrlKey]) AND $this->_imageUrls[$imageUrlKey][0] == 'attachment' AND $this->_imageUrls[$imageUrlKey][1] == $attachmentId)
							{
								$this->_imageUrls[$imageUrlKey] = $attachmentUrl;
								$imageUrlKeyFound = true;
							}
						}
						if (!$imageUrlKeyFound)
						{
							$this->_imageUrls[] = $attachmentUrl;
						}
					}
				}
			}

			$this->_attachmentIds = array();
		}

		$imageUrls = array();

		foreach ($this->_imageUrls as $imageUrl)
		{
			if (is_string($imageUrl))
			{
				$imageUrls[] = $imageUrl;
			}
			else
			{
				$type = array_shift($imageUrl);
				$data = $imageUrl;
				$imageUrl = false;

				switch ($type)
				{
					case 'media':
						if ($data[0] == 'youtube')
						{
							$imageUrl = sprintf('http://img.youtube.com/vi/%s/default.jpg', $data[1]);
						}
						break;
				}

				if (!empty($imageUrl))
				{
					$imageUrls[] = $imageUrl;
				}
			}
		}

		return $imageUrls;
	}

	public function getTags()
	{
		return array(
			'img' => array(
				'hasOption' => false,
				'plainChildren' => true,
				'callback' => array(
					$this,
					'renderTagImage'
				)
			),
			'attach' => array(
				'plainChildren' => true,
				'callback' => array(
					$this,
					'renderTagAttach'
				)
			),
			'media' => array(
				'hasOption' => true,
				'plainChildren' => true,
				'callback' => array(
					$this,
					'renderTagMedia'
				)
			),

			'quote' => array(
				'plainChildren' => true,
				'trimLeadingLinesAfter' => 2,
				'callback' => array(
					$this,
					'renderTagQuote'
				)
			),
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
			$this->_imageUrls[] = array(
				'attachment',
				$id
			);
			$this->_attachmentIds[] = $id;
		}
	}

	public function renderTagMedia(array $tag, array $rendererStates)
	{
		$mediaKey = trim($this->stringifyTree($tag['children']));
		$mediaSiteId = strtolower($tag['option']);

		$this->_imageUrls[] = array(
			'media',
			$mediaSiteId,
			$mediaKey
		);

		return '';
	}

	public function getModelFromCache($class)
	{
		if (empty($this->_dwOrModel))
		{
			$this->_dwOrModel = XenForo_Model::create($class);
			return $this->_dwOrModel;
		}
		elseif ($this->_dwOrModel instanceof $class)
		{
			return $this->_dwOrModel;
		}
		else
		{
			return $this->_dwOrModel->getModelFromCache($class);
		}
	}

}
