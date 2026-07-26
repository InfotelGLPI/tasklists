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
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\Ticket;

Session::checkLoginUser();

$ticket = new Ticket();
if (isset($_POST["add"])) {
   $ticket->check(-1, CREATE, $_POST);

   // check(-1, CREATE) only validates the global plugin_tasklists right, not object access on
   // the client-supplied task id. Without this, a user with CREATE could forge a link to a task
   // outside their visibility scope and later read its name/content via the ticket's linked-tasks
   // tab (showForTicket). Gate the target task with the same READ + checkVisibility as task.form.php.
   $tasks_id = (int) ($_POST['plugin_tasklists_tasks_id'] ?? 0);
   $task     = new Task();
   if (!$task->can($tasks_id, READ) || !$task->checkVisibility($tasks_id)) {
      throw new AccessDeniedHttpException();
   }

   $ticket->add($_POST);
   Html::back();

}

throw new BadRequestHttpException("lost");
