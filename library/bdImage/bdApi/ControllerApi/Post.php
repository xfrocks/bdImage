<?php

class bdImage_bdApi_ControllerApi_Post extends XFCP_bdImage_bdApi_ControllerApi_Post
{
    public function actionGetEditorConfig()
    {
        $response = parent::actionGetEditorConfig();

        if ($this->_isFieldIncluded('post_images') && ($response instanceof XenForo_ControllerResponse_View)) {
            $input = $this->_input->filter(array('post_id' => XenForo_Input::UINT));
            list($post,,) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($input['post_id']);

            /** @var bdImage_XenForo_DataWriter_DiscussionMessage_Post $firstPostDw */
            $firstPostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
            $firstPostDw->setExistingData($post, true);

            $postBodyImages = array();
            $imageDataList = $firstPostDw->bdImage_extractImage(true);
            foreach ($imageDataList as $imageData) {
                $data = bdImage_Helper_Data::unpack($imageData);
                $postBodyImages[] = array('image_url' => $data[bdImage_Helper_Data::IMAGE_URL], 'data' => $imageData);
            }
            $response->params['post_images'] = $postBodyImages;
        }
        return $response;
    }
}
