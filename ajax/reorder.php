<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Tasklists plugin for GLPI
 Copyright (C) 2003-2016 by the Tasklists Development Team.

 https://github.com/InfotelGLPI/tasklists
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Tasklists.

 Tasklists is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Tasklists is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Tasklists. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include("../../../inc/includes.php");

Session::checkLoginUser();

if (
    !array_key_exists('plugin_tasklists_tasktypes_id', $_POST)
    || !array_key_exists('old_order', $_POST)
    || !array_key_exists('new_order', $_POST)
) {
    // Missing input
    exit();
}

$table        = PluginTasklistsStateOrder::getTable();
$plugin_tasklists_tasktypes_id = (int) $_POST['plugin_tasklists_tasktypes_id'];
$old_order    = (int) $_POST['old_order'];
$new_order    = (int) $_POST['new_order'];

/** @var DBmysql $DB */
global $DB;

// Retrieve id of field to update
$field_iterator = $DB->request(
    [
        'SELECT' => 'id',
        'FROM'   => $table,
        'WHERE'  => [
            'plugin_tasklists_tasktypes_id' => $plugin_tasklists_tasktypes_id,
            'ranking'                     => $old_order,
        ],
    ],
);

if (0 === $field_iterator->count()) {
    // Unknown field
    exit();
}

$field_id = $field_iterator->current()['id'];

// Move all elements to their new ranking
if ($old_order < $new_order) {
    $DB->update(
        $table,
        [
            'ranking' => new \QueryExpression($DB->quoteName('ranking') . ' - 1'),
        ],
        [
            'plugin_tasklists_tasktypes_id' => $plugin_tasklists_tasktypes_id,
            ['ranking'                    => ['>',  $old_order]],
            ['ranking'                    => ['<=', $new_order]],
        ],
    );
} else {
    $DB->update(
        $table,
        [
            'ranking' => new \QueryExpression($DB->quoteName('ranking') . ' + 1'),
        ],
        [
            'plugin_fields_containers_id' => $plugin_tasklists_tasktypes_id,
            ['ranking'                    => ['<',  $old_order]],
            ['ranking'                    => ['>=', $new_order]],
        ],
    );
}

// Update current element
$DB->update(
    $table,
    [
        'ranking' => $new_order,
    ],
    [
        'id' => $field_id,
    ],
);
