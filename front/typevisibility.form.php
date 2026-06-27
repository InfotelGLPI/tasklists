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

use GlpiPlugin\Tasklists\TypeVisibility;

$group = new TypeVisibility();

if (isset($_POST["add_groups"])) {
    $group->check(-1, UPDATE, $_POST);
    //add groups
    foreach ($_POST['groups_id'] as $groups_id) {
        $group->add(['groups_id'                     => $groups_id,
            'plugin_tasklists_tasktypes_id' => $_POST['plugin_tasklists_tasktypes_id']]);
    }
    Html::back();
}
