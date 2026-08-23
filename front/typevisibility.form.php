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

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Tasklists\TaskType;
use GlpiPlugin\Tasklists\TypeVisibility;

$group = new TypeVisibility();

if (isset($_POST["add_groups"])) {
    $group->check(-1, UPDATE, $_POST);

    // Validate the submitted task type and enforce entity isolation on it.
    $tasktypes_id = (int) ($_POST['plugin_tasklists_tasktypes_id'] ?? 0);
    $tasktype     = new TaskType();
    if ($tasktypes_id <= 0 || !$tasktype->getFromDB($tasktypes_id)) {
        throw new BadRequestHttpException("Invalid task type");
    }
    if (!Session::haveAccessToEntity(
        $tasktype->fields['entities_id'],
        $tasktype->fields['is_recursive'],
    )) {
        throw new BadRequestHttpException("Task type out of entity scope");
    }

    // groups_id must be an array of group ids.
    if (!isset($_POST['groups_id']) || !is_array($_POST['groups_id'])) {
        throw new BadRequestHttpException("Missing or invalid groups_id parameter");
    }

    // Add groups, skipping any group the current user cannot access.
    $groupitem = new Group();
    foreach ($_POST['groups_id'] as $groups_id) {
        $groups_id = (int) $groups_id;
        if ($groups_id <= 0 || !$groupitem->getFromDB($groups_id)) {
            continue;
        }
        if (!Session::haveAccessToEntity(
            $groupitem->fields['entities_id'],
            $groupitem->fields['is_recursive'],
        )) {
            continue;
        }
        $group->add([
            'groups_id'                     => $groups_id,
            'plugin_tasklists_tasktypes_id' => $tasktypes_id,
        ]);
    }
    Html::back();
}
