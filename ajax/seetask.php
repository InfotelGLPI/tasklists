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
use GlpiPlugin\Tasklists\TaskType;
use GlpiPlugin\Tasklists\TypeVisibility;

Session::checkRight('plugin_tasklists', UPDATE);

Html::header_nocache();
header("Content-Type: text/html; charset=UTF-8");

// No script tag is emitted here any more. The one that stood at this line pointed at
// ../../../public/lib/tinymce.js: from /plugins/tasklists/ajax/ that resolves to
// <glpi>/public/lib/tinymce.js, a GLPI 10 layout - in GLPI 11 the directory served as the web
// root IS public/, so the asset lives at <glpi>/lib/tinymce.js and the request returned a 404 on
// every opening of the modal. Restoring Html::requireJs('tinymce') would not help either: it
// only pushes onto $_SESSION['glpi_js_toload'], which is drained by Html::footer() during a full
// page render, so in an AJAX fragment the entry would simply leak into the next page. It is
// unnecessary in the first place - Html::includeHeader() calls requireJs('tinymce')
// unconditionally (src/Html.php), so the document this fragment is injected into has already
// loaded the editor.

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
    // The three other branches of this endpoint all replay can($id, READ) then
    // checkVisibility($id) before showing anything; this one showed the template task of a
    // context named by the client, checking neither end. hasTemplate() filters on is_template,
    // is_deleted, is_archived, the requested context and the entity criteria, nothing else: it
    // replays neither Session::haveAccessToEntity() nor TypeVisibility::isUserHaveRight() on the
    // context - the pair that Kanban::showKanban(), ajax/dropdownState.php,
    // ajax/dropdownTypeTasks.php and the $checkKanbanContext helper of ajax/kanban.php all apply
    // - and Task::showForm() performs no access control of its own. Enumerating the context
    // identifiers over a plain GET therefore returned the whole template form - name, RichText
    // description, requester, technician, group, client, priority, due date - of every context
    // restricted to a group the caller is not a member of, which is exactly what the group
    // visibility model exists to prevent.
    $tasktypes_id = (int) $_GET['plugin_tasklists_tasktypes_id'];
    $tasktype     = new TaskType();
    if (!$tasktype->getFromDB($tasktypes_id)
        || !Session::haveAccessToEntity($tasktype->fields['entities_id'], $tasktype->fields['is_recursive'])
        || !TypeVisibility::isUserHaveRight($tasktypes_id)) {
        throw new AccessDeniedHttpException();
    }

    $options = [
        'from_edit_ajax'                 => true,
        // Both identifiers used to reach $options - and from there the query and the form -
        // without ever being cast.
        'plugin_tasklists_tasktypes_id'  => $tasktypes_id,
        'plugin_tasklists_taskstates_id' => (int) $_GET['plugin_tasklists_taskstates_id'],
        'withtemplate'                   => 0,
    ];
    $task    = new Task();
    $id      = (int) $task->hasTemplate($options);
    if ($id > 0) {
        // A template is a task like any other: same pair as the neighbouring branches. Checking
        // the context is not enough on its own, since the template carries its own visibility
        // level and its own owner.
        if (!$task->can($id, READ) || !$task->checkVisibility($id)) {
            throw new AccessDeniedHttpException();
        }
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
            // glpi_plugin_tasklists_tasks has no comment column: the schema declares content
            // (sql/empty-2.1.0.sql), and content is what prepareInputForAdd()/Update(),
            // showForm() and NotificationTargetTask manipulate. The description of the task was
            // therefore never carried over to the ticket, and PHP 8.2 raised an "Undefined array
            // key" on every call. comment only exists on the neighbouring tables - tasktypes,
            // taskstates, notifications - which is where the confusion came from.
            'content'        => $task->fields['content'],
            'withtemplate'   => 0,
        ];
        $ticket  = new Ticket();
        $ticket->showForm(0, $options);
    }
}
