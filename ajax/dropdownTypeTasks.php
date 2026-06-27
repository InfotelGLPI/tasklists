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

use GlpiPlugin\Tasklists\Task;

if (strpos($_SERVER['PHP_SELF'], "dropdownTypeTasks.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkCentralAccess();
Session::checkRight('plugin_tasklists', UPDATE);

global $DB;

// Make a select box
if (isset($_POST["tasktypes"])) {
    $used = [];

// Clean used array
    if (
        isset($_POST['used'])
        && is_array($_POST['used'])
        && (count($_POST['used']) > 0)
    ) {
        $options = [
            'id' => $_POST['used'],
            'plugin_tasklists_tasktypes_id' => $_POST['tasktypes']
        ];
        foreach (
            $DB->request([
                'FROM'  => 'glpi_plugin_tasklists_tasks',
                'WHERE' => $options
            ]) as $data
        ) {
            $used[$data['id']] = $data['id'];
        }
    }


    Dropdown::show(
        Task::class,
        [
            'name' => $_POST['myname'],
            'used' => $used,
            'width' => '50%',
            'entity' => $_POST['entity'],
            'rand' => $_POST['rand'],
            'condition' => ["glpi_plugin_tasklists_tasks.plugin_tasklists_tasktypes_id" => $_POST["tasktypes"]]
        ]
    );
}
