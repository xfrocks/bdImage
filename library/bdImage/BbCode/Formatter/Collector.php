<?php

class bdImage_BbCode_Formatter_Collector extends XenForo_BbCode_Formatter_Base
{
    protected $_bdImage_attachmentIds = array();
    protected $_bdImage_imageUrls = array();
    protected $_bdImage_mediaIds = array();

    /** @var XenForo_DataWriter */
    protected $_dwOrModel = null;
    protected $_contentData = null;

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
        $this->_bdImage_attachmentIds = array();
        $this->_bdImage_imageUrls = array();
        $this->_bdImage_mediaIds = array();
    }

    public function getImageDataMany()
    {
        $imageDataMany = array();

        if (!empty($this->_bdImage_attachmentIds)
            || !empty($this->_contentData['allAttachments'])
        ) {
            // found some attachment ids...
            if (!empty($this->_contentData)) {
                /** @var XenForo_Model_Attachment $attachmentModel */
                $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
                $attachments = array();

                if (!empty($this->_contentData['contentId'])) {
                    $attachments += $attachmentModel->getAttachmentsByContentId(
                        $this->_contentData['contentType'],
                        $this->_contentData['contentId']
                    );
                }

                if (!empty($this->_contentData['attachmentHash'])) {
                    $attachments += $attachmentModel->getAttachmentsByTempHash($this->_contentData['attachmentHash']);
                }

                if (!empty($this->_contentData['allAttachments'])) {
                    $this->_bdImage_attachmentIds = array_keys($attachments);
                }

                foreach ($this->_bdImage_attachmentIds as $attachmentId) {
                    if (isset($attachments[$attachmentId])
                        && isset($attachments[$attachmentId]['width'])
                        && $attachments[$attachmentId]['width'] > 0
                        && isset($attachments[$attachmentId]['height'])
                        && $attachments[$attachmentId]['height'] > 0
                    ) {
                        $attachmentUrl = XenForo_Link::buildPublicLink('canonical:attachments',
                            $attachments[$attachmentId]);

                        $imageDataMany[] = bdImage_Helper_Data::pack(
                            $attachmentUrl,
                            $attachments[$attachmentId]['width'],
                            $attachments[$attachmentId]['height'],
                            array('type' => 'attachment')
                        );
                    }
                }
            }
        }

        foreach ($this->_bdImage_imageUrls as $imageUrl) {
            $imageDataMany[] = $imageUrl;
        }

        foreach ($this->_bdImage_mediaIds as $mediaId) {
            switch ($mediaId[0]) {
                case 'youtube':
                    $imageDataMany = array_merge($imageDataMany,
                        bdImage_Helper_BbCode::extractYouTubeThumbnails($mediaId[1]));
                    break;
            }
        }

        return $imageDataMany;
    }

    public function getTags()
    {
        return array(
            'attach' => array(
                'plainChildren' => true,
                'callback' => array(
                    $this,
                    'renderTagAttach'
                )
            ),

            'img' => array(
                'hasOption' => false,
                'plainChildren' => true,
                'callback' => array(
                    $this,
                    'renderTagImage'
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

    public function renderTagAttach(array $tag, array $rendererStates)
    {
        $id = intval($this->stringifyTree($tag['children']));
        if (!empty($id)) {
            $this->_bdImage_attachmentIds[] = $id;
        }
    }

    public function renderTagImage(array $tag, array $rendererStates)
    {
        $url = $this->stringifyTree($tag['children']);

        $validUrl = $this->_getValidUrl($url);
        if (!$validUrl) {
            return '';
        }

        $this->_bdImage_imageUrls[] = $validUrl;
        return '';
    }

    public function renderTagMedia(array $tag, array $rendererStates)
    {
        $mediaKey = trim($this->stringifyTree($tag['children']));
        $mediaSiteId = strtolower($tag['option']);

        $this->_bdImage_mediaIds[] = array(
            $mediaSiteId,
            $mediaKey
        );

        return '';
    }

    public function renderTagQuote(array $tag, array $rendererStates)
    {
        return '';
    }

    public function getModelFromCache($class)
    {
        if (empty($this->_dwOrModel)) {
            $this->_dwOrModel = XenForo_Model::create($class);
            return $this->_dwOrModel;
        } elseif ($this->_dwOrModel instanceof $class) {
            return $this->_dwOrModel;
        } else {
            return $this->_dwOrModel->getModelFromCache($class);
        }
    }

}
