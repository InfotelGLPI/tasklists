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

use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Html;
use ProfileRight;
use Session;

/**
 * Class Profile
 */
class Profile extends \Profile
{
    public static $rightname = "profile";

    /**
     * @param CommonGLPI $item
     * @param int        $withtemplate
     *
     * @return string|translated
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Profile'
            && $item->getField('interface') != 'helpdesk'
        ) {
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
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (!$item instanceof \Profile || !self::canView()) {
            return false;
        }

        $profile = new \Profile();
        $profile->getFromDB($item->getID());

        $rights = self::getAllRights(true);

        $twig = TemplateRenderer::getInstance();
        $twig->display('@tasklists/profile.html.twig', [
            'id' => $item->getID(),
            'profile' => $profile,
            'title' => self::getTypeName(Session::getPluralNumber()),
            'rights' => $rights,
        ]);

        return true;
    }

    /**
     * @param $ID
     */
    public static function createFirstAccess($ID)
    {
        //85
        self::addDefaultProfileInfos(
            $ID,
            ['plugin_tasklists'         => ALLSTANDARDRIGHT + READNOTE + UPDATENOTE,
                'plugin_tasklists_see_all' => READ,
                'plugin_tasklists_config'  => READ | CREATE | UPDATE | PURGE],
            true,
        );
    }

    /**
     * @param      $profiles_id
     * @param      $rights
     * @param bool $drop_existing
     *
     * @internal param $profile
     */
    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $dbu          = new DbUtils();
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if ($dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id, "name" => $right],
            ) && $drop_existing) {
                $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
            }
            if (!$dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id, "name" => $right],
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    /**
     * @param bool $all
     *
     * @return array
     */
    public static function getAllRights($all = false)
    {
        $rights = [
            ['itemtype' => Task::class,
                'label'    => Task::getTypeName(2),
                'field'    => 'plugin_tasklists',
            ],
        ];
        if ($all) {

            $rights[] = ['itemtype' => Task::class,
                'label'    => __('See and update all tasks', 'tasklists'),
                'field'    => 'plugin_tasklists_see_all',
                'rights' => [
                    READ => __('Read'),
                ]];

            // The write bits have to be offered by the matrix now that TaskState no longer
            // overrides its canCreate()/canUpdate()/canDelete()/canPurge() with a READ test.
            // The right used to carry READ alone, which is why those overrides existed at all:
            // the only way to make the statuses editable was to accept the read bit everywhere.
            $rights[] = ['itemtype' => Task::class,
                'label'    => __('Configure contexts and statuses', 'tasklists'),
                'field'    => 'plugin_tasklists_config',
                'rights' => [
                    READ   => __('Read'),
                    CREATE => _x('button', 'Add'),
                    UPDATE => __('Update'),
                    PURGE  => _x('button', 'Delete permanently'),
                ]];
        }
        return $rights;
    }

    /**
     * Init profiles
     *
     * @param $old_right
     *
     * @return int
     */

    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case '':
                return 0;
            case 'r':
                return READ;
            case 'w':
                return ALLSTANDARDRIGHT + READNOTE + UPDATENOTE;
            case '0':
            case '1':
                return $old_right;

            default:
                return 0;
        }
    }


    /**
     * Initialize profiles, and migrate it necessary
     */
    public static function initProfile()
    {
        global $DB;
        $profile = new self();
        $dbu     = new DbUtils();
        //Add new rights in glpi_profilerights table
        foreach ($profile->getAllRights(true) as $data) {
            if ($dbu->countElementsInTable(
                "glpi_profilerights",
                ["name" => $data['field']],
            ) == 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }
        $options = [
            'FROM'  => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                // Written as a plain string, this criterion compiled to a strict equality on
                // the literal "LIKE '%plugin_tasklists%'" - DBmysqlIterator::analyseCriterion
                // treats a scalar string as a value, not as an operator - so the iterator was
                // always empty and the reload below was dead code. The array form is what the
                // query builder expects for an operator.
                'name' => ['LIKE', '%plugin_tasklists%'],
            ],
        ];
        foreach ($DB->request($options) as $prof) {
            // Vérifier si la session est active
            if (isset($_SESSION['glpiactiveprofile'])) {
                $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
            }
        }
    }


    public static function removeRightsFromSession()
    {
        foreach (self::getAllRights(true) as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }
}
