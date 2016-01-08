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

 
class PluginTasklistsMenu extends CommonGLPI {
   static $rightname = 'plugin_tasklists';

   static function getMenuName($nb = 1) {
      return __('Tasks list', 'tasklists');
   }

   static function getMenuContent() {
      global $CFG_GLPI;

      $menu                                           = array();
      $menu['title']                                  = self::getMenuName(2);
      $menu['page']                                   = PluginTasklistsTask::getSearchURL(false);
      $menu['links']['search']                        = PluginTasklistsTask::getSearchURL(false);
      if (PluginTasklistsTask::canCreate()) {
         $menu['links']['add']                        = PluginTasklistsTask::getFormURL(false);
      }
      

      return $menu;
   }

   static function removeRightsFromSession() {
      if (isset($_SESSION['glpimenu']['helpdesk']['types']['PluginTasklistsMenu'])) {
         unset($_SESSION['glpimenu']['helpdesk']['types']['PluginTasklistsMenu']); 
      }
      if (isset($_SESSION['glpimenu']['helpdesk']['content']['plugintasklistsmenu'])) {
         unset($_SESSION['glpimenu']['helpdesk']['content']['plugintasklistsmenu']); 
      }
   }
}