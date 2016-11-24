<?php

class bdImage_WidgetRenderer_AttachmentsGrid extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        $config = array(
            'name' => '[bd] Image: Attachments Grid',
            'options' => array(
                'grid_rows' => XenForo_Input::UINT,
                'grid_columns' => XenForo_Input::UINT,
                'thumbnail_width' => XenForo_Input::UINT,
                'thumbnail_height' => XenForo_Input::UINT,
                'gap' => XenForo_Input::UINT,

                'forums' => XenForo_Input::ARRAY_SIMPLE,
                'as_guest' => XenForo_Input::UINT,
            ),
            'useCache' => true,
            'cacheSeconds' => 300, // cache for 5 minutes
        );

        return $config;
    }

    protected function _getOptionsTemplate()
    {
        return 'bdimage_widget_options_attachments_grid';
    }

    protected function _renderOptions(XenForo_Template_Abstract $template)
    {
        $params = $template->getParams();

        $forums = $this->_helperPrepareForumsOptionSource(empty($params['options']['forums']) ? array() : $params['options']['forums'],
            true);

        $template->setParam('forums', $forums);

        return parent::_renderOptions($template);
    }

    protected function _validateOptionValue($optionKey, &$optionValue)
    {
        if (empty($optionValue)) {
            switch ($optionKey) {
                case 'grid_rows':
                case 'grid_columns':
                    $optionValue = 4;
                    break;
                case 'thumbnail_width':
                case 'thumbnail_height':
                    $optionValue = 50;
                    break;
                case 'gap':
                    $optionValue = 5;
                    break;
            }
        }

        return parent::_validateOptionValue($optionKey, $optionValue);
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'bdimage_widget_attachments_grid';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        $core = WidgetFramework_Core::getInstance();
        $visitor = XenForo_Visitor::getInstance();

        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $core->getModelFromCache('XenForo_Model_Attachment');
        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = $core->getModelFromCache('XenForo_Model_Node');

        $forumIds = $this->_helperGetForumIdsFromOption($widget['options']['forums'], $params,
            empty($widget['options']['as_guest']) ? false : true);
        $forumIdsWithAttachmentView = array();

        $permissionCombinationId = empty($widget['options']['as_guest']) ? $visitor['permission_combination_id'] : 1;
        $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination($permissionCombinationId);
        foreach ($forumIds as $forumId) {
            $forumPermissions = (isset($nodePermissions[$forumId]) ? $nodePermissions[$forumId] : array());

            if (XenForo_Permission::hasContentPermission($forumPermissions, 'viewAttachment')) {
                $forumIdsWithAttachmentView[] = $forumId;
            }
        }
        if (count($forumIdsWithAttachmentView) === 0) {
            // no forum with attachment view permission?! Nothing to do here
            return '';
        }

        $db = XenForo_Application::getDb();
        $days = 1;
        $posts = array();
        $requiredPostsCount = $widget['options']['grid_rows'] * $widget['options']['grid_columns'];

        while (count($posts) < $requiredPostsCount) {
            $records = $db->fetchAll('
				SELECT post.post_id, post.position,
					thread.thread_id, thread.title, thread.node_id,
					attachment.*, ' . XenForo_Model_Attachment::$dataColumns . '
				FROM `xf_post` AS post
				INNER JOIN `xf_thread` AS thread
					ON (thread.thread_id = post.thread_id)
				LEFT JOIN `xf_attachment` AS attachment
					ON (attachment.content_type = \'post\' AND attachment.content_id = post.post_id)
				LEFT JOIN `xf_attachment_data` AS data
					ON (data.data_id = attachment.data_id)
				WHERE post.message_state = \'visible\'
					AND thread.node_id IN (' . $db->quote($forumIdsWithAttachmentView) . ')
					AND thread.discussion_state = \'visible\'
					AND thread.last_post_date > ' . (XenForo_Application::$time - $days * 86400) . ' 
					AND data.thumbnail_width > 0
					AND data.thumbnail_height > 0
				ORDER BY post.post_date DESC, attachment.attachment_id ASC
			');

            $posts = array();
            foreach ($records as $record) {
                if (isset($posts[$record['post_id']])) {
                    continue;
                }

                // only get one attachment per post
                $posts[$record['post_id']] = $record;
            }

            if (!empty($posts)) {
                $days = ceil($days * max(ceil($requiredPostsCount / count($posts)), 1.1));
            } else {
                $days *= 2;
            }

            if ($days > 30) {
                // 1 month is too long...
                break;
            }
        }

        if (count($posts) < $requiredPostsCount) {
            // not enough posts, do not work
            return '';
        } elseif (count($posts) > $requiredPostsCount) {
            $posts = array_slice($posts, 0, $requiredPostsCount, true);
        }

        foreach ($posts as &$post) {
            $post['attachmentUri'] = $attachmentModel->getAttachmentDataFilePath($post);
            if (!is_readable($post['attachmentUri'])
                || filesize($post['attachmentUri']) < 10
            ) {
                $post['attachmentUri'] = XenForo_Link::buildPublicLink('full:attachments', $post);
            }
        }

        $renderTemplateObject->setParam('posts', $posts);

        return $renderTemplateObject->render();
    }

    public function useUserCache(array $widget)
    {
        if (!empty($widget['options']['as_guest'])) {
            // using guest permission
            // there is no reason to use the user cache
            return false;
        }

        return parent::useUserCache($widget);
    }

    protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array())
    {
        if ($this->_helperDetectSpecialForums($widget['options']['forums'])) {
            // we have to use special cache id when special forum ids are used
            if (isset($params['forum'])) {
                $suffix[] = 'f' . $params['forum']['node_id'];
            }
        }

        return parent::_getCacheId($widget, $positionCode, $params, $suffix);
    }

}
