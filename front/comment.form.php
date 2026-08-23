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
use GlpiPlugin\Tasklists\Menu;
use GlpiPlugin\Tasklists\Task;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}
if (!isset($_GET["withtemplate"])) {
    $_GET["withtemplate"] = "";
}

Html::header(Task::getTypeName(2), '', "helpdesk", Menu::class);

// IDOR read/write on Notepad: the controller only checked the global plugin right and then
// loaded an arbitrary $_GET['id'], exposing (and letting the Notepad form write) the internal
// notes of tasks from other entities or restricted visibility. Enforce object-level rights
// (can(READ) validates right + entity) plus the plugin visibility model before showForItem().
$tasks_id = (int) $_GET['id'];
$task     = new Task();
if (!$task->can($tasks_id, READ) || !$task->checkVisibility($tasks_id)) {
    throw new AccessDeniedHttpException();
}
$note = new Notepad();
$note->showForItem($task);

Html::footer();
