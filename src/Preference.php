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

use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Class Preference
 */
class Preference extends CommonDBTM
{
    public static $rightname = 'plugin_tasklists';

    /**
     * @param CommonGLPI $item
     * @param int        $withtemplate
     *
     * @return string|translated
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (Session::haveRight('plugin_tasklists', READ)
          && $item->getType() == 'Preference') {
            return self::createTabEntry(__('Tasks list', 'tasklists'));
        }
        return '';
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return Task::getIcon();
    }


    /**
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $pref = new self();
        $pref->showPreferenceForm(Session::getLoginUserID());
        return true;
    }

    /**
     * @param $user_id
     */
    public function showPreferenceForm($user_id)
    {
        //If user has no preferences yet, we set default values
        if (!$this->getFromDB($user_id)) {
            $this->initPreferences($user_id);
            $this->getFromDB($user_id);
        }

        //Preferences are not deletable
        $options['candel']  = false;
        $options['colspan'] = 1;

        $this->showFormHeader($options);

        $types               = TypeVisibility::seeAllowedTypes();
        $default_type_field  = Dropdown::show(TaskType::class, ['name'      => "default_type",
            'value'     => $this->fields['default_type'],
            'condition' => ["id" => $types],
            'display'   => false]);

        ob_start();
        Dropdown::showYesNo("automatic_refresh", $this->fields['automatic_refresh']);
        $automatic_refresh_field = ob_get_clean();

        $refresh_delay_field = Dropdown::showFromArray(
            "automatic_refresh_delay",
            [1 => 1, 2 => 2, 5 => 5, 10 => 10, 30 => 30, 60 => 60],
            ["value"   => $this->fields['automatic_refresh_delay'],
                'display' => false],
        );

        TemplateRenderer::getInstance()->display('@tasklists/preference/form.html.twig', [
            'default_type_field'      => $default_type_field,
            'automatic_refresh_field' => $automatic_refresh_field,
            'refresh_delay_field'     => $refresh_delay_field,
        ]);

        $this->showFormButtons($options);
    }

    /**
     * The preference primary key is the user id, so force it to the current user on
     * update to prevent an authenticated user from overwriting another user's settings
     * by posting a forged id (IDOR).
     *
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        $input['id'] = Session::getLoginUserID();
        return $input;
    }

    /**
     * @param $users_id
     */
    public function initPreferences($users_id)
    {

        $input                 = [];
        $input['id']           = $users_id;
        $input['default_type'] = "0";
        $this->add($input);
    }

    /**
     * @param $users_id
     *
     * @return int
     */
    public static function checkDefaultType($users_id)
    {
        return self::checkPreferenceValue('default_type', $users_id);
    }

    /**
     * @param     $field
     * @param int $users_id
     *
     * @return int
     */
    public static function checkPreferenceValue($field, $users_id = 0)
    {
        $dbu  = new DbUtils();
        $data = $dbu->getAllDataFromTable($dbu->getTableForItemType(__CLASS__), ["id" => $users_id]);
        if (!empty($data)) {
            $first = array_pop($data);
            if ($field != "default_type") {
                return $first[$field];
            }
            if ($first[$field] > 0) {
                return $first[$field];
            }
        }

        return self::getFirstVisibleTaskType();
    }


    /**
     * Get the first task type the current user is allowed to open on the kanban
     *
     * Both fallbacks of checkPreferenceValue() - no preference row at all, and a preference row
     * holding an empty default type - carried a byte-identical copy of this lookup.
     *
     * @return int task type id, 0 when the user is allowed on none
     */
    private static function getFirstVisibleTaskType()
    {
        foreach (array_keys(TaskType::getAllForKanban()) as $tasktypes_id) {
            if (TypeVisibility::isUserHaveRight($tasktypes_id)) {
                return (int) $tasktypes_id;
            }
        }

        return 0;
    }
}
