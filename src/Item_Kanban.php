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

namespace GlpiPlugin\Tasklists;

use CommonDBRelation;
use Session;

class Item_Kanban extends CommonDBRelation
{
    public static $itemtype_1         = 'itemtype';
    public static $items_id_1         = 'items_id';
    public static $itemtype_2         = 'User';
    public static $items_id_2         = 'users_id';
    public static $checkItem_1_Rights = 'plugin_tasklists';


    /**
     * Load the state of a Kanban's column for a specific kanban for the current user
     *
     * @param string $itemtype Type of the item.
     * @param int    $items_id ID of the item.
     * @param int    $plugin_tasklists_taskstates_id column id
     * @param string $timestamp Timestamp string of last check or null to always get the state.
     *
     * @return return the state of the collummn for the user
     *          if the state doesn't exist it is created
     * @since 9.5.0
     */
    public static function loadStateForItem($itemtype, $items_id, $plugin_tasklists_taskstates_id, $timestamp = null)
    {
        global $DB;


        $item = new self();
        if ($item->getFromDBByCrit([
            'users_id'                       => Session::getLoginUserID(),
            'itemtype'                       => $itemtype,
            'items_id'                       => $items_id,
            'plugin_tasklists_taskstates_id' => $plugin_tasklists_taskstates_id,
        ])) {
            return $item->getField('state');

        } else {
            $input = [
                'users_id'                       => Session::getLoginUserID(),
                'itemtype'                       => $itemtype,
                'items_id'                       => $items_id,
                'state'                          => false,
                'plugin_tasklists_taskstates_id' => $plugin_tasklists_taskstates_id,
                'date_creation'                  => $_SESSION['glpi_currenttime'],
                'date_mod'                       => $_SESSION['glpi_currenttime'],
            ];
            $item->add($input);
            return false;
        }
    }


    /**
     * Fold a Kanban column for the current user
     *
     * @param string $itemtype Type of the item.
     * @param int    $items_id ID of the item.
     * @param int    $column   column id
     *
     * @return bool
     */
    public static function collapseColumn($itemtype, $items_id, $column)
    {
        return self::setColumnState($itemtype, $items_id, $column, true);
    }

    /**
     * Unfold a Kanban column for the current user
     *
     * @param string $itemtype Type of the item.
     * @param int    $items_id ID of the item.
     * @param int    $column   column id
     *
     * @return bool
     */
    public static function expandColumn($itemtype, $items_id, $column)
    {
        return self::setColumnState($itemtype, $items_id, $column, false);
    }

    /**
     * Persist the folded state of one Kanban column for the current user
     *
     * getFromDBByCrit() returns false as long as the row has not been materialised, which is the
     * nominal case the first time a user folds a column: $item->fields was then empty, update()
     * received a payload without id and silently did nothing, so the preference was lost on the
     * next reload. loadStateForItem() creates the missing row, after which the read succeeds and
     * the outcome of the write is returned to the caller.
     *
     * @param string $itemtype Type of the item.
     * @param int    $items_id ID of the item.
     * @param int    $column   column id
     * @param bool   $state    true to fold, false to unfold
     *
     * @return bool
     */
    private static function setColumnState($itemtype, $items_id, $column, $state)
    {
        $crit = [
            'users_id'                       => Session::getLoginUserID(),
            'itemtype'                       => $itemtype,
            'items_id'                       => $items_id,
            'plugin_tasklists_taskstates_id' => $column,
        ];

        $item = new self();
        if (!$item->getFromDBByCrit($crit)) {
            self::loadStateForItem($itemtype, $items_id, $column);
            if (!$item->getFromDBByCrit($crit)) {
                return false;
            }
        }

        return $item->update([
            'id'       => $item->getID(),
            'state'    => $state,
            'date_mod' => $_SESSION['glpi_currenttime'],
        ]);
    }
}
