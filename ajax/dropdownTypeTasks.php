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

if (strpos($_SERVER['PHP_SELF'], "dropdownTypeTasks.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkRight('plugin_tasklists', UPDATE);

global $DB;

// The entity used to restrict the dropdown is client-supplied: validate the session
// actually has access to it, otherwise it could enumerate task names of other entities.
if (isset($_POST['entity'])) {
    $entity = (int) $_POST['entity'];
    if (!Session::haveAccessToEntity($entity)) {
        throw new AccessDeniedHttpException();
    }
} else {
    $entity = $_SESSION['glpiactive_entity'] ?? 0;
}

// The context identifier is client-supplied exactly like the entity above, and it was the last
// entry point handling one that did not replay the pair of checks every other path applies -
// ajax/dropdownState.php, TaskType::getKanbanColumns(), Kanban::showKanban() and the
// $checkKanbanContext helper of ajax/kanban.php all test the entity then the group visibility.
// Naming a task type of another entity, or one restricted to a group the caller is not a member
// of, listed its tasks.
$tasktypes_id = (int) ($_POST['tasktypes'] ?? 0);

// Make a select box
if ($tasktypes_id > 0) {
    $tasktype = new TaskType();
    if (!$tasktype->getFromDB($tasktypes_id)
        || !Session::haveAccessToEntity($tasktype->fields['entities_id'], $tasktype->fields['is_recursive'])
        || !TypeVisibility::isUserHaveRight($tasktypes_id)) {
        throw new AccessDeniedHttpException();
    }

    // These three were read straight out of $_POST: myname is echoed as the name of the
    // generated field and rand as its id suffix, and neither is guaranteed to be posted at all.
    $myname = (string) ($_POST['myname'] ?? 'plugin_tasklists_tasks_id');
    $rand   = (int) ($_POST['rand'] ?? mt_rand());

    $used = [];

    // Clean used array
    if (
        isset($_POST['used'])
        && is_array($_POST['used'])
        && (count($_POST['used']) > 0)
    ) {
        $options = [
            'id' => array_map('intval', $_POST['used']),
            'plugin_tasklists_tasktypes_id' => $tasktypes_id,
        ];
        foreach (
            $DB->request([
                'FROM'  => 'glpi_plugin_tasklists_tasks',
                'WHERE' => $options,
            ]) as $data
        ) {
            $used[$data['id']] = $data['id'];
        }
    }

    Dropdown::show(
        Task::class,
        [
            'name' => $myname,
            'used' => $used,
            'width' => '50%',
            'entity' => $entity,
            'rand' => $rand,
            // The core generic dropdown applies the entity handed to it and nothing else: the
            // plugin's own visibility model was not replayed here, although the plugin knows how
            // to express it - this selector listed the names of tasks the caller cannot open.
            'condition' => array_merge(
                ["glpi_plugin_tasklists_tasks.plugin_tasklists_tasktypes_id" => $tasktypes_id],
                Task::getVisibilityCriteria(),
            ),
        ],
    );
}
