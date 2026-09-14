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

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_tasklists', UPDATE);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    // Get AJAX input and load it into $_REQUEST
    $input = file_get_contents('php://input');
    parse_str($input, $_REQUEST);
}

if (!isset($_REQUEST['action'])) {
    throw new BadRequestHttpException("Missing action parameter");
}
$action = $_REQUEST['action'];

// Security (CSRF bypass): the dispatch reads its action from $_REQUEST, which merges $_GET and
// $_POST, and two of the branches below are writes - they reassign $_SESSION["archive"] and
// $_SESSION["usersKanban"]. GLPI 11's CheckCsrfListener only validates the token on non-GET
// requests, so a state-changing action reachable over GET escapes CSRF protection entirely: an
// <img> tag on a third-party page rewrote the Kanban filters of any authenticated visitor, and
// the value persisted in his session. Reads stay on $_REQUEST, writes are restricted to POST.
if (in_array($action, ['changeArchive', 'changeUsers'], true)
    && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new BadRequestHttpException("This action requires a POST request");
}

if ($_REQUEST['action'] == 'addArchived') {

    header("Content-Type: application/json; charset=UTF-8", true);
    $states    = [];
    $states[0] = __("Not archived", 'tasklists');
    $states[1] = __("Archived", 'tasklists');

    if (!isset($_SESSION["archive"][Session::getLoginUserID()])) {
        $_SESSION["archive"][Session::getLoginUserID()] = json_encode([0]);
    }
    if ($_SESSION["archive"][Session::getLoginUserID()] != "" && $_SESSION["archive"][Session::getLoginUserID()] != "null") {
        $arch = Dropdown::showFromArray(
            "archive",
            $states,
            ['id'       => 'archive',
                'multiple' => true,
                'values'   => json_decode($_SESSION["archive"][Session::getLoginUserID()], true),
                "display"  => false],
        );
    } else {
        $arch = Dropdown::showFromArray("archive", $states, ['id'       => 'archive',
            'multiple' => true,
            'value'    => 0,
            "display"  => false]);

    }

    echo json_encode($arch, JSON_FORCE_OBJECT);

} elseif ($_REQUEST['action'] == 'changeArchive') {
    if (!empty($_REQUEST['vals'])) {
        // The value is json_encode()d into the session and read back by
        // TaskType::getKanbanColumns() to filter the board, so its shape was the client's all
        // the way: a nested array or a plain string survived the round trip and came back as
        // something in_array() could never match, emptying the Kanban of that user until the
        // session was dropped. Only the two values the dropdown offers are kept - 0 "Not
        // archived" and 1 "Archived".
        $archive_vals = array_values(array_intersect(
            array_map('intval', array_filter((array) $_REQUEST['vals'], 'is_scalar')),
            [0, 1],
        ));
        if ($archive_vals !== []) {
            $_SESSION["archive"][Session::getLoginUserID()] = json_encode($archive_vals);
        }
    }

} elseif ($_REQUEST['action'] == 'addUsers') {

    header("Content-Type: application/json; charset=UTF-8", true);
    // Was an if() opening a second chain, so the condition was re-evaluated after the
    // addArchived / changeArchive chain had already answered and two Content-Type headers could
    // be emitted on the same response. Also: context was read without isset() nor integer cast.
    // findUsers() applies the entity restriction and TypeVisibility::isUserHaveRight() itself
    // (src/TaskType.php:536-546), so nothing leaks, but the value has no business reaching it
    // untyped.
    $users = TaskType::findUsers((int) ($_REQUEST['context'] ?? 0));

    if (!isset($_SESSION["usersKanban"][Session::getLoginUserID()])) {
        $_SESSION["usersKanban"][Session::getLoginUserID()] = json_encode([-1]);
    }
    if ($_SESSION["usersKanban"][Session::getLoginUserID()] != "" && isset($_SESSION["archive"]) && $_SESSION["archive"][Session::getLoginUserID()] != "null") {
        $arch = Dropdown::showFromArray("usersKanban", $users, ['id' => 'users',
            'multiple' => true,
            'values' => json_decode($_SESSION["usersKanban"][Session::getLoginUserID()], true),
            "display" => false]);
    } else {
        $arch = Dropdown::showFromArray("usersKanban", $users, ['id' => 'users',
            'multiple' => true,
            'value' => -1,
            "display" => false]);
    }

    echo json_encode($arch, JSON_FORCE_OBJECT);

} elseif ($_REQUEST['action'] == 'changeUsers') {
    if (!empty($_REQUEST['vals'])) {
        // Same normalisation as changeArchive above, to a flat list of integers. -1 is the
        // pseudo value meaning "every user" that the addUsers branch seeds the filter with; the
        // others are user identifiers. The list is typed rather than matched against the offered
        // set, which would require re-posting the context: this is a display filter, the entity
        // and visibility boundaries being enforced by Task::checkVisibility() when the cards are
        // built.
        $users_vals = array_values(array_unique(array_map(
            'intval',
            array_filter((array) $_REQUEST['vals'], 'is_scalar'),
        )));
        if ($users_vals !== []) {
            $_SESSION["usersKanban"][Session::getLoginUserID()] = json_encode($users_vals);
        }
    }

}
