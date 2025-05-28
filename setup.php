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

global $CFG_GLPI;

use Glpi\Plugin\Hooks;

define('PLUGIN_TASKLISTS_VERSION', '2.0.4');

if (!defined("PLUGIN_TASKLISTS_DIR")) {
   define("PLUGIN_TASKLISTS_DIR", Plugin::getPhpDir("tasklists"));
   define("PLUGIN_TASKLISTS_NOTFULL_DIR", Plugin::getPhpDir("tasklists",false));
}
// Init the hooks of the plugins -Needed
function plugin_init_tasklists() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['tasklists'] = true;
   $PLUGIN_HOOKS['change_profile']['tasklists'] = ['PluginTasklistsProfile', 'initProfile'];
   $PLUGIN_HOOKS['use_rules']['tasklists'] = ['RuleMailCollector'];
//    $PLUGIN_HOOKS[Hooks::ADD_CSS]['tasklists'][]      = "kanban.css";
   if (Session::getLoginUserID()) {

      Plugin::registerClass('PluginTasklistsTask', [
//         'linkuser_types'              => true,
//         'linkgroup_types'             => true,
         'document_types'              => true,
         'notificationtemplates_types' => true
      ]);

      Plugin::registerClass('PluginTasklistsTicket',
                            ['addtabon' => 'Ticket']);

      $PLUGIN_HOOKS['item_purge']['tasklists']['Ticket'] = ['PluginTasklistsTaskTicket', 'cleanForTicket'];

      Plugin::registerClass('PluginTasklistsProfile',
                            ['addtabon' => 'Profile']);

      Plugin::registerClass('PluginTasklistsPreference',
                            ['addtabon' => 'Preference']);

      if (Session::haveRight("plugin_tasklists", READ)) {
         $PLUGIN_HOOKS['menu_toadd']['tasklists'] = ['helpdesk' => 'PluginTasklistsMenu'];
      }

      if (class_exists('PluginMydashboardMenu')) {
         $PLUGIN_HOOKS['mydashboard']['tasklists'] = ["PluginTasklistsDashboard"];
      }

      if (Session::haveRight("plugin_tasklists", CREATE)) {
         $PLUGIN_HOOKS['use_massive_action']['tasklists'] = 1;
      }
   }
}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_tasklists() {

   return [
      'name'         => __('Tasks list', 'tasklists'),
      'version'      => PLUGIN_TASKLISTS_VERSION,
      'license'      => 'GPLv2+',
      'author'       => "<a href='http://blogglpi.infotel.com'>Infotel</a>",
      'homepage'     => 'https://github.com/InfotelGLPI/tasklists',
      'requirements' => [
         'glpi' => [
            'min' => '11.0',
            'max' => '12.0',
            'dev' => false
         ]
      ]
   ];

}
