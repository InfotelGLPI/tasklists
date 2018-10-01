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

/**
 * Class PluginTasklistsKanban
 */
class PluginTasklistsKanban extends CommonGLPI {

   static $rightname = 'plugin_tasklists';

   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {
      return __('Kanban', 'tasklists');
   }


   function defineTabs($options = []) {

      $ong = [];
      $this->addStandardTab(__CLASS__, $ong, $options);
      $ong['no_all_tab'] = true;
      return $ong;
   }

   static function countTasksForKanban($id) {
      $dbu = new DbUtils();
      return $dbu->countElementsInTable('glpi_plugin_tasklists_tasks',
                                        ["plugin_tasklists_tasktypes_id" => $id]);
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      global $DB, $CFG_GLPI;

      $dbu   = new DbUtils();
      $query = "SELECT `glpi_plugin_tasklists_tasktypes`.*
                FROM `glpi_plugin_tasklists_tasktypes` ";
      $query .= $dbu->getEntitiesRestrictRequest('WHERE', 'glpi_plugin_tasklists_tasktypes', '', '',true);
      $tabs  = [];
      if ($item->getType() == __CLASS__) {
         if ($result = $DB->query($query)) {
            if ($DB->numrows($result)) {
               while ($data = $DB->fetch_array($result)) {
                  if (self::countTasksForKanban($data["id"]) > 0) {
                     if (PluginTasklistsTypeVisibility::isUserHaveRight($data["id"])) {
                        $tabs[$data["id"]] = $data["completename"];
                     }
                  }
               }
            }
         }
         if (count($tabs) == 0){
            echo "<div align='center'><br><br><img src=\"" . $CFG_GLPI["root_doc"] . "/pics/warning.png\" alt=\"warning\"><br><br>";
            echo "<b>" . __("You don't have the right to see any context", 'tasklists') . "</b></div>";
            return false;
         }

         return $tabs;
      }

      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == __CLASS__) {
         self::showKanban($tabnum);
      }
      return true;
   }


   static function showKanban($plugin_tasklists_tasktypes_id = 0) {
      global $DB, $CFG_GLPI;

      if (!PluginTasklistsTypeVisibility::isUserHaveRight($plugin_tasklists_tasktypes_id)) {
         echo "<div align='center'><br><br><img src=\"" . $CFG_GLPI["root_doc"] . "/pics/warning.png\" alt=\"warning\"><br><br>";
         echo "<b>" . __("You don't have the right to see any context", 'tasklists') . "</b></div>";
         return false;
      }

      $dbu   = new DbUtils();
      $rand  = mt_rand();
      $query = "SELECT `glpi_plugin_tasklists_tasks`.*,`glpi_plugin_tasklists_tasktypes`.`completename` AS 'type' 
                FROM `glpi_plugin_tasklists_tasks`
                LEFT JOIN `glpi_plugin_tasklists_tasktypes` ON (`glpi_plugin_tasklists_tasks`.`plugin_tasklists_tasktypes_id` = `glpi_plugin_tasklists_tasktypes`.`id`) 
                WHERE `glpi_plugin_tasklists_tasks`.`plugin_tasklists_tasktypes_id` = '" . $plugin_tasklists_tasktypes_id . "'
                AND `glpi_plugin_tasklists_tasks`.`is_deleted` = 0 
                AND `glpi_plugin_tasklists_tasks`.`is_archived` = 0 ";
      $query .= $dbu->getEntitiesRestrictRequest('AND', 'glpi_plugin_tasklists_tasks');
      $query .= "ORDER BY `glpi_plugin_tasklists_tasks`.`priority` DESC ";

      $tasks = [];
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)) {
            while ($data = $DB->fetch_array($result)) {
               $user = new User();
               $link = "";
               if ($user->getFromDB($data['users_id'])) {
                  if ($user->fields['picture']) {
                     $link = "<div class='kanban_user_picture_border_verysmall'>";
                     $link .= "<img title=\"" . $dbu->getUserName($data['users_id']) . "\" class='kanban_user_picture_verysmall' alt=\"" . $dbu->getUserName($data['users_id']) . "\" src='" .
                              User::getThumbnailURLForPicture($user->fields['picture']) . "'>";
                     $link .= "</div>";
                  }
               }
               $plugin_tasklists_taskstates_id = $data['plugin_tasklists_taskstates_id'];
               $finished                       = 0;
               $finished_style                 = 'style="display: inline;"';
               $state                          = new PluginTasklistsTaskState();
               if ($state->getFromDB($plugin_tasklists_taskstates_id)) {
                  if ($state->getFinishedState()) {
                     $finished_style = 'style="display: none;"';
                     $finished       = 1;
                  }
               }
               $task = new PluginTasklistsTask();
               if ($task->checkVisibility($data['id']) == true) {
                  $duedate = '';
                  if (!empty($data['due_date'])) {
                     $duedate = __('Due date', 'tasklists') . " " . Html::convDate($data['due_date']);
                  }
                  $actiontime = '';
                  if ($data['actiontime'] != 0) {
                     $time       = floor($data['actiontime']);
                     $time       = round(abs($time));
                     $actiontime = sprintf(__('%1$s%2$d days', 'tasklists'), "", $time / DAY_TIMESTAMP);
                  }


                  $tasks[] = ['id'             => $data['id'],
                              'title'          => $data['name'],
                              'block'          => ($plugin_tasklists_taskstates_id > 0 ? $plugin_tasklists_taskstates_id : 0),
                              'link'           => Toolbox::getItemTypeFormURL("PluginTasklistsTask") . "?id=" . $data['id'],
                              'description'    => Html::resume_text(Html::clean(Toolbox::unclean_cross_side_scripting_deep($data["comment"])), 80),
                              'priority'       => CommonITILObject::getPriorityName($data['priority']),
                              'bgcolor'        => $_SESSION["glpipriority_" . $data['priority']],
                              'percent'        => $data['percent_done'],
                              'actiontime'     => $actiontime,
                              'duedate'        => $duedate,
                              'footer'         => $link,
                              'finished'       => $finished,
                              'finished_style' => $finished_style,
                  ];
               }
            }
         }
      }

      $tasks = json_encode($tasks);

      echo "<div id='kanban$rand'></div>";

      $cond       = ["plugin_tasklists_taskstates_id" => 0,
                     "plugin_tasklists_tasktypes_id"  => $plugin_tasklists_tasktypes_id,
                     "is_deleted"                     => 0,
                     "is_archived"                    => 0];
      $countTasks = $dbu->countElementsInTable($dbu->getTableForItemType('PluginTasklistsTasks'),
                                               $cond);

      $colors[0]  = "#FFFAAA";
      $states[]   = ['id'       => 0,
                     'title'    => __('Backlog', 'tasklists'),
                     'rank'     => 0,
                     'count'    => $countTasks,
                     'finished' => 0];
      $nb         = 1;
      $datastates = $dbu->getAllDataFromTable($dbu->getTableForItemType('PluginTasklistsTaskState'));
      if (!empty($datastates)) {
         foreach ($datastates as $datastate) {
            $tasktypes = json_decode($datastate['tasktypes']);
            if (is_array($tasktypes)) {
               if (in_array($plugin_tasklists_tasktypes_id, $tasktypes)) {
                  $condition = "`plugin_tasklists_taskstates_id` = '" . $datastate['id'] . "'
                                          AND `plugin_tasklists_tasktypes_id` = '" . $plugin_tasklists_tasktypes_id . "'";
                  $order     = new PluginTasklistsStateOrder();
                  $ranks     = $order->find($condition);
                  $ranking   = 0;
                  if (count($ranks) > 0) {
                     foreach ($ranks as $rank) {
                        $ranking = $rank['ranking'];
                     }
                  }
                  $cond       = ["plugin_tasklists_taskstates_id" => $datastate['id'],
                                 "plugin_tasklists_tasktypes_id"  => $plugin_tasklists_tasktypes_id,
                                 "is_deleted"                     => 0,
                                 "is_archived"                    => 0];
                  $countTasks = $dbu->countElementsInTable($dbu->getTableForItemType('PluginTasklistsTasks'),
                                                           $cond);

                  $states[] = ['id'       => $datastate['id'],
                               'title'    => $datastate['name'],
                               'rank'     => $ranking,
                               'count'    => $countTasks,
                               'finished' => $datastate['is_finished']];

                  $states_ranked = [];
                  foreach ($states as $key => $row) {
                     $states_ranked[$key] = $row['rank'];
                  }
                  array_multisort($states_ranked, SORT_ASC, $states);

                  $colors[$datastate['id']] = $datastate['color'];

                  $nb++;

               }
            }
         }
      }
      $lang['add_tasks']       = __('Add task', 'tasklists');
      $lang['archive_all_tasks']   = __('Archive all tasks of this state', 'tasklists');
      $lang['alert_archive_task']   = __('Are you sure you want to archive this task ?', 'tasklists');
      $lang['alert_archive_all_tasks']   = __('Are you sure you want to archive all tasks ?', 'tasklists');
      $lang['archive_task']   = __('Archive this task', 'tasklists');
      $lang['update_priority'] = __('Update priority of task', 'tasklists');
      $lang['see_details']     = __('Details of task', 'tasklists');
      $lang                    = json_encode($lang);

      $states   = json_encode($states);
      $colors   = json_encode($colors);
      $root_doc = $CFG_GLPI['root_doc'];
      echo "<script>$('#kanban$rand').kanban({
           context: $plugin_tasklists_tasktypes_id,
           titles: $states,
           colours: $colors,
           items: $tasks,
           rootdoc: '$root_doc',
           lang: $lang
       });</script>";

   }
}
