<?php

namespace Xfrocks\Image\XF\Service\Post;

use Xfrocks\Image\XF\Entity\Post;

class Preparer extends XFCP_Preparer
{
    public function setAttachmentHash($hash)
    {
        parent::setAttachmentHash($hash);

        /** @var Post $post */
        $post = $this->post;
        $post->setXfrocksAttachmentHash($hash);
    }
}
