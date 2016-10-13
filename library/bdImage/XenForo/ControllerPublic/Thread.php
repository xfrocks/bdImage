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

    public function actionBdImageTest()
    {
        if (!XenForo_Visitor::getInstance()->isSuperAdmin()) {
            return $this->responseNoPermission();
        }

        if ($this->isConfirmedPost()) {
            $input = $this->_input->filter(array(
                'url' => XenForo_Input::STRING,
                'width' => XenForo_Input::UINT,
                'height' => XenForo_Input::UINT,
                'rebuild' => XenForo_Input::BOOLEAN,
            ));


            if ($input['width'] > 0 && $input['height'] > 0) {
                $size = $input['width'];
                $mode = $input['height'];
            } elseif ($input['width'] > 0) {
                $size = $input['width'];
                $mode = bdImage_Integration::MODE_STRETCH_HEIGHT;
            } elseif ($input['height'] > 0) {
                $size = $input['height'];
                $mode = bdImage_Integration::MODE_STRETCH_WIDTH;
            } else {
                $imageSize = bdImage_Helper_Image::getSize($input['url']);
                if (empty($imageSize)) {
                    return $this->responseNoPermission();
                }

                $size = $imageSize[0];
                $mode = $imageSize[1];
            }

            $hash = bdImage_Helper_Data::computeHash($input['url'], $size, $mode);
            $thumbnailUrl = bdImage_Helper_Thumbnail::buildPhpLink($input['url'], $size, $mode, $hash);
            if (!empty($input['rebuild'])) {
                $rebuildHash = bdImage_Helper_Data::computeHash($thumbnailUrl, 0, 'rebuild');
                $thumbnailUrl .= sprintf('&rebuild=%s', $rebuildHash);
            }

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $thumbnailUrl);
        }

        return $this->responseView('bdImage_ViewPublic_Thread_Test', 'bdimage_thread_test');
    }
}
