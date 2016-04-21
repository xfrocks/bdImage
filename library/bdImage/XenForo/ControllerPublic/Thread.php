<?php

class bdImage_XenForo_ControllerPublic_Thread extends XFCP_bdImage_XenForo_ControllerPublic_Thread
{
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof XenForo_ControllerResponse_View
            && !empty($response->params['thread']['bdimage_image'])
            && isset($response->params['forum'])
        ) {
            if (bdImage_Helper_Data::get($response->params['thread']['bdimage_image'], 'is_cover')) {
                $response->containerParams['bdImage_threadWithCover'] = $response->params['thread'];
                $response->containerParams['bdImage_threadWithCover']['forum'] = $response->params['forum'];
            }
        }

        return $response;
    }


    public function actionEdit()
    {
        $response = parent::actionEdit();

        if ($response instanceof XenForo_ControllerResponse_View) {
            $params = &$response->params;

            $firstPost = $this->_getPostModel()->getPostById($params['thread']['first_post_id']);

            $contentData = array(
                'contentType' => 'post',
                'contentId' => $firstPost['post_id'],
                'attachmentHash' => false,
                'allAttachments' => true,
            );
            $params['bdImage_images'] = bdImage_Integration::getBbCodeImages($firstPost['message'], $contentData, null);
        }

        return $response;
    }

    public function actionSave()
    {
        $GLOBALS[bdImage_Listener::XENFORO_CONTROLLERPUBLIC_THREAD_SAVE] = $this;

        return parent::actionSave();
    }

    public function bdImage_actionSave(XenForo_DataWriter_Discussion_Thread $threadDw)
    {
        /** @var bdImage_ControllerHelper_Picker $picker */
        $picker = $this->getHelper('bdImage_ControllerHelper_Picker');
        $picked = $picker->getPickedData();

        if (is_string($picked)) {
            $threadDw->set('bdimage_image', $picked);
        }
    }

}
