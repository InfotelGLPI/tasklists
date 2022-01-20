<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

$AJAX_INCLUDE = 1;

include('../../../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

use Glpi\Application\View\TemplateRenderer;

//if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
//   // Get AJAX input and load it into $_REQUEST
//   $input = file_get_contents('php://input');
//   parse_str($input, $_REQUEST);
//}

if (!isset($_REQUEST['action'])) {
   Glpi\Http\Response::sendError(400, "Missing action parameter", Glpi\Http\Response::CONTENT_TYPE_TEXT_HTML);
}

$action = $_REQUEST['action'];

//Infotel add get_url && get_switcher_dropdown into array
$nonkanban_actions = ['update', 'bulk_add_item', 'add_item', 'move_item', 'show_card_edit_form', 'delete_item', 'load_item_panel',
                      'add_teammember', 'delete_teammember', 'get_url', 'get_switcher_dropdown'];
if (isset($_REQUEST['itemtype'])) {

   $traits = class_uses($_REQUEST['itemtype'], true);
   if (!in_array($_REQUEST['action'], $nonkanban_actions) && !Toolbox::hasTrait($_REQUEST['itemtype'], Kanban::class)) {
      // Bad request
      // For all actions, except those in $nonkanban_actions, we expect to be manipulating the Kanban itself.
      Glpi\Http\Response::sendError(400, "Invalid itemtype parameter", Glpi\Http\Response::CONTENT_TYPE_TEXT_HTML);
   }
   /** @var CommonDBTM $item */
   $itemtype = $_REQUEST['itemtype'];
   $item     = new $itemtype();
}

// Rights Checks
if (isset($itemtype)) {
   if (in_array($action, ['refresh', 'get_switcher_dropdown', 'get_column', 'load_item_panel'])) {
      if (!$item->canView()) {
         // Missing rights
         http_response_code(403);
         return;
      }
   }
   if (in_array($action, ['update', 'add_teammember', 'delete_teammember', 'load_item_panel'])) {
      $item->getFromDB($_REQUEST['items_id']);
      if (!$item->canUpdateItem()) {
         // Missing rights
         http_response_code(403);
         return;
      }
   }
   if (in_array($action, ['bulk_add_item', 'add_item'])) {
      if (!$item->canCreate()) {
         // Missing rights
         http_response_code(403);
         return;
      }
   }
   if (in_array($action, ['delete_item'])) {
      $maybe_deleted = $item->maybeDeleted();
      if (($maybe_deleted && !$item::canDelete()) && (!$maybe_deleted && $item::canPurge())) {
         // Missing rights
         http_response_code(403);
         return;
      }
   }
}

// Helper to check required parameters
$checkParams = static function ($required) {
   foreach ($required as $param) {
      if (!isset($_REQUEST[$param])) {
         Glpi\Http\Response::sendError(400, "Missing $param parameter");
      }
   }
};

// Action Processing
if (($_POST['action'] ?? null) === 'update') {
   $checkParams(['column_field', 'column_value']);
   // Update project or task based on changes made in the Kanban
   $item->update([
                    'id'                   => $_POST['items_id'],
                    $_POST['column_field'] => $_POST['column_value']
                 ]);
} else if (($_POST['action'] ?? null) === 'add_item') {
   $checkParams(['inputs']);
   $item   = new $itemtype();
   $inputs = [];
   parse_str($_UPOST['inputs'], $inputs);

   $item->add(Sanitizer::sanitize($inputs));
} else if (($_POST['action'] ?? null) === 'bulk_add_item') {
   $checkParams(['inputs']);
   $item   = new $itemtype();
   $inputs = [];
   parse_str($_UPOST['inputs'], $inputs);

   $bulk_item_list = preg_split('/\r\n|[\r\n]/', $inputs['bulk_item_list']);
   if (!empty($bulk_item_list)) {
      unset($inputs['bulk_item_list']);
      foreach ($bulk_item_list as $item_entry) {
         $item_entry = trim($item_entry);
         if (!empty($item_entry)) {
            $item->add(Sanitizer::sanitize($inputs + ['name' => $item_entry, 'content' => '']));
         }
      }
   }
} else if (($_POST['action'] ?? null) === 'move_item') {
   $checkParams(['card', 'column', 'position', 'kanban']);
   /** @var Kanban|CommonDBTM $kanban */
   $kanban   = new $_POST['kanban']['itemtype']();
   $can_move = $kanban->canOrderKanbanCard($_POST['kanban']['items_id']);
   if ($can_move) {
      Item_Kanban::moveCard(
         $_POST['kanban']['itemtype'],
         $_POST['kanban']['items_id'],
         $_POST['card'],
         $_POST['column'],
         $_POST['position']
      );
   }
} else if (($_POST['action'] ?? null) === 'show_column') {
   $checkParams(['column', 'kanban']);
   Item_Kanban::showColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} else if (($_POST['action'] ?? null) === 'hide_column') {
   $checkParams(['column', 'kanban']);
   Item_Kanban::hideColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} else if (($_POST['action'] ?? null) === 'collapse_column') {
   $checkParams(['column', 'kanban']);
   Item_Kanban::collapseColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} else if (($_POST['action'] ?? null) === 'expand_column') {
   $checkParams(['column', 'kanban']);
   Item_Kanban::expandColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} else if (($_POST['action'] ?? null) === 'move_column') {
   $checkParams(['column', 'kanban', 'position']);
   Item_Kanban::moveColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column'], $_POST['position']);
} else if ($_REQUEST['action'] === 'refresh') {
   $checkParams(['column_field']);
   // Get all columns to refresh the kanban
   header("Content-Type: application/json; charset=UTF-8", true);
   $force_columns = Item_Kanban::getAllShownColumns($itemtype, $_REQUEST['items_id']);
   $columns       = $itemtype::getKanbanColumns($_REQUEST['items_id'], $_REQUEST['column_field'], $force_columns, true);
   echo json_encode($columns, JSON_FORCE_OBJECT);
} else if ($_REQUEST['action'] == 'get_switcher_dropdown') {
   //Infotel
   $values = $itemtype::getAllForKanban();
   $vals   = [];
   foreach ($values as $key => $value) {
      if (PluginTasklistsTypeVisibility::isUserHaveRight($key)) {
         $vals[$key] = $value;
      }
   }
   Dropdown::showFromArray('kanban-board-switcher', $vals, [
      'value' => isset($_REQUEST['items_id']) ? $_REQUEST['items_id'] : ''
   ]);

} else if ($_REQUEST['action'] == 'get_url') {
   //Infotel
   $checkParams(['items_id']);
   $kb = new PluginTasklistsKanban();
   echo $kb->getSearchURL() . '?context_id=' . $_REQUEST['items_id'];
   return;

} else if (($_POST['action'] ?? null) === 'create_column') {
   $checkParams(['column_field', 'items_id', 'column_name']);
   $column_field = $_POST['column_field'];
   $column_itemtype = getItemtypeForForeignKeyField($column_field);
   if (!$column_itemtype::canCreate() || !$column_itemtype::canView()) {
      // Missing rights
      http_response_code(403);
      return;
   }
   $params = $_POST['params'] ?? [];
   $column_item = new $column_itemtype();
   $column_id = $column_item->add([
                                     'name'   => $_POST['column_name']
                                  ] + $params);
   header("Content-Type: application/json; charset=UTF-8", true);
   $column = $itemtype::getKanbanColumns($_POST['items_id'], $column_field, [$column_id]);
   echo json_encode($column);
} else if (($_POST['action'] ?? null) === 'save_column_state') {
   $checkParams(['items_id', 'state']);
   Item_Kanban::saveStateForItem($_POST['itemtype'], $_POST['items_id'], $_POST['state']);
} else if ($_REQUEST['action'] === 'load_column_state') {
   $checkParams(['items_id', 'last_load']);
   header("Content-Type: application/json; charset=UTF-8", true);
   $response = [
      'state'     => Item_Kanban::loadStateForItem($_REQUEST['itemtype'], $_REQUEST['items_id'], $_REQUEST['last_load']),
      'timestamp' => $_SESSION['glpi_currenttime']
   ];
   echo json_encode($response, JSON_FORCE_OBJECT);
} else if ($_REQUEST['action'] === 'list_columns') {
   $checkParams(['column_field']);
   header("Content-Type: application/json; charset=UTF-8", true);
   echo json_encode($itemtype::getAllKanbanColumns($_REQUEST['column_field']));
} else if ($_REQUEST['action'] === 'get_column') {
   $checkParams(['column_id', 'column_field', 'items_id']);
   header("Content-Type: application/json; charset=UTF-8", true);
   $column = $itemtype::getKanbanColumns($_REQUEST['items_id'], $_REQUEST['column_field'], [$_REQUEST['column_id']]);
   echo json_encode($column, JSON_FORCE_OBJECT);
} else if ($_REQUEST['action'] === 'show_card_edit_form') {
   $checkParams(['card']);
   $item->getFromDB($_REQUEST['card']);
   if ($item->canViewItem() && $item->canUpdateItem()) {
      $item->showForm($_REQUEST['card']);
   } else {
      http_response_code(403);
      return;
   }
} else if (($_POST['action'] ?? null) === 'delete_item') {
   $checkParams(['items_id']);
   $item->getFromDB($_POST['items_id']);
   // Check if the item can be trashed and if the request isn't forcing deletion (purge)
   $maybe_deleted = $item->maybeDeleted() && !($_REQUEST['force'] ?? false);
   if (($maybe_deleted && $item->canDeleteItem()) || (!$maybe_deleted && $item->canPurgeItem())) {
      $item->delete(['id' => $_POST['items_id']], !$maybe_deleted);
   } else {
      http_response_code(403);
      return;
   }
} else if (($_POST['action'] ?? null) === 'add_teammember') {
   $checkParams(['itemtype_teammember', 'items_id_teammember']);
   $item->addTeamMember($_POST['itemtype_teammember'], (int) $_POST['items_id_teammember'], [
      'role'   => (int) $_POST['role']
   ]);
} else if (($_POST['action'] ?? null) === 'delete_teammember') {
   $checkParams(['itemtype_teammember', 'items_id_teammember']);
   $item->deleteTeamMember($_POST['itemtype_teammember'], (int) $_POST['items_id_teammember'], [
      'role'   => (int) $_POST['role']
   ]);
} else if (($_REQUEST['action'] ?? null) === 'load_item_panel') {
   if (isset($itemtype, $item)) {
      TemplateRenderer::getInstance()->display('components/kanban/item_panels/default_panel.html.twig', [
         'itemtype'     => $itemtype,
         'item_fields'  => $item->fields,
         'team'         => Toolbox::hasTrait($item, Teamwork::class) ? $item->getTeam() : []
      ]);
   } else {
      http_response_code(400);
      return;
   }
} else {
   http_response_code(400);
   return;
}
