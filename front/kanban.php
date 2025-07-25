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

use Glpi\Exception\Http\AccessDeniedHttpException;

Html::header(PluginTasklistsTask::getTypeName(2), '', "helpdesk", "plugintasklistsmenu");

$kanban = new PluginTasklistsKanban();

if ($kanban->canView() || Session::haveRight("config", CREATE)) {
   //AS module for SearchTokenizer
//   echo "<script type='module' src='../../../js/modules/Kanban/Kanban.js'></script>";
    echo "<script src='".$CFG_GLPI['root_doc']."/plugins/tasklists/lib/kanban/js/SearchTokenizer/SearchTokenizerResult.js'></script>";
    echo "<script src='".$CFG_GLPI['root_doc']."/plugins/tasklists/lib/kanban/js/SearchTokenizer/SearchToken.js'></script>";
    echo "<script src='".$CFG_GLPI['root_doc']."/plugins/tasklists/lib/kanban/js/SearchTokenizer/SearchTokenizer.js'></script>";
    echo "<script src='".$CFG_GLPI['root_doc']."/plugins/tasklists/lib/kanban/js/SearchTokenizer/SearchInput.js'></script>";
    echo "<script src='".$CFG_GLPI['root_doc']."/plugins/tasklists/lib/kanban/js/Kanban.js'></script>";

   Html::requireJs('sortable');
//   Html::requireJs('kanban');
   echo Html::css(PLUGIN_TASKLISTS_NOTFULL_DIR . '/lib/kanban/css/kanban.css');
//   echo Html::script(PLUGIN_TASKLISTS_NOTFULL_DIR . "/lib/kanban/js/kanban-actions.js");
   if (!isset($_GET["context_id"])) {
      $_GET["context_id"] = -1;
   }
   PluginTasklistsKanban::showKanban($_GET["context_id"]);

} else {
    throw new AccessDeniedHttpException();
}

Html::footer();
