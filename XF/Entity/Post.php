<?php

namespace Xfrocks\Image\XF\Entity;

use XF\Mvc\Entity\Structure;
use Xfrocks\Image\Util\BbCode;

class Post extends XFCP_Post
{
    const OPTION_SKIP_THREAD_AUTO = 'bdImage_optionSkipThreadAuto';
    private $bdImageAttachmentHash = null;

    public function extractXfrocksImage($getAll = false)
    {
        $options = $this->app()->options();
        $contentData = [
            'contentType' => 'post',
            'contentId' => $this->post_id,
            'attachmentHash' => $this->bdImageAttachmentHash,
            'allAttachments' => $getAll || !!$options->bdImage_allAttachments,
            'autoCover' => $options->bdImage_threadAutoCover
        ];

        if ($getAll) {
            return BbCode::extractImages($this->message, $contentData);
        } else {
            return BbCode::extractImage($this->message, $contentData);
        }
    }

    public function setXfrocksAttachmentHash($hash)
    {
        $this->bdImageAttachmentHash = $hash;
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->options[self::OPTION_SKIP_THREAD_AUTO] = false;

        return $structure;
    }

    protected function _postSave()
    {
        if ($this->app()->options()->bdImage_threadAuto
            && !$this->getOption(self::OPTION_SKIP_THREAD_AUTO)
            && $this->isChanged('message')
            && $this->thread_id > 0
            && $this->position === 0
        ) {
            /** @var Thread $thread */
            $thread = $this->Thread;
            if ($this->post_id === $thread->first_post_id) {
                $existingImage = $thread->getXfrocksThreadImage();
                if (empty($existingImage['_locked'])) {
                    $image = $this->extractXfrocksImage();
                    if (is_string($image)) {
                        $thread->setXfrocksThreadImage($image);
                        $thread->save();
                    }
                }
            }
        }

        parent::_postSave();
    }
}
