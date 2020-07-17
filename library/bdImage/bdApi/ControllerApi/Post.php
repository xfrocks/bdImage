<?php

class bdImage_bdApi_ControllerApi_Post extends XFCP_bdImage_bdApi_ControllerApi_Post
{
    public function actionGetEditorConfig()
    {
        $response = parent::actionGetEditorConfig();

        if ($this->_isFieldIncluded('post_images') && ($response instanceof XenForo_ControllerResponse_View)) {
            $input = $this->_input->filter(array('post_id' => XenForo_Input::UINT));
            list($post, $thread,) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($input['post_id']);

            /** @var bdImage_XenForo_DataWriter_DiscussionMessage_Post $firstPostDw */
            $firstPostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
            $firstPostDw->setExistingData($post, true);

            $threadImageData = bdImage_Helper_Data::unpack($thread['bdimage_image']);
            $postBodyImages = array();
            $imageDataList = $firstPostDw->bdImage_extractImage(true);
            foreach ($imageDataList as $imageData) {
                $data = bdImage_Helper_Data::unpack($imageData);
                $postBodyImages[] = array(
                    'image_url' => $data[bdImage_Helper_Data::IMAGE_URL],
                    'data' => $imageData,
                    'image_is_thread_image' =>
                        $data[bdImage_Helper_Data::IMAGE_URL] === $threadImageData[bdImage_Helper_Data::IMAGE_URL],
                    'image_is_cover' =>
                        $data[bdImage_Helper_Data::IMAGE_URL] === $threadImageData[bdImage_Helper_Data::IMAGE_URL]
                        && !empty($threadImageData['is_cover'])
                );
            }
            $response->params['post_images'] = $postBodyImages;
        }
        return $response;
    }
}
