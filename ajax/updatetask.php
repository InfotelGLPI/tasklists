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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Tasklists\Task;

Session::checkLoginUser();
Session::checkRight('plugin_tasklists', UPDATE);

// IDOR write: these branches only checked the global plugin right and then updated an
// arbitrary $_POST['data_id'], so a user could change the percent/priority/archive flag
// of any task in any entity (and archivealltasks mass-archived across every entity).
// Every mutation now requires object-level rights (can(UPDATE) enforces right + entity)
// plus the plugin visibility model (own task / own group / public); ids are cast to int.
if (isset($_POST['data_id'])
    && isset($_POST['percent_done'])) {
   $tasks_id = (int) $_POST['data_id'];
   $task     = new Task();
   if (!$task->can($tasks_id, UPDATE) || !$task->checkVisibility($tasks_id)) {
      throw new AccessDeniedHttpException();
   }
   $task->update(['id' => $tasks_id, 'percent_done' => (int) $_POST['percent_done']]);

} else if (isset($_POST['data_id'])
           && isset($_POST['updatepriority'])) {
   $tasks_id = (int) $_POST['data_id'];
   $task     = new Task();
   if (!$task->can($tasks_id, UPDATE) || !$task->checkVisibility($tasks_id)) {
      throw new AccessDeniedHttpException();
   }
   if ($task->fields["priority"] < 5) {
      $task->update(['id' => $tasks_id, 'priority' => $task->fields["priority"] + 1]);
   }
} else if (isset($_POST['data_id'])
           && isset($_POST['archivetask'])) {
   $tasks_id = (int) $_POST['data_id'];
   $task     = new Task();
   if (!$task->can($tasks_id, UPDATE) || !$task->checkVisibility($tasks_id)) {
      throw new AccessDeniedHttpException();
   }
   $task->update(['id' => $tasks_id, 'is_archived' => 1]);

} else if (isset($_POST['archivealltasks'])
           && isset($_POST['state_id'])
           && isset($_POST['context_id'])) {

   $task  = new Task();
   $dbu   = new DbUtils();
   $cond  = ["plugin_tasklists_taskstates_id" => (int) $_POST['state_id'],
             "plugin_tasklists_tasktypes_id"  => (int) $_POST['context_id'],
             "is_deleted"                     => 0,
             "is_archived"                    => 0];
   $tasks = $dbu->getAllDataFromTable($dbu->getTableForItemType(Task::class),
                                      $cond);
   foreach ($tasks as $key => $row) {
      // Only archive tasks the caller may actually update and see (entity + visibility).
      if ($task->can((int) $row['id'], UPDATE) && $task->checkVisibility((int) $row['id'])) {
         $task->update(['id' => (int) $row['id'], 'is_archived' => 1]);
      }
   }
}
