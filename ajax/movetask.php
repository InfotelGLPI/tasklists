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

if (isset($_POST['data_id'])
      && isset($_POST['data_destblock'])) {
   $task = new PluginTasklistsTask();
   if ($task->getFromDB($_POST['data_id'])) {
      $input['plugin_tasklists_taskstates_id'] = $_POST['data_destblock'];
      $input['id'] = $_POST['data_id'];

      if (($task->fields['users_id'] == Session::getLoginUserID() && Session::haveRight("plugin_tasklists", UPDATE))
          || Session::haveRight("plugin_tasklists_see_all", 1)) {
         $task->update($input);
      }
   }
}