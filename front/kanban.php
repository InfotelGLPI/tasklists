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

global $CFG_GLPI;

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Tasklists\Menu;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\Kanban;

$kanban = new Kanban();

// The refusal used to come after Html::header(), so an unauthorised caller was served a
// complete page shell - menu, breadcrumb, title - before the 403, which both blurs the refusal
// and hands out a little enumeration. Nothing is emitted before the right is settled.
if (!$kanban->canView() && !Session::haveRight("config", CREATE)) {
    throw new AccessDeniedHttpException();
}

Html::header(Task::getTypeName(2), '', "helpdesk", Menu::class);

//AS module for SearchTokenizer
//   echo "<script type='module' src='../../../js/modules/Kanban/Kanban.js'></script>";
// Html::script() adds the nonce of the Content Security Policy, which a <script> tag echoed by
// hand lacks. It prefixes the root_doc itself, hence the path without it (PLUGIN_TASKLISTS_WEBDIR
// already holds it), and the unminified files, as loaded before.
foreach ([
    'SearchTokenizer/SearchTokenizerResult.js',
    'SearchTokenizer/SearchToken.js',
    'SearchTokenizer/SearchTokenizer.js',
    'SearchTokenizer/SearchInput.js',
    'Kanban.js',
] as $kanban_script) {
    echo Html::script('/plugins/tasklists/lib/kanban/js/' . $kanban_script, ['version' => PLUGIN_TASKLISTS_VERSION], false);
}

Html::requireJs('sortable');
//   Html::requireJs('kanban');
echo Html::css(PLUGIN_TASKLISTS_WEBDIR . '/lib/kanban/css/kanban.css');
//   echo Html::script(PLUGIN_TASKLISTS_WEBDIR . "/lib/kanban/js/kanban-actions.js");
if (!isset($_GET["context_id"])) {
    $_GET["context_id"] = -1;
}
Kanban::showKanban((int) $_GET["context_id"]);

Html::footer();
