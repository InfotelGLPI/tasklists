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

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_tasklists', UPDATE);

if (isset($_POST["plugin_tasklists_tasktypes_id"])) {

    // This was the only endpoint handling a context identifier that did not replay the pair of
    // checks every other path applies - ajax/dropdownTypeTasks.php, Task::validatePostedContext(),
    // TaskType::getKanbanColumns(), Kanban::showKanban() and the $checkKanbanContext helper of
    // ajax/kanban.php all test the entity then the group visibility. It took the identifier from
    // the client and listed the states configured for it, so the workflow of a context of
    // another entity, or of a context restricted to a group the caller is not a member of, was
    // readable.
    $tasktypes_id = (int) $_POST['plugin_tasklists_tasktypes_id'];
    $tasktype     = new TaskType();
    if (!$tasktype->getFromDB($tasktypes_id)
        || !Session::haveAccessToEntity($tasktype->fields['entities_id'], $tasktype->fields['is_recursive'])
        || !TypeVisibility::isUserHaveRight($tasktypes_id)) {
        throw new AccessDeniedHttpException();
    }

    Task::displayState($tasktypes_id);

}
