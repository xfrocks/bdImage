<?php

class bdImage_XenForo_ControllerPublic_Thread extends XFCP_bdImage_XenForo_ControllerPublic_Thread
{
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof XenForo_ControllerResponse_View
            && isset($response->params['thread'])
            && isset($response->params['forum'])
        ) {
            $imageData = bdImage_Helper_Template::getImageData('', $response->params['thread']);
            if (bdImage_Helper_Data::get($imageData, 'is_cover')) {
                $response->containerParams['bdImage_threadWithCover'] = $response->params['thread'];
                $response->containerParams['bdImage_threadWithCover']['forum'] = $response->params['forum'];
            }
        }

        return $response;
    }

    public function actionEdit()
    {
        $response = parent::actionEdit();
        $visitor = XenForo_Visitor::getInstance();

        if (bdImage_Option::get('template', 'picker')
            && $response instanceof XenForo_ControllerResponse_View
            && !empty($response->params['thread']['first_post_id'])
            && $visitor->hasPermission('general', 'bdImage_usePicker')
        ) {
            $response->params['bdImage_canUsePicker'] = true;
            $response->params['bdImage_canSetCover'] = $visitor->hasPermission('general', 'bdImage_setCover');

            /** @var bdImage_XenForo_DataWriter_DiscussionMessage_Post $firstPostDw */
            $firstPostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
            $firstPostDw->setExistingData($response->params['thread']['first_post_id']);
            $response->params['bdImage_images'] = $firstPostDw->bdImage_extractImage(true);
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
        if (!bdImage_Option::get('template', 'picker')) {
            return;
        }

        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasPermission('general', 'bdImage_usePicker')) {
            return;
        }

        /** @var bdImage_ControllerHelper_Picker $picker */
        $picker = $this->getHelper('bdImage_ControllerHelper_Picker');
        $pickedImage = $picker->getPickedData();

        if (is_string($pickedImage)) {
            /** @var bdImage_XenForo_DataWriter_Discussion_Thread $threadDw */
            $threadDw->bdImage_setThreadImage($pickedImage);
        }
    }

    public function actionImage()
    {
        $this->_assertRegistrationRequired();

        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

        $post = $ftpHelper->getPostOrError($thread['first_post_id']);
        $postModel = $this->_getPostModel();
        if (!$postModel->canViewPost($post, $thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        if ($this->isConfirmedPost()) {
            /** @var bdImage_ControllerHelper_Picker $picker */
            $picker = $this->getHelper('bdImage_ControllerHelper_Picker');
            $pickedImage = $picker->getPickedData();

            if (is_string($pickedImage)) {
                /** @var bdImage_XenForo_DataWriter_Discussion_Thread $threadDw */
                $threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
                $threadDw->setExistingData($thread, true);

                $threadDw->bdImage_setThreadImage($pickedImage);
                $threadDw->save();
            }

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                $this->_buildLink('threads', $thread)
            );
        }

        $visitor = XenForo_Visitor::getInstance();

        /** @var bdImage_XenForo_DataWriter_DiscussionMessage_Post $firstPostDw */
        $firstPostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
        $firstPostDw->setExistingData($post, true);

        $viewParams = array(
            'thread' => $thread,
            'forum' => $forum,

            'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

            'images' => $firstPostDw->bdImage_extractImage(true),
            'canSetCover' => $visitor->hasPermission('general', 'bdImage_setCover')
        );

        return $this->responseView('bdImage_ViewPublic_Thread_Image', 'bdimage_thread_image', $viewParams);
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
                $imageSize = bdImage_Integration::getSize($input['url']);
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
