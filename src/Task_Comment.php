<?php

/*
 -------------------------------------------------------------------------
 tasklists plugin for GLPI
 Copyright (C) 2016-2026 by the tasklists Development Team.

 https://github.com/InfotelGLPI/tasklists
 -------------------------------------------------------------------------

 LICENSE

 This file is part of tasklists.

 tasklists is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 tasklists is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with tasklists. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Tasklists;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class Task_Comment
 */
class Task_Comment extends CommonDBTM {

   static function getTypeName($nb = 0) {
      return _n('Comment', 'Comments', $nb);
   }

    /**
     * @return string
     */
    static function getIcon()
    {
        return Task::getIcon();
    }


    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (!$item->can($item->getID(), UPDATE)) {
         return '';
      }

      $nb = 0;
      if ($_SESSION['glpishow_count_on_tabs']) {
         $where = [];
         if ($item->getType() == Task::getType()) {
            $where = [
               'plugin_tasklists_tasks_id' => $item->getID(),
               'language'                  => null
            ];
         } else {
            $where = [
               'plugin_tasklists_tasks_id' => $item->fields['plugin_tasklists_tasks_id'],
               'language'                  => $item->fields['language']
            ];
         }

         $nb = countElementsInTable(
            'glpi_plugin_tasklists_tasks_comments',
            $where
         );
      }
      return self::createTabEntry(self::getTypeName($nb), $nb);
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      self::showForItem($item, $withtemplate);
      return true;
   }

   /**
    * Show linked items of a task
    *
    * @param $item                     CommonDBTM object
    * @param $withtemplate    integer  withtemplate param (default 0)
    **/
   static function showForItem(CommonDBTM $item, $withtemplate = 0) {

      // Total Number of comments
       global $CFG_GLPI;
       if ($item->getType() == Task::getType()) {
         $where                     = [
            'plugin_tasklists_tasks_id' => $item->getID(),
            'language'                  => null
         ];
         $plugin_tasklists_tasks_id = $where['plugin_tasklists_tasks_id'];
      } else {
         $where                     = [
            'plugin_tasklists_tasks_id' => $item->fields['plugin_tasklists_tasks_id'],
            'language'                  => $item->fields['language']
         ];
         $plugin_tasklists_tasks_id = $where['plugin_tasklists_tasks_id'];
      }

      $task = new Task();
      $task->getFromDB($plugin_tasklists_tasks_id);

      $number = countElementsInTable(
         'glpi_plugin_tasklists_tasks_comments',
         $where
      );

      $entry = new Task();
      $entry->getFromDB($task->fields['id']);
      $cancomment = true;

      $lang     = null;
      $comments = self::getCommentsForTaskItem($plugin_tasklists_tasks_id, $where['language']);

      // The click handlers ('.add_answer'/'.edit_item') live in js/tasklists_comment.js
      // (registered via Hooks::ADD_JAVASCRIPT); the endpoint is read from data-ajax-url.
      TemplateRenderer::getInstance()->display('@tasklists/comment/tab.html.twig', [
         'cancomment'    => $cancomment,
         'number'        => $number,
         'comment_form'  => $cancomment ? self::getCommentForm($plugin_tasklists_tasks_id, $lang) : '',
         'comments_html' => self::displayComments($comments, $cancomment),
         'ajax_url'      => $CFG_GLPI['root_doc'] . '/plugins/tasklists/ajax/getTaskComment.php',
      ]);
   }

   /**
    * Gat all comments for specified Task entry
    *
    * @param integer $plugin_tasklists_tasks_id Task entry ID
    * @param string  $lang Requested language
    * @param integer $parent Parent ID (defaults to 0)
    *
    * @return array
    */
   static public function getCommentsForTaskItem($plugin_tasklists_tasks_id, $lang, $parent = null) {
      global $DB;

      $where = [
         'plugin_tasklists_tasks_id' => $plugin_tasklists_tasks_id,
         'language'                  => $lang,
         'parent_comment_id'         => $parent
      ];

       $db_comments = $DB->request([
           'FROM'  => 'glpi_plugin_tasklists_tasks_comments',
           'WHERE' => $where,
           'ORDERBY' => 'id ASC',
       ]);

      $comments = [];
      foreach ($db_comments as $db_comment) {
         $db_comment['answers'] = self::getCommentsForTaskItem($plugin_tasklists_tasks_id, $lang, $db_comment['id']);
         $comments[]            = $db_comment;
      }

      return $comments;
   }

   /**
    * Display comments
    *
    * @param array   $comments Comments
    * @param boolean $cancomment Whether user can comment or not
    * @param integer $level Current level, defaults to 0
    *
    * @return string
    */
   static public function displayComments($comments, $cancomment, $level = 0) {
      // Twig auto-escapes the comment text and every attribute (recursive thread macro),
      // removing the stored-XSS surface the manual string concatenation carried.
      return TemplateRenderer::getInstance()->render('@tasklists/comment/list.html.twig', [
         'comments'   => self::buildCommentsTree($comments),
         'cancomment' => $cancomment,
      ]);
   }

   /**
    * Build a view-ready comment tree (recursively) for the Twig thread macro.
    *
    * @param array $comments Raw comments (each may hold nested 'answers')
    *
    * @return array
    */
   static private function buildCommentsTree($comments) {
      $tree = [];
      foreach ($comments as $comment) {
         $user = new User();
         $user->getFromDB($comment['users_id']);

         $thumbnail_url = User::getThumbnailURLForPicture($user->fields['picture']);
         $avatar_style  = !empty($thumbnail_url)
            ? 'background-image: url("' . $thumbnail_url . '")'
            : 'background-color: ' . $user->getUserInitialsBgColor();

         $tree[] = [
            'id'                        => (int) $comment['id'],
            'plugin_tasklists_tasks_id' => (int) $comment['plugin_tasklists_tasks_id'],
            'language'                  => $comment['language'],
            'comment'                   => $comment['comment'],
            'date_creation'             => Html::convDateTime($comment['date_creation']),
            'user_url'                  => $user->getLinkURL(),
            'avatar_style'              => $avatar_style,
            'user_initials'             => empty($thumbnail_url) ? $user->getUserInitials() : '',
            'can_edit'                  => (Session::getLoginUserID() == $comment['users_id']),
            'answers'                   => (isset($comment['answers']) && count($comment['answers']) > 0)
               ? self::buildCommentsTree($comment['answers'])
               : [],
         ];
      }
      return $tree;
   }

   /**
    * Get comment form
    *
    * @param integer       $plugin_tasklists_tasks_id Task item ID
    * @param string        $lang Related item language
    * @param false|integer $edit Comment id to edit, or false
    * @param false|integer $answer Comment id to answer to, or false
    *
    * @return string
    */
   static public function getCommentForm($plugin_tasklists_tasks_id, $lang = null, $edit = false, $answer = false) {
      $content = '';
      if ($edit !== false) {
         // Load the comment being edited from its own table (not Task).
         $comment = new self();
         $comment->getFromDB($edit);
         $content = $comment->fields['comment'];
      }

      // Twig auto-escapes every value below (textarea content, language, ids), which
      // structurally prevents the attribute/tag break-out this form used to be exposed to.
      return TemplateRenderer::getInstance()->render('@tasklists/comment/form.html.twig', [
         'rand'                      => mt_rand(),
         'action'                    => Toolbox::getItemTypeFormURL(__CLASS__),
         'content'                   => $content,
         'plugin_tasklists_tasks_id' => (int) $plugin_tasklists_tasks_id,
         'lang'                      => $lang,
         'is_edit'                   => ($edit !== false),
         'edit'                      => ($edit !== false) ? (int) $edit : 0,
         'is_answer'                 => ($answer !== false),
         'answer'                    => ($answer !== false) ? (int) $answer : 0,
      ]);
   }

   function prepareInputForAdd($input) {
      if (!isset($input["users_id"])) {
         $input["users_id"] = 0;
         if ($uid = Session::getLoginUserID()) {
            $input["users_id"] = $uid;
         }
      }

      return $input;
   }
}
