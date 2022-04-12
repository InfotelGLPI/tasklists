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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;

/**
 * Class PluginTasklistsKanban
 */
class PluginTasklistsKanban extends CommonGLPI {

   static $rightname = 'plugin_tasklists';

   /**
    * @return bool
    */
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * @return bool
    */
   static function canCreate() {
      return Session::haveRight(self::$rightname, CREATE);
   }


   public function canOrderKanbanCard($ID) {
      if ($ID > 0) {
         $this->getFromDB($ID);
      }
      return ($ID <= 0 || $this->canModifyGlobalState());
   }

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {
      return __('Kanban', 'tasklists');
   }


   /**
    * @param array $options
    *
    * @return array
    */
   function defineTabs($options = []) {

      $ong = [];
      $this->addStandardTab(__CLASS__, $ong, $options);
      $ong['no_all_tab'] = true;

      return $ong;
   }

   /**
    * @param $id
    *
    * @return int
    */
   static function countTasksForKanban($id) {
      $dbu = new DbUtils();
      return $dbu->countElementsInTable('glpi_plugin_tasklists_tasks',
                                        ["plugin_tasklists_tasktypes_id" => $id,
                                         "is_template"                   => 0]);
   }

   /**
    * @param \CommonGLPI $item
    * @param int         $withtemplate
    *
    * @return array|bool|string
    * @throws \GlpitestSQLError
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      global $DB, $CFG_GLPI;

      $dbu   = new DbUtils();
      $query = "SELECT `glpi_plugin_tasklists_tasktypes`.*
                FROM `glpi_plugin_tasklists_tasktypes` ";
      $query .= $dbu->getEntitiesRestrictRequest('WHERE', 'glpi_plugin_tasklists_tasktypes', '', $_SESSION["glpiactiveentities"], true);
      $query .= "ORDER BY `name`";
      $tabs  = [];
      if ($item->getType() == __CLASS__) {
         if ($result = $DB->query($query)) {
            if ($DB->numrows($result)) {
               while ($data = $DB->fetchArray($result)) {
                  //                  if (self::countTasksForKanban($data["id"]) > 0) {
                  if (PluginTasklistsTypeVisibility::isUserHaveRight($data["id"])) {
                     $tabs[$data["id"]] = $data["completename"];
                  }
                  //                  }
               }
            }
         }
         if (count($tabs) == 0) {
            echo "<div class='alert alert-important alert-warning d-flex'>";
            echo "<b>" . __("You don't have the right to see any context", 'tasklists') . "</b></div>";
            return false;
         }

         return $tabs;
      }

      return '';
   }

   /**
    * @param \CommonGLPI $item
    * @param int         $tabnum
    * @param int         $withtemplate
    *
    * @return bool
    * @throws \GlpitestSQLError
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == __CLASS__) {
         self::showKanban($tabnum);
      }
      return true;
   }


   static function showKanban($ID) {

      if ($ID > 0) {
         $item_id = $ID;
      } else {
         $item_id = PluginTasklistsPreference::checkPreferenceValue("default_type", Session::getLoginUserID());
      }

      if ($item_id == 0) {
         echo "<div class='alert alert-important alert-warning d-flex'>";
         echo "<b>" . __("There is no accessible context", "tasklists") . "</b></div>";
      } else {
         //         $supported_itemtypes = json_encode($supported_itemtypes, JSON_FORCE_OBJECT);
         //         $column_field        = json_encode($column_field, JSON_FORCE_OBJECT);
         $context             = new PluginTasklistsTaskType();
         $context->getFromDB($item_id);
         $supported_itemtypes = [];

         $team_itemtypes = PluginTasklistsTask::getTeamItemtypes();
         $team_role_ids  = PluginTasklistsTask::getTeamRoles();
         $team_roles     = [];

         foreach ($team_role_ids as $role_id) {
            $team_roles[$role_id] = PluginTasklistsTask::getTeamRoleName($role_id);
         }

         if (PluginTasklistsTask::canCreate()) {
            $supported_itemtypes['PluginTasklistsTask'] = [
               'name'           => PluginTasklistsTask::getTypeName(1),
               'icon'           => PluginTasklistsTask::getIcon(),
               'fields'         => [
                  'name'                          => [
                     'placeholder' => __('Name')
                  ],
                  'content'                       => [
                     'placeholder' => __('Content'),
                     'type'        => 'textarea'
                  ],
                  'plugin_tasklists_tasktypes_id' => [
                     'type'  => 'hidden',
                     'value' => $item_id
                  ],
                  'entities_id'                   => [
                     'type'  => 'hidden',
                     'value' => $context->fields['entities_id']
                  ],
                  'users_id'                      => [
                     'type'  => 'hidden',
                     'value' => $_SESSION['glpiID']
                  ]
               ],
               'team_itemtypes' => $team_itemtypes,
               'team_roles'     => $team_roles,
            ];
         }
         //
         //         $column_field = [
         //            'id'           => 'plugin_tasklists_taskstates_id',
         //            'extra_fields' => [
         //               'color' => [
         //                  'type' => 'color'
         //               ]
         //            ]
         //         ];

         $column_field = [
            'id'           => 'plugin_tasklists_taskstates_id',
            'extra_fields' => []
         ];

         $refresh = 0;
         if (PluginTasklistsPreference::checkPreferenceValue("automatic_refresh", Session::getLoginUserID()) != 0) {
            $refresh = PluginTasklistsPreference::checkPreferenceValue("automatic_refresh_delay", Session::getLoginUserID());
         }

         $canadd_item    = json_encode(self::canCreate());
         $candelete_item = json_encode(self::canDelete());
         $canmodify_view = json_encode(Session::haveRight("plugin_tasklists_config", READ));
         //      $canmodify_view = json_encode(($ID == 0 || $project->canModifyGlobalState()));
         $cancreate_column      = json_encode((bool)Session::haveRight("plugin_tasklists_config", READ));
         $limit_addcard_columns = [];
         $can_order_item        = json_encode((bool)PluginTasklistsTypeVisibility::isUserHaveRight($item_id));

         $itemtype = PluginTasklistsTaskType::class;

         $rights = [
            'create_item'                 => $canadd_item,
            'delete_item'                 => $candelete_item,
            'create_column'               => $cancreate_column,
            'modify_view'                 => $canmodify_view,
            'order_card'                  => $can_order_item,
            'create_card_limited_columns' => $limit_addcard_columns
         ];

         TemplateRenderer::getInstance()->display('@tasklists/kanban.html.twig', [
            'root_tasklists'              => PLUGIN_TASKLISTS_WEBDIR,
            'kanban_id'                   => 'kanban',
            'rights'                      => $rights,
            'supported_itemtypes'         => $supported_itemtypes,
            'max_team_images'             => 3,
            'background_refresh_interval' => $refresh,
            'column_field'                => $column_field,
            'item'                        => [
               'itemtype' => $itemtype,
               'items_id' => $item_id
            ],
            'supported_filters'           => [
                                                'title'   => [
                                                   'description'        => _x('filters', 'The title of the item'),
                                                   'supported_prefixes' => ['!', '#'] // Support exclusions and regex
                                                ],
                                                'type'    => [
                                                   'description'        => _x('filters', 'The type of the item'),
                                                   'supported_prefixes' => ['!']
                                                ],
                                                'content' => [
                                                   'description'        => _x('filters', 'The content of the item'),
                                                   'supported_prefixes' => ['!', '#'] // Support exclusions and regex
                                                ],
                                                'team'    => [
                                                   'description'        => _x('filters', 'A team member for the item'),
                                                   'supported_prefixes' => ['!']
                                                ],
                                                'user'    => [
                                                   'description'        => _x('filters', 'A user in the team of the item'),
                                                   'supported_prefixes' => ['!']
                                                ],
                                                'group'   => [
                                                   'description'        => _x('filters', 'A group in the team of the item'),
                                                   'supported_prefixes' => ['!']
                                                ],
                                                //                                                'supplier' => [
                                                //                                                   'description'        => _x('filters', 'A supplier in the team of the item'),
                                                //                                                   'supported_prefixes' => ['!']
                                                //                                                ],
                                             ] + self::getKanbanPluginFilters(static::getType()),
         ]);
      }
   }

   public static function getKanbanPluginFilters($itemtype) {
      global $PLUGIN_HOOKS;
      $filters = [];

      //      if (isset($PLUGIN_HOOKS[Hooks::KANBAN_FILTERS])) {
      //         foreach ($PLUGIN_HOOKS[Hooks::KANBAN_FILTERS] as $plugin => $itemtype_filters) {
      //            $filters = array_merge($filters, $itemtype_filters[$itemtype] ?? []);
      //         }
      //      }
      return $filters;
   }
}
