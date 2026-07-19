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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\Task_Comment;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();
Session::checkRight('plugin_tasklists', UPDATE);

if (!isset($_POST['plugin_tasklists_tasks_id'])) {
   throw new \RuntimeException('Required argument missing!');
}

// IDOR read: the endpoint only checked the global plugin right and then rendered the comment
// form for an arbitrary task/comment id, disclosing the content of comments from tasks outside
// the caller's entity or visibility scope. Enforce object-level rights on the parent task
// (can(READ) validates right + entity) plus the plugin visibility model, with ids cast to int.
$plugin_tasklists_tasks_id = (int) $_POST['plugin_tasklists_tasks_id'];
$task                      = new Task();
if (!$task->can($plugin_tasklists_tasks_id, READ) || !$task->checkVisibility($plugin_tasklists_tasks_id)) {
   throw new AccessDeniedHttpException();
}

$lang = null;
if (isset($_POST['language'])) {
   $lang = $_POST['language'];
}

$edit = false;
if (isset($_POST['edit'])) {
   $edit = (int) $_POST['edit'];
   // Make sure the edited comment actually belongs to the authorized task, otherwise a caller
   // could pull any comment by pairing a task they may read with a foreign comment id.
   $comment = new Task_Comment();
   if (!$comment->getFromDB($edit)
       || (int) $comment->fields['plugin_tasklists_tasks_id'] !== $plugin_tasklists_tasks_id) {
      throw new AccessDeniedHttpException();
   }
}

$answer = false;
if (isset($_POST['answer'])) {
   $answer = $_POST['answer'];
}

echo Task_Comment::getCommentForm($plugin_tasklists_tasks_id, $lang, $edit, $answer);
