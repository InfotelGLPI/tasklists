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

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_tasklists', UPDATE);

if (isset($_POST["entities_id"])) {
    // Every other entry point of the plugin types the posted entity and confronts it with the
    // caller's scope before using it; this fragment reflected it untouched, so the task form was
    // primed on a foreign entity until the write was finally refused further down.
    $entities_id = (int) $_POST["entities_id"];
    if (!Session::haveAccessToEntity($entities_id)) {
        throw new AccessDeniedHttpException();
    }
    echo Html::hidden('entities_id', ['value' => $entities_id]);
}
