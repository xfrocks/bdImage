<?php

namespace Xfrocks\Image\XF\Entity;

use XF\Entity\Post;
use XF\Mvc\Entity\Structure;
use Xfrocks\Image\Util\Data;

class Thread extends XFCP_Thread
{
    /**
     * @param string $image
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function setXfrocksThreadImage($image)
    {
        if (!is_string($image)) {
            throw new \InvalidArgumentException('$image must be a packed string');
        }

        $existing = $this->get('bdimage_image');
        if (!empty($existing)) {
            $image = Data::mergeAndPack($image);
        }

        return $this->set('bdimage_image', $image);
    }

    public function getXfrocksThreadImage()
    {
        return Data::unpack($this->get('bdimage_image'));
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['bdimage_image'] = ['type' => self::STR, 'default' => ''];

        return $structure;
    }

    protected function _preSave()
    {
        /** @var \Xfrocks\Image\XF\Entity\Post|null $firstPost */
        $firstPost = $this->FirstPost;
        if ($this->app()->options()->bdImage_threadAuto
            && $firstPost
        ) {
            $image = $firstPost->extractXfrocksImage();
            if (is_string($image)) {
                $this->setXfrocksThreadImage($image);
            }

            $firstPost->setOption(\Xfrocks\Image\XF\Entity\Post::OPTION_SKIP_THREAD_AUTO, true);
        }

        parent::_preSave();
    }
}
