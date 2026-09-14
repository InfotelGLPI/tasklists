<?php

/**
 * -------------------------------------------------------------------------
 * tasklists plugin for GLPI
 * Copyright (C) 2016-2026 by the tasklists Development Team.
 *
 * https://github.com/InfotelGLPI/tasklists
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of tasklists.
 *
 * tasklists is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * tasklists is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with tasklists. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\Task_Comment;

$comment = new Task_Comment();
if (!isset($_POST['plugin_tasklists_tasks_id'])) {
    $message = __('Mandatory fields are not filled!');
    Session::addMessageAfterRedirect($message, false, ERROR);
    Html::back();
}
$tasks_id = (int) $_POST['plugin_tasklists_tasks_id'];
$task = new Task();
// Every branch of this controller writes, so the right tested is UPDATE, not READ. The two
// other paths exposing the same feature already required it - Task_Comment::getTabNameForItem()
// hides the tab unless can($id, UPDATE) and ajax/getTaskComment.php calls
// Session::checkRight('plugin_tasklists', UPDATE) - so a read-only profile simply never saw
// the tab, while the controller receiving the POST accepted it: the protection was interface
// concealment. checkVisibility() stays, it enforces the plugin's own visibility model.
if (!$task->can($tasks_id, UPDATE) || !$task->checkVisibility($tasks_id)) {
    throw new AccessDeniedHttpException();
}

if (isset($_POST["add"])) {
    if (!isset($_POST['plugin_tasklists_tasks_id']) || !isset($_POST['comment'])) {
        $message = __('Mandatory fields are not filled!');
        Session::addMessageAfterRedirect($message, false, ERROR);
        Html::back();
    }

    // Never trust a client-supplied author: the comment is always owned by the caller.
    $input                                = $_POST;
    $input['users_id']                    = Session::getLoginUserID();
    $input['plugin_tasklists_tasks_id']   = $tasks_id;
    if ($newid = $comment->add($input)) {
        Session::addMessageAfterRedirect(
            "<a href='#taskcomment$newid'>" . __('Your comment has been added') . "</a>",
            false,
            INFO,
        );
    }
    Html::back();
}

if (isset($_POST["edit"])) {
    if (!isset($_POST['plugin_tasklists_tasks_id']) || !isset($_POST['id']) || !isset($_POST['comment'])) {
        $message = __('Mandatory fields are not filled!');
        Session::addMessageAfterRedirect($message, false, ERROR);
        Html::back();
    }

    // Ownership: the edit affordance is rendered client-side only when the comment
    // belongs to the caller, so re-check it here. The comment must also belong to the
    // task authorized above.
    if (!$comment->getFromDB((int) $_POST['id'])
        || (int) $comment->fields['users_id'] !== (int) Session::getLoginUserID()
        || (int) $comment->fields['plugin_tasklists_tasks_id'] !== $tasks_id) {
        throw new AccessDeniedHttpException();
    }
    $data = array_merge($comment->fields, $_POST);
    // Never let the client rewrite ownership or reparent the comment via extra POST keys.
    $data['users_id']                  = $comment->fields['users_id'];
    $data['plugin_tasklists_tasks_id'] = $comment->fields['plugin_tasklists_tasks_id'];
    if ($comment->update($data)) {
        Session::addMessageAfterRedirect(
            "<a href='#taskcomment{$comment->getID()}'>" . __('Your comment has been edited') . "</a>",
            false,
            INFO,
        );
    }
    Html::back();
}

throw new BadRequestHttpException("lost");
