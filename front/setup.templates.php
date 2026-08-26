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

global $CFG_GLPI;
$task = new Task();

if ($task->canView() || Session::haveRight("config", UPDATE)) {
    Html::header(Task::getTypeName(2), '', "helpdesk", Menu::class);

    // Default the flag when the controller is reached without ?add=... : other
    // controllers initialise their $_GET params likewise, and this avoids the PHP 8
    // "Undefined array key" notice plus a non-deterministic mode downstream.
    $add = (int) ($_GET["add"] ?? 0);
    $task->listOfTemplates($CFG_GLPI['root_doc'] . "/plugins/tasklists/front/task.form.php", $add);

    Html::footer();
} else {
    throw new AccessDeniedHttpException();
}
