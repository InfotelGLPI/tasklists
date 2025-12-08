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

use GlpiPlugin\Tasklists\Menu;
use GlpiPlugin\Tasklists\NotificationTargetTask;
use GlpiPlugin\Tasklists\Profile;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\TaskState;
use GlpiPlugin\Tasklists\TaskType;
use GlpiPlugin\Tasklists\Item_Kanban;
/**
 * @return bool
 */
function plugin_tasklists_install()
{
    global $DB;

    if (!$DB->tableExists("glpi_plugin_tasklists_tasks")) {
        $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/empty-2.1.0.sql");
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
        if ($result = $DB->doQuery($query)) {
            if ($DB->numrows($result)) {
                while ($data = $DB->fetchArray($result)) {
                    $lists_unique_kanban[] = $data['items_id'];
                }
            }
        }
        $tasklist_item_kanban = new Item_Kanban();
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
        if ($result = $DB->doQuery($query)) {
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
    $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-2.1.0.sql");

    $DB->runFile(PLUGIN_TASKLISTS_DIR . "/sql/update-2.1.4.sql");
   // Add record notification
    call_user_func([NotificationTargetTask::class, 'install']);

    //DisplayPreferences Migration
    $classes = ['PluginTasklistsTask' => Task::class,
        'PluginTasklistsTaskType' => TaskType::class];

    foreach ($classes as $old => $new) {
        $displayusers = $DB->request([
            'SELECT' => [
                'users_id'
            ],
            'DISTINCT' => true,
            'FROM' => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $old,
            ],
        ]);

        if (count($displayusers) > 0) {
            foreach ($displayusers as $displayuser) {
                $iterator = $DB->request([
                    'SELECT' => [
                        'num',
                        'id'
                    ],
                    'FROM' => 'glpi_displaypreferences',
                    'WHERE' => [
                        'itemtype' => $old,
                        'users_id' => $displayuser['users_id'],
                        'interface' => 'central'
                    ],
                ]);

                if (count($iterator) > 0) {
                    foreach ($iterator as $data) {
                        $iterator2 = $DB->request([
                            'SELECT' => [
                                'id'
                            ],
                            'FROM' => 'glpi_displaypreferences',
                            'WHERE' => [
                                'itemtype' => $new,
                                'users_id' => $displayuser['users_id'],
                                'num' => $data['num'],
                                'interface' => 'central'
                            ],
                        ]);
                        if (count($iterator2) > 0) {
                            foreach ($iterator2 as $dataid) {
                                $query = $DB->buildDelete(
                                    'glpi_displaypreferences',
                                    [
                                        'id' => $dataid['id'],
                                    ]
                                );
                                $DB->doQuery($query);
                            }
                        } else {
                            $query = $DB->buildUpdate(
                                'glpi_displaypreferences',
                                [
                                    'itemtype' => $new,
                                ],
                                [
                                    'id' => $data['id'],
                                ]
                            );
                            $DB->doQuery($query);
                        }
                    }
                }
            }
        }
    }

    Profile::initProfile();
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

    return true;
}

/**
 * @return bool
 */
function plugin_tasklists_uninstall()
{
    global $DB;

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

    $itemtypes = ['Alert',
        'DisplayPreference',
        'Document_Item',
        'ImpactItem',
        'Item_Ticket',
        'Item_Kanban',
        'Link_Itemtype',
        'Notepad',
        'SavedSearch',
        'DropdownTranslation',
        'NotificationTemplate',
        'Notification'];
    foreach ($itemtypes as $itemtype) {
        $item = new $itemtype;
        $item->deleteByCriteria(['itemtype' => Task::class]);
    }

    $notif   = new Notification();
    $options = ['itemtype' => Task::class];
    foreach ($DB->request([
        'FROM' => 'glpi_notifications',
        'WHERE' => $options]) as $data) {
        $notif->delete($data);
    }

    //templates
    $template       = new NotificationTemplate();
    $translation    = new NotificationTemplateTranslation();
    $notif_template = new Notification_NotificationTemplate();
    $options        = ['itemtype' => Task::class];
    foreach ($DB->request([
        'FROM' => 'glpi_notificationtemplates',
        'WHERE' => $options]) as $data) {
        $options_template = [
            'notificationtemplates_id' => $data['id']
        ];

        foreach ($DB->request([
            'FROM' => 'glpi_notificationtemplatetranslations',
            'WHERE' => $options_template]) as $data_template) {
            $translation->delete($data_template);
        }
        $template->delete($data);

        foreach ($DB->request([
            'FROM' => 'glpi_notifications_notificationtemplates',
            'WHERE' => $options_template]) as $data_template) {
            $notif_template->delete($data_template);
        }
    }

   //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }
    Menu::removeRightsFromSession();

    Profile::removeRightsFromSession();

    return true;
}

// Define dropdown relations
/**
 * @return array
 */
function plugin_tasklists_getDatabaseRelations()
{

    if (Plugin::isPluginActive("tasklists")) {
        return ["glpi_plugin_tasklists_tasktypes"  => ["glpi_plugin_tasklists_tasks" => "plugin_tasklists_tasktypes_id"],
//              "glpi_plugin_tasklists_taskstates" => ["glpi_plugin_tasklists_tasks" => "plugin_tasklists_taskstates_id"],
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
function plugin_tasklists_getDropdown()
{
    if (Plugin::isPluginActive("tasklists")) {
        return [TaskType::class  => TaskType::getTypeName(2),
              TaskState::class => TaskState::getTypeName(2)];
    } else {
        return [];
    }
}

/**
 * @param $type
 *
 * @return string
 */
function plugin_tasklists_addDefaultWhere($type)
{

    switch ($type) {
        case Task::class:
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

   if (in_array($itemtype, Task::getTypes(true))) {
      if (Session::haveRight("plugin_tasklists",READ)) {

         $sopt[4411]['table']='glpi_plugin_tasklists_tasktypes';
         $sopt[4411]['field']='name';
         $sopt[4411]['name']= Task::getTypeName(2)." - ".
                                      TaskType::getTypeName(1);
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
function plugin_tasklists_displayConfigItem($type, $ID, $data, $num)
{

    $searchopt = Search::getOptions($type);
    $table     = $searchopt[$ID]["table"];
    $field     = $searchopt[$ID]["field"];

    switch ($table . '.' . $field) {
        case "glpi_plugin_tasklists_tasks.priority":
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
function plugin_tasklists_getRuleActions($options)
{
    $task = new Task();
    return $task->getActions();
}

/**
 * @param $options
 *
 * @return mixed
 */
function plugin_tasklists_getRuleCriterias($options)
{
    $task = new Task();
    return $task->getCriterias();
}

/**
 * @param $options
 *
 * @return the
 */
function plugin_tasklists_executeActions($options)
{
    $task = new Task();
    return $task->executeActions($options['action'], $options['output'], $options['params']);
}
