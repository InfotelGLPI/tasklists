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

/**
 * @return bool
 */
function plugin_tasklists_install() {
   global $DB;

   include_once(PLUGIN_TASKLISTS_DIR . "/inc/profile.class.php");
   include_once(PLUGIN_TASKLISTS_DIR . "/inc/task.class.php");
   if (!$DB->tableExists("glpi_plugin_tasklists_tasks")) {

      $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/empty-2.0.4.sql");

   }
   if (!$DB->tableExists("glpi_plugin_tasklists_taskstates")) {
      $mig = new Migration("1.4.1");
      $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-1.4.1.sql");
      $mig->executeMigration();
   }
   if (!$DB->tableExists("glpi_plugin_tasklists_items_kanbans")) {
      $mig = new Migration("1.5.1");
      $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-1.5.1.sql");
      $mig->executeMigration();
   }
   if (!$DB->fieldExists("glpi_plugin_tasklists_preferences", "automatic_refresh")) {
      $mig = new Migration("1.6.0");
      $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-1.6.0.sql");
      $mig->executeMigration();
   }
   if (!$DB->fieldExists("glpi_plugin_tasklists_tasks", "users_id_requester")) {
      $mig = new Migration("1.6.1");
      $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-1.6.1.sql");
      $mig->executeMigration();
   }
   if (!$DB->fieldExists("glpi_plugin_tasklists_tasks", "content")) {
      $mig = new Migration("2.0.0");
      $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-2.0.0.sql");
      $mig->executeMigration();

      //Migrate glpi_plugin_tasklists_items_kanbans to Item_Kanban
      $lists_unique_kanban = [];
      $query               = "SELECT * FROM `glpi_plugin_tasklists_items_kanbans`
                            WHERE `items_id` > 0
                            GROUP BY `items_id`";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)) {
            while ($data = $DB->fetchArray($result)) {
               $lists_unique_kanban[] = $data['items_id'];
            }
         }
      }
      $tasklist_item_kanban = new PluginTasklistsItem_Kanban();
      $lists_kanban         = $tasklist_item_kanban->find();
      $states_by_kanban     = [];
      foreach ($lists_unique_kanban as $kanban) {
         $states_by_kanban[$kanban] = [];
         foreach ($lists_kanban as $columns) {
            if ($columns['items_id'] > 0
                && $columns['items_id'] == $kanban
                && isset($states_by_kanban[$kanban])
                && !(in_array($columns['plugin_tasklists_taskstates_id'], $states_by_kanban[$kanban]))) {
               $states_by_kanban[$kanban][] = $columns['plugin_tasklists_taskstates_id'];
            }
         }
      }
      $state = [];
      foreach ($states_by_kanban as $kanban => $states) {
         foreach ($states as $k => $v) {
            $state[$kanban][$v] =["column" => $v,
                                  "visible" => true,
                                  "folded" => false,
                                  "cards" => []];
         }
      }

      $query = "SELECT * FROM `glpi_plugin_tasklists_items_kanbans`
                            WHERE `items_id` > 0
                            GROUP BY `items_id`";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)) {
            while ($data = $DB->fetchArray($result)) {
               $item_kanban            = new Item_Kanban();
               $input['itemtype']      = $data['itemtype'];
               $input['items_id']      = $data['items_id'];
               $input['users_id']      = 0;
               $input['date_mod']      = $data['date_mod'];
               $input['date_creation'] = $data['date_creation'];
               $input['state']         = json_encode($state[$data['items_id']]);

               $item_kanban->add($input);
            }
         }
      }
   }
   // Add record notification
   include_once(PLUGIN_TASKLISTS_DIR . "/inc/notificationtargettask.class.php");
   call_user_func(["PluginTasklistsNotificationTargetTask", 'install']);

   PluginTasklistsProfile::initProfile();
   PluginTasklistsProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

   return true;
}

/**
 * @return bool
 */
function plugin_tasklists_uninstall() {
   global $DB;

   include_once(PLUGIN_TASKLISTS_DIR . "/inc/profile.class.php");
   include_once(PLUGIN_TASKLISTS_DIR . "/inc/menu.class.php");

   $tables = ["glpi_plugin_tasklists_tasks",
              "glpi_plugin_tasklists_tasktypes",
              "glpi_plugin_tasklists_taskstates",
              "glpi_plugin_tasklists_stateorders",
              "glpi_plugin_tasklists_typevisibilities",
              "glpi_plugin_tasklists_preferences",
              "glpi_plugin_tasklists_tasks_comments",
              "glpi_plugin_tasklists_tickets",
              "glpi_plugin_tasklists_items_kanbans"];

   foreach ($tables as $table) {
      $DB->doQuery("DROP TABLE IF EXISTS `$table`;");
   }

   $tables_glpi = ["glpi_displaypreferences",
                   "glpi_notepads",
                   "glpi_savedsearches",
                   "glpi_items_kanbans",
                   "glpi_logs",
                   "glpi_documents_items"];

   foreach ($tables_glpi as $table_glpi) {
      $DB->doQuery("DELETE FROM `$table_glpi` WHERE `itemtype` LIKE 'PluginTasklistsTask%';");
   }

   $notif = new Notification();

   $options = ['itemtype' => 'PluginTasklistsTask',
               'FIELDS'   => 'id'];
   foreach ($DB->request('glpi_notifications', $options) as $data) {
      $notif->delete($data);
   }

   //templates
   $template    = new NotificationTemplate();
   $translation = new NotificationTemplateTranslation();
   $options     = ['itemtype' => 'PluginTasklistsTask',
                   'FIELDS'   => 'id'];
   foreach ($DB->request('glpi_notificationtemplates', $options) as $data) {
      $options_template = ['notificationtemplates_id' => $data['id'],
                           'FIELDS'                   => 'id'];

      foreach ($DB->request('glpi_notificationtemplatetranslations', $options_template) as $data_template) {
         $translation->delete($data_template);
      }
      $template->delete($data);
   }
   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginTasklistsProfile::getAllRights() as $right) {
      $profileRight->deleteByCriteria(['name' => $right['field']]);
   }
   PluginTasklistsMenu::removeRightsFromSession();

   PluginTasklistsProfile::removeRightsFromSession();

   return true;
}

// Define dropdown relations
/**
 * @return array
 */
function plugin_tasklists_getDatabaseRelations() {

   if (Plugin::isPluginActive("tasklists")) {
      return ["glpi_plugin_tasklists_tasktypes"  => ["glpi_plugin_tasklists_tasks" => "plugin_tasklists_tasktypes_id"],
              "glpi_plugin_tasklists_taskstates" => ["glpi_plugin_tasklists_tasks" => "plugin_tasklists_taskstates_id"],
              "glpi_users"                       => ["glpi_plugin_tasklists_tasks" => "users_id"],
              "glpi_groups"                      => ["glpi_plugin_tasklists_tasks" => "groups_id"],
              "glpi_entities"                    => ["glpi_plugin_tasklists_tasks"     => "entities_id",
                                                     "glpi_plugin_tasklists_tasktypes" => "entities_id"]];
   } else {
      return [];
   }
}

// Define Dropdown tables to be manage in GLPI :
/**
 * @return array
 */
function plugin_tasklists_getDropdown() {
   if (Plugin::isPluginActive("tasklists")) {
      return ['PluginTasklistsTaskType'  => PluginTasklistsTaskType::getTypeName(2),
              'PluginTasklistsTaskState' => PluginTasklistsTaskState::getTypeName(2)];
   } else {
      return [];
   }
}

/**
 * @param $type
 *
 * @return string
 */
function plugin_tasklists_addDefaultWhere($type) {

   switch ($type) {
      case "PluginTasklistsTask" :
         $who = Session::getLoginUserID();
         if (!Session::haveRight("plugin_tasklists_see_all", 1)) {
            if (count($_SESSION["glpigroups"])
               //                && Session::haveRight("plugin_tasklists_my_groups", 1)
            ) {
               $first_groups = true;
               $groups       = "";
               foreach ($_SESSION['glpigroups'] as $val) {
                  if (!$first_groups) {
                     $groups .= ",";
                  } else {
                     $first_groups = false;
                  }
                  $groups .= "'" . $val . "'";
               }
               return " (`glpi_plugin_tasklists_tasks`.`groups_id` IN (
               SELECT DISTINCT `groups_id`
               FROM `glpi_groups_users`
               WHERE `groups_id` IN ($groups)
               )
               OR `glpi_plugin_tasklists_tasks`.`users_id` = '$who'
               OR `glpi_plugin_tasklists_tasks`.`visibility` = '3') ";
            } else { // Only personal ones
               return " (`glpi_plugin_tasklists_tasks`.`users_id` = '$who' 
                OR `glpi_plugin_tasklists_tasks`.`visibility` = '3')";
            }
         }
   }
   return "";
}

////// SEARCH FUNCTIONS ///////() {
/*
function plugin_tasklists_getAddSearchOptions($itemtype) {

   $sopt=[];

   if (in_array($itemtype, PluginTasklistsTask::getTypes(true))) {
      if (Session::haveRight("plugin_tasklists",READ)) {

         $sopt[4411]['table']='glpi_plugin_tasklists_tasktypes';
         $sopt[4411]['field']='name';
         $sopt[4411]['name']=PluginTasklistsTask::getTypeName(2)." - ".
                                      PluginTasklistsTaskType::getTypeName(1);
         $sopt[4411]['forcegroupby']=true;
         $sopt[4411]['datatype']       = 'dropdown';
         $sopt[4411]['massiveaction']  = false;
         $sopt[4411]['joinparams']     = array('beforejoin' => array(
                                                   array('table'      => 'glpi_plugin_tasklists_tasks',
                                                         'joinparams' => $sopt[4410]['joinparams'])));
      }
   }
   return $sopt;
}
*/
/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
 *
 * @return string
 */
function plugin_tasklists_displayConfigItem($type, $ID, $data, $num) {

   $searchopt =& Search::getOptions($type);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];

   switch ($table . '.' . $field) {
      case "glpi_plugin_tasklists_tasks.priority" :
         return " style=\"background-color:" . $_SESSION["glpipriority_" . $data[$num][0]['name']] . ";\" ";
         break;
   }
   return "";
}

/**
 * @param $options
 *
 * @return array
 */
function plugin_tasklists_getRuleActions($options) {
   $task = new PluginTasklistsTask();
   return $task->getActions();
}

/**
 * @param $options
 *
 * @return mixed
 */
function plugin_tasklists_getRuleCriterias($options) {
   $task = new PluginTasklistsTask();
   return $task->getCriterias();
}

/**
 * @param $options
 *
 * @return the
 */
function plugin_tasklists_executeActions($options) {
   $task = new PluginTasklistsTask();
   return $task->executeActions($options['action'], $options['output'], $options['params']);
}
