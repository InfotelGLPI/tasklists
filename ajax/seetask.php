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
use GlpiPlugin\Tasklists\Task;

Session::checkRight('plugin_tasklists', UPDATE);

Html::header_nocache();
header("Content-Type: text/html; charset=UTF-8");

//Html::requireJs('tinymce');
echo "<script type='text/javascript'  src='../../../public/lib/tinymce.js'></script>";

if (isset($_GET['id'])) {
    // IDOR read: gate on object-level right + plugin visibility before showing the task.
    $tasks_id = (int) $_GET['id'];
    $task     = new Task();
    if (!$task->can($tasks_id, READ) || !$task->checkVisibility($tasks_id)) {
        throw new AccessDeniedHttpException();
    }
    $options = [
        'from_edit_ajax' => true,
        'id'             => $tasks_id,
        'withtemplate'   => 0,
    ];
    echo "<div class='center'>";
    echo "<a href='" . Task::getFormURL(true) . "?id=" . $tasks_id . "'>" . __("View this item in his context") . "</a>";
    echo "</div>";
    echo "<hr>";
    $task->showForm($tasks_id, $options);
} elseif (isset($_GET['plugin_tasklists_tasktypes_id'])
           && isset($_GET['plugin_tasklists_taskstates_id'])) {
    $options = [
        'from_edit_ajax'                 => true,
        'plugin_tasklists_tasktypes_id'  => $_GET['plugin_tasklists_tasktypes_id'],
        'plugin_tasklists_taskstates_id' => $_GET['plugin_tasklists_taskstates_id'],
        'withtemplate'                   => 0,
    ];
    $task    = new Task();
    if ($id = $task->hasTemplate($options)) {
        $options['withtemplate'] = 2;
        $task->showForm($id, $options);
    } else {
        $task->showForm(0, $options);
    }
} elseif (isset($_GET['clone_id'])) {
    // IDOR read: only clone a task the caller may actually read and see.
    $id   = (int) $_GET['clone_id'];
    $task = new Task();
    if (!$task->can($id, READ) || !$task->checkVisibility($id)) {
        throw new AccessDeniedHttpException();
    }
    if ($task->getFromDB($id)) {
        $options    = [
            'from_edit_ajax'                 => true,
            'plugin_tasklists_tasktypes_id'  => $task->fields['plugin_tasklists_tasktypes_id'],
            'plugin_tasklists_taskstates_id' => $task->fields['plugin_tasklists_taskstates_id'],
            'priority'                       => $task->fields['priority'],
            'users_id'                       => Session::getLoginUserID(),
            'groups_id'                      => $task->fields['groups_id'],
            'client'                         => $task->fields['client'],
            'entities_id'                    => $task->fields['entities_id'],
            'visibility'                     => $task->fields['visibility'],
            'withtemplate'                   => 0,
        ];
        $taskcloned = new Task();
        $taskcloned->showForm(0, $options);
    }
} elseif (isset($_GET['task_id'])) {
    // IDOR read: only pre-fill a ticket from a task the caller may read and see.
    $id   = (int) $_GET['task_id'];
    $task = new Task();
    if (!$task->can($id, READ) || !$task->checkVisibility($id)) {
        throw new AccessDeniedHttpException();
    }
    if ($task->getFromDB($id)) {
        $options = [
            'from_edit_ajax' => true,
            //'plugin_tasklists_tasktypes_id'  => $task->fields['plugin_tasklists_tasktypes_id'],
            //'plugin_tasklists_taskstates_id' => $task->fields['plugin_tasklists_taskstates_id'],
            //'priority'                       => $task->fields['priority'],
            //'users_id'                       => Session::getLoginUserID(),
            //'groups_id'                      => $task->fields['groups_id'],
            //'client'                         => $task->fields['client'],
            'entities_id'    => $task->fields['entities_id'],
            'name'           => $task->fields['name'],
            'content'        => $task->fields['comment'],
            'withtemplate'   => 0,
        ];
        $ticket  = new Ticket();
        $ticket->showForm(0, $options);
    }
}
