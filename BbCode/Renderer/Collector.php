<?php

namespace Xfrocks\Image\BbCode\Renderer;

use XF\BbCode\Renderer\Html;
use XF\Entity\Attachment;
use Xfrocks\Image\Util\BbCode;
use Xfrocks\Image\Util\Data;

class Collector extends Html
{
    protected $bdImageAttachmentIds = [];
    protected $bdImageImageUrls = [];
    protected $bdImageMediaIds = [];

    protected $bdImageContentData = null;

    public function setContentData(array $contentData)
    {
        $this->bdImageContentData = $contentData;
    }

    public function addDefaultTags()
    {
        $this->addTag('img', [
            'callback' => 'renderTagImage'
        ]);

        $this->addTag('media', [
            'callback' => 'renderTagMedia',
            'trimAfter' => 1
        ]);

        $this->addTag('quote', [
            'callback' => 'renderTagQuote',
            'trimAfter' => 2
        ]);

        $this->addTag('attach', [
            'callback' => 'renderTagAttach',
        ]);
    }

    public function reset()
    {
        $this->bdImageAttachmentIds = [];
        $this->bdImageImageUrls = [];
        $this->bdImageMediaIds = [];
    }

    public function getImageDataMany()
    {
        $imageDataMany = [];
        $app = \XF::app();

        if (!empty($this->bdImageAttachmentIds)
            || !empty($this->bdImageContentData['allAttachments'])
        ) {
            // found some attachment ids...
            if (!empty($this->bdImageContentData)) {
                $attachments = $app->em()->getEmptyCollection();
                if (!empty($this->bdImageContentData['contentId'])
                    && (!isset($this->bdImageContentData['contentAttachCount'])
                        || $this->bdImageContentData['contentAttachCount'] > 0)
                ) {
                    $attachments = $attachments->merge(
                        $app->finder('XF:Attachment')
                        ->where('content_type', $this->bdImageContentData['contentType'])
                        ->where('content_id', $this->bdImageContentData['contentId'])
                        ->fetch()
                    );

                    if (\XF::$debugMode && defined('DEFERRED_CMD')) {
                        echo(sprintf(
                            "Fetching attachments for %s-%d\n",
                            $this->bdImageContentData['contentType'],
                            $this->bdImageContentData['contentId']
                        ));
                    }
                }

                if (!empty($this->bdImageContentData['attachmentHash'])) {
                    $attachments = $attachments->merge(
                        $app->finder('XF:Attachment')
                        ->where('temp_hash', $this->bdImageContentData['attachmentHash'])
                        ->fetch()
                    );
                    if (\XF::$debugMode && defined('DEFERRED_CMD')) {
                        echo(sprintf("Fetching attachments for %s\n", $this->bdImageContentData['attachmentHash']));
                    }
                }

                if (!empty($this->bdImageContentData['allAttachments'])) {
                    $this->bdImageAttachmentIds = $attachments->keys();
                }

                foreach ($this->bdImageAttachmentIds as $attachmentId) {
                    /** @var Attachment|null $attachmentRef */
                    $attachmentRef = isset($attachments[$attachmentId]) ? $attachments[$attachmentId] : null;
                    if ($attachmentRef
                        && $attachmentRef->Data->width > 0
                        && $attachmentRef->Data->height > 0
                    ) {
                        $orgTempHash = $attachmentRef->temp_hash;
                        $attachmentRef->temp_hash = '';
                        $attachmentUrl = $app->router('public')->buildLink('canonical:attachments', $attachmentRef);

                        $imageDataMany[] = Data::pack(
                            $attachmentUrl,
                            $attachmentRef->Data->width,
                            $attachmentRef->Data->height,
                            array(
                                'type' => 'attachment',
                                'attachment_id' => $attachmentId,
                                'filename' => $attachmentRef->getFilename(),
                            )
                        );

                        $attachmentRef->temp_hash = $orgTempHash;
                    }
                }
            }
        }

        foreach ($this->bdImageImageUrls as $imageUrl) {
            $imageDataMany[] = Data::pack($imageUrl, 0, 0, array(
                'type' => 'url',
                'filename' => basename($imageUrl),
            ));
        }

        foreach ($this->bdImageMediaIds as $mediaId) {
            switch ($mediaId[0]) {
                case 'youtube':
                    $imageDataMany = array_merge(
                        $imageDataMany,
                        BbCode::extractYouTubeThumbnails($mediaId[1])
                    );
                    break;
            }
        }

        $imageDataMany = array_unique($imageDataMany);

        return $imageDataMany;
    }

    public function renderTagAttach(array $children, $option, array $tag, array $options)
    {
        $id = intval($this->renderSubTreePlain($children));
        if (!empty($id)) {
            $this->bdImageAttachmentIds[] = $id;
        }
    }

    public function renderTagImage(array $children, $option, array $tag, array $options)
    {
        $url = $this->renderSubTreePlain($children);

        $validUrl = $this->getValidUrl($url);
        if (!$validUrl) {
            return '';
        }

        $this->bdImageImageUrls[] = $validUrl;

        return '';
    }

    public function renderTagMedia(array $children, $option, array $tag, array $options)
    {
        $mediaKey = trim($this->renderSubTreePlain($children));
        if (preg_match('#[&?"\'<>\r\n]#', $mediaKey) || strpos($mediaKey, '..') !== false) {
            return '';
        }

        $mediaSiteId = strtolower($option);
        if (!isset($this->mediaSites[$mediaSiteId])) {
            return '';
        }

        $this->bdImageMediaIds[] = [$mediaSiteId, $mediaKey];

        return '';
    }

    public function renderTagQuote(array $children, $option, array $tag, array $options)
    {
        return '';
    }
}
