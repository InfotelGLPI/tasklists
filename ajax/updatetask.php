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

Session::checkLoginUser();
Session::checkRight('plugin_tasklists', UPDATE);

if (isset($_POST['data_id'])
    && isset($_POST['percent_done'])) {
   $task                  = new Task();
   $input['percent_done'] = $_POST['percent_done'];
   $input['id']           = $_POST['data_id'];
   $task->update($input);

} else if (isset($_POST['data_id'])
           && isset($_POST['updatepriority'])) {
   $task = new Task();
   if ($task->getFromDB($_POST['data_id'])) {
      if ($task->fields["priority"] < 5) {
         $input['priority'] = $task->fields["priority"] + 1;
      }
      $input['id'] = $_POST['data_id'];
      $task->update($input);
   }
} else if (isset($_POST['data_id'])
           && isset($_POST['archivetask'])) {
   $task                 = new Task();
   $input['is_archived'] = 1;
   $input['id']          = $_POST['data_id'];
   $task->update($input);

} else if (isset($_POST['archivealltasks'])
           && isset($_POST['state_id'])
           && isset($_POST['context_id'])) {

   $task  = new Task();
   $dbu   = new DbUtils();
   $cond  = ["plugin_tasklists_taskstates_id" => $_POST['state_id'],
             "plugin_tasklists_tasktypes_id"  => $_POST['context_id'],
             "is_deleted"                     => 0,
             "is_archived"                    => 0];
   $tasks = $dbu->getAllDataFromTable($dbu->getTableForItemType(Task::class),
                                      $cond);
   foreach ($tasks as $key => $row) {
      if ($task->getFromDB($row['id'])) {
         $input['is_archived'] = 1;
         $input['id']          = $row['id'];
         $task->update($input);
      }
   }
}
