<?php

class bdImage_XenForo_DataWriter_Discussion_Thread extends XFCP_bdImage_XenForo_DataWriter_Discussion_Thread
{
    /**
     * @return array
     */
    public function bdImage_getThreadImage()
    {
        return bdImage_Helper_Data::unpack($this->get('bdimage_image'));
    }

    /**
     * @param string $image
     * @return bool
     * @throws XenForo_Exception
     */
    public function bdImage_setThreadImage($image)
    {
        if (!is_string($image)) {
            throw new XenForo_Exception('$image must be a packed string');
        }

        $existing = $this->get('bdimage_image');
        if (!empty($existing)) {
            $image = bdImage_Helper_Data::mergeAndPack($existing, $image);
        }

        return $this->set('bdimage_image', $image);
    }

    /**
     * @return null|string
     */
    public function bdImage_getImageFromTags()
    {
        $tags = $this->get('tags');
        $tags = XenForo_Helper_Php::safeUnserialize($tags);

        if (empty($tags)) {
            return null;
        }

        /** @var XenForo_Model_Tag $tagModel */
        $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
        $tags = $tagModel->getTags(XenForo_Application::arrayColumn($tags, 'tag'));

        if (empty($tags)) {
            return null;
        }

        foreach ($tags as $tag) {
            if (!empty($tag['tinhte_xentagnhattao_thumbnail'])) {
                $imageSize = bdImage_Integration::getSize($tag['tinhte_xentagnhattao_thumbnail']);
                if ($imageSize === false) {
                    continue;
                }

                return bdImage_Helper_Data::pack(
                    $tag['tinhte_xentagnhattao_thumbnail'],
                    $imageSize[0],
                    $imageSize[1],
                    array('type' => 'tag')
                );
            }
        }

        return null;
    }

    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_thread']['bdimage_image'] = array(
            'type' => XenForo_DataWriter::TYPE_STRING,
            'default' => ''
        );

        return $fields;
    }

    protected function _discussionPreSave()
    {
        if (bdImage_Option::get('threadAuto')
            && $this->_firstMessageDw
        ) {
            /** @var bdImage_XenForo_DataWriter_DiscussionMessage_Post $firstMessageDw */
            $firstMessageDw = $this->_firstMessageDw;
            $image = $firstMessageDw->bdImage_extractImage();
            if (is_string($image)) {
                $this->bdImage_setThreadImage($image);
            }

            // tell the post data writer not to update the thread again
            $optionName = bdImage_XenForo_DataWriter_DiscussionMessage_Post::OPTION_SKIP_THREAD_AUTO;
            $this->_firstMessageDw->setOption($optionName, true);
        } elseif (bdImage_Option::get('imageFromTags')
            && $this->isChanged('tags')
        ) {
            $existingImage = $this->bdImage_getThreadImage();
            if (empty($existingImage[bdImage_Helper_Data::IMAGE_URL])) {
                $image = $this->bdImage_getImageFromTags();
                if (is_string($image)) {
                    $this->bdImage_setThreadImage($image);
                }
            }
        }

        parent::_discussionPreSave();
    }
}
