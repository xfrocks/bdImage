<?php

namespace Xfrocks\Image\Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Image\Integration;

class Thread extends XFCP_Thread
{
    public function getMappings(TransformContext $context)
    {
        $mappings = parent::getMappings($context);

        $mappings[] = 'thread_image';

        return $mappings;
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        if ($key === 'thread_image') {
            /** @var \Xfrocks\Image\XF\Entity\Thread $thread */
            $thread = $context->getSource();
            $data = $thread->getXfrocksThreadImage();

            if (empty($data['url'])) {
                return null;
            }

            list($width, $height) = Integration::getSize($data, false);
            $imageData = [
                'url' => $data['url'],
                'width' => intval($width),
                'height' => intval($height)
            ];

            if (!empty($data['is_cover'])) {
                $imageData['display_mode'] = 'cover';
            }

            return $imageData;
        }

        return parent::calculateDynamicValue($context, $key);
    }

    public function collectLinks(TransformContext $context)
    {
        $links = parent::collectLinks($context);

        if (empty($links['image'])) {
            /** @var \Xfrocks\Image\XF\Entity\Thread $thread */
            $thread = $context->getSource();
            $data = $thread->getXfrocksThreadImage();

            if (!empty($data['url'])) {
                $links['image'] = $data['url'];
            }
        }

        return $links;
    }
}
