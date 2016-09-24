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
 
// Init the hooks of the plugins -Needed
function plugin_init_tasklists() {
   global $PLUGIN_HOOKS,$CFG_GLPI;
   
   $PLUGIN_HOOKS['csrf_compliant']['tasklists'] = true;
   $PLUGIN_HOOKS['change_profile']['tasklists'] = array('PluginTasklistsProfile','initProfile');
   
   $PLUGIN_HOOKS['use_rules']['tasklists'] = array('RuleMailCollector');
   
   if (Session::getLoginUserID()) {
      
      Plugin::registerClass('PluginTasklistsTask', array(
         'linkuser_types' => true,
         'linkgroup_types' => true
      ));
      
      Plugin::registerClass('PluginTasklistsProfile',
                         array('addtabon' => 'Profile'));
      
      if (Session::haveRight("plugin_tasklists", READ)) {
         $PLUGIN_HOOKS['menu_toadd']['tasklists'] = array('helpdesk'   => 'PluginTasklistsMenu');
      }
      
      if (class_exists('PluginMydashboardMenu')) {
         $PLUGIN_HOOKS['mydashboard']['tasklists'] = array ("PluginTasklistsDashboard");
      }
      
      if (Session::haveRight("plugin_tasklists", CREATE)) {
         $PLUGIN_HOOKS['use_massive_action']['tasklists']=1;
      }
   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_tasklists() {

   return array (
      'name' => __('Tasks list', 'tasklists'),
      'version' => '1.1.0',
      'license' => 'GPLv2+',
      'author'  => "<a href='http://infotel.com/services/expertise-technique/glpi/'>Infotel</a>",
      'homepage'=>'https://github.com/InfotelGLPI/tasklists',
      'minGlpiVersion' => '0.85',// For compatibility / no install in version < 0.80
   );

}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_tasklists_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '0.85', 'lt') || version_compare(GLPI_VERSION, '9.2', 'ge')) {
      _e('This plugin requires GLPI >= 0.85', 'tasklists');
      return false;
   }
   return true;
}

// Uninstall process for plugin : need to return true if succeeded : may display messages or add to message after redirect
function plugin_tasklists_check_config() {
   return true;
}

?>