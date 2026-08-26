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

Session::checkRight("plugin_tasklists", READ);

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Tasklists\Menu;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\Ticket;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}
if (!isset($_GET["withtemplate"])) {
    $_GET["withtemplate"] = "";
}

$task = new Task();

if (isset($_POST["add"])) {
    $task->check(-1, CREATE, $_POST);
    $newID = $task->add($_POST);
    //if ($_SESSION['glpibackcreated']) {
    //   Html::redirect($task->getFormURL() . "?id=" . $newID);
    //}
    Html::back();
} elseif (isset($_POST["delete"])) {
    $task->check($_POST['id'], DELETE);
    // can() validates the global right and entity but not the plugin visibility model;
    // enforce checkVisibility() on write paths too (as ajax/updatetask.php already does)
    // so a same-entity user cannot mutate another user's private task.
    if (!$task->checkVisibility((int) $_POST['id'])) {
        throw new AccessDeniedHttpException();
    }
    $task->delete($_POST);
    if (!isset($_POST["from_edit_ajax"])) {
        $task->redirectToList();
    } else {
        Html::back();
    }

} elseif (isset($_POST["restore"])) {
    $task->check($_POST['id'], PURGE);
    if (!$task->checkVisibility((int) $_POST['id'])) {
        throw new AccessDeniedHttpException();
    }
    $task->restore($_POST);
    $task->redirectToList();

} elseif (isset($_POST["purge"])) {
    $task->check($_POST['id'], PURGE);
    if (!$task->checkVisibility((int) $_POST['id'])) {
        throw new AccessDeniedHttpException();
    }
    $task->delete($_POST, 1);
    if (!isset($_POST["from_edit_ajax"])) {
        $task->redirectToList();
    } else {
        Html::back();
    }

} elseif (isset($_POST["update"])) {
    $task->check($_POST['id'], UPDATE);
    if (!$task->checkVisibility((int) $_POST['id'])) {
        throw new AccessDeniedHttpException();
    }
    $task->update($_POST);
    Html::back();

} elseif (isset($_POST["done"])) {
    $task->check($_POST['id'], UPDATE);
    if (!$task->checkVisibility((int) $_POST['id'])) {
        throw new AccessDeniedHttpException();
    }
    $options['id']           = $_POST['id'];
    $options['state']        = 2;
    $options['percent_done'] = 100;
    $task->update($options);
    Html::back();

} elseif (isset($_POST["ticket_link"])) {

    $ticket = new Ticket();
    $task   = new Task();
    $task->check($_POST['plugin_tasklists_tasks_id'], UPDATE);
    if (!$task->checkVisibility((int) $_POST['plugin_tasklists_tasks_id'])) {
        throw new AccessDeniedHttpException();
    }
    // The target ticket id comes from the client: the UI dropdown is entity-restricted but
    // that is client-side only. Require READ on the ticket so a forged id cannot link (and
    // later disclose the metadata of) a ticket outside the caller's scope.
    $tickets_id = (int) $_POST['tickets_id'];
    $linked_ticket = new \Ticket();
    if (!$linked_ticket->can($tickets_id, READ)) {
        throw new AccessDeniedHttpException();
    }
    $ticket->add(['tickets_id'                => $tickets_id,
        'plugin_tasklists_tasks_id' => (int) $_POST['plugin_tasklists_tasks_id']]);
    Html::back();

} else {

    $task->checkGlobal(READ);

    // IDOR read: for an existing task, display() resolves to CommonGLPI::display() ->
    // can($id, READ), which only checks entity membership (Task overrides neither
    // canViewItem() nor display()) and never applies the plugin visibility model.
    // Enforce it here - mirroring every mutation branch above and ajax/seetask.php - so a
    // same-entity user cannot view another user's private/group task via a forged id.
    // ($id <= 0 is the blank creation form, which carries no task to disclose.)
    $id = (int) $_GET["id"];
    if ($id > 0 && (!$task->can($id, READ) || !$task->checkVisibility($id))) {
        throw new AccessDeniedHttpException();
    }

    Html::header(Task::getTypeName(2), '', "helpdesk", Menu::class);

    Html::requireJs('tinymce');
    $task->display(['id' => $_GET["id"], 'withtemplate' => $_GET["withtemplate"]]);

    Html::footer();
}
