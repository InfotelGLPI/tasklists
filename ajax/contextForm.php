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

use GlpiPlugin\Tasklists\TaskState;

Session::checkRight('plugin_tasklists_config', UPDATE);

Html::header_nocache();
header("Content-Type: text/html; charset=UTF-8");

// Same GLPI 10 path as ajax/seetask.php carried, removed for the same reason: it resolved to
// <glpi>/public/lib/tinymce.js and returned a 404, and the page this fragment is injected into
// has already loaded the editor through Html::includeHeader(), which requires it for every page.

if (isset($_GET['newContext'])) {
    $options = [
        'from_edit_ajax' => true,

        'withtemplate' => 0,
    ];
    $task    = new TaskState();
    $task->showForm(0, $options);

}
