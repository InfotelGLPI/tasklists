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

global $CFG_GLPI;

use Glpi\Plugin\Hooks;
use GlpiPlugin\Tasklists\Preference;
use GlpiPlugin\Tasklists\Dashboard;
use GlpiPlugin\Tasklists\Menu;
use GlpiPlugin\Tasklists\Profile;
use GlpiPlugin\Tasklists\Ticket;
use GlpiPlugin\Tasklists\Task;

define('PLUGIN_TASKLISTS_VERSION', '2.1.7');

if (!defined("PLUGIN_TASKLISTS_DIR")) {
    define("PLUGIN_TASKLISTS_DIR", Plugin::getPhpDir("tasklists"));
    //   define("PLUGIN_TASKLISTS_WEBDIR", Plugin::getPhpDir("tasklists",false));
    $root = $CFG_GLPI['root_doc'] . '/plugins/tasklists';
    define("PLUGIN_TASKLISTS_WEBDIR", $root);
}
// Init the hooks of the plugins -Needed
function plugin_init_tasklists()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS[Hooks::CHANGE_PROFILE]['tasklists'] = [Profile::class, 'initProfile'];
    $PLUGIN_HOOKS[Hooks::USE_RULES]['tasklists'] = ['RuleMailCollector'];
    //    $PLUGIN_HOOKS[Hooks::ADD_CSS]['tasklists'][]      = "kanban.css";
    if (Session::getLoginUserID()) {

        Plugin::registerClass(Task::class, [
            'document_types'              => true,
            'notificationtemplates_types' => true,
        ]);

        Plugin::registerClass(
            Ticket::class,
            ['addtabon' => \Ticket::class]
        );

        $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['tasklists'][\Ticket::class] = [Ticket::class, 'cleanForTicket'];

        Plugin::registerClass(
            Profile::class,
            ['addtabon' => 'Profile']
        );

        Plugin::registerClass(
            Preference::class,
            ['addtabon' => 'Preference']
        );

        if (Session::haveRight("plugin_tasklists", READ)) {
            $PLUGIN_HOOKS[Hooks::MENU_TOADD]['tasklists'] = ['helpdesk' => Menu::class];
        }

        if (class_exists('PluginMydashboardMenu')) {
            $PLUGIN_HOOKS['mydashboard']['tasklists'] = [Dashboard::class];
        }

        if (Session::haveRight("plugin_tasklists", CREATE)) {
            $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['tasklists'] = 1;
        }
    }
}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_tasklists()
{

    return [
        'name'         => __('Tasks list', 'tasklists'),
        'version'      => PLUGIN_TASKLISTS_VERSION,
        'license'      => 'GPLv2+',
        'author'       => "<a href='https//blogglpi.infotel.com'>Infotel</a>, Xavier CAILLAUD",
        'homepage'     => 'https://github.com/InfotelGLPI/tasklists',
        'requirements' => [
            'glpi' => [
                'min' => '11.0',
                'max' => '12.0',
                'dev' => false,
            ],
        ],
    ];

}
