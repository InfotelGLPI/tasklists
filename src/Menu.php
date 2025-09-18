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

namespace GlpiPlugin\Tasklists;

use CommonGLPI;
use Session;

/**
 * Class Menu
 */
class Menu extends CommonGLPI
{
    public static $rightname = 'plugin_tasklists';

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getMenuName($nb = 1)
    {
        return __('Tasks list', 'tasklists');
    }

    /**
     * @return array
     */
    public static function getMenuContent()
    {

        $url             = "";
        $default_context = 0;
        if (class_exists(Preference::class)) {
            $default_context = Preference::checkDefaultType(Session::getLoginUserID());
        }
        if ($default_context > 0) {
            $url = "?itemtype=GlpiPlugin\Tasklists\Kanban&glpi_tab=GlpiPlugin\Tasklists\Kanban$" . $default_context;
        }

        $menu          = [];
        $menu['title'] = self::getMenuName(2);
        $menu['page']  = Kanban::getSearchURL(false) ;

        $menu['links']['search'] = Task::getSearchURL(false);
        if (Task::canCreate()) {
            $menu['links']['add']      = PLUGIN_TASKLISTS_WEBDIR . '/front/setup.templates.php?add=1';
            $menu['links']['template'] = PLUGIN_TASKLISTS_WEBDIR . '/front/setup.templates.php?add=0';
        }
        $menu['links']['summary'] = Kanban::getSearchURL(false);

        $menu['icon']    = self::getIcon();

        return $menu;
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return "ti ti-layout-kanban";
    }

    public static function removeRightsFromSession()
    {
        if (isset($_SESSION['glpimenu']['helpdesk']['types'][Menu::class])) {
            unset($_SESSION['glpimenu']['helpdesk']['types'][Menu::class]);
        }
        if (isset($_SESSION['glpimenu']['helpdesk']['content'][Menu::class])) {
            unset($_SESSION['glpimenu']['helpdesk']['content'][Menu::class]);
        }
    }
}
