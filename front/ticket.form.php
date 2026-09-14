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
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\Ticket;

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

    // The other end of the link was never validated. check(-1, CREATE) above reads the global
    // plugin_tasklists right only - this class is a plain link CommonDBTM whose $rightname is
    // plugin_tasklists, so nothing in it ever looks at the core ticket - and $_POST['tickets_id']
    // went verbatim into the insert. The twin path front/task.form.php, which performs the same
    // linking from the task form, does gate it with can($tickets_id, READ): the two controllers
    // diverged on one and the same operation. Without this, any holder of the plugin CREATE right
    // could hang one of their own tasks off any ticket of the instance, and the ticket's
    // linked-tasks tab and its notifications would carry it to that ticket's actors.
    $tickets_id  = (int) ($_POST['tickets_id'] ?? 0);
    $core_ticket = new \Ticket();
    if (!$core_ticket->can($tickets_id, READ)) {
        throw new AccessDeniedHttpException();
    }

    // The link is made of exactly two columns, both now validated and cast; handing over the
    // whole $_POST let the client name any other field of the relation table.
    $ticket->add([
        'tickets_id'                => $tickets_id,
        'plugin_tasklists_tasks_id' => $tasks_id,
    ]);
    Html::back();

}

throw new BadRequestHttpException("lost");
