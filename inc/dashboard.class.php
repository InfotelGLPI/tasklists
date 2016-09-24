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

class PluginTasklistsDashboard extends CommonGLPI {

   public $widgets = array();
   private $options;
   private $datas, $form;

   function __construct($options = array()) {
      $this->options = $options;
   }

   function init() {


   }

   function getWidgetsForItem() {
      return array(
         $this->getType()."1" => __("Tasks list",'tasklists'),
      );
   }

   function getWidgetContentForItem($widgetId) {
      global $CFG_GLPI, $DB;
      
      if (empty($this->form))
         $this->init();
      switch ($widgetId) {
            case $this->getType()."1":
               $plugin = new Plugin();
               if ($plugin->isActivated("tasklists")) {
                  $widget = new PluginMydashboardDatatable();
                  $headers = array(__('Name'), __('Priority'), _n('Context', 'Contexts', 1, 'tasklists'), __('User'), __('Percent done'), __('Due date'), __('Action'));
                  $query = "SELECT `glpi_plugin_tasklists_tasks`.*,`glpi_plugin_tasklists_tasktypes`.`completename` as 'type' 
                            FROM `glpi_plugin_tasklists_tasks`
                            LEFT JOIN `glpi_plugin_tasklists_tasktypes` ON (`glpi_plugin_tasklists_tasks`.`plugin_tasklists_tasktypes_id` = `glpi_plugin_tasklists_tasktypes`.`id`) 
                            WHERE NOT `glpi_plugin_tasklists_tasks`.`is_deleted`
                                 AND `glpi_plugin_tasklists_tasks`.`state` < 2 ";
                  $query .= getEntitiesRestrictRequest('AND', 'glpi_plugin_tasklists_tasks');
                  $query .=  "ORDER BY `glpi_plugin_tasklists_tasks`.`priority`DESC ";

                  $tasks = array();
                  if ($result = $DB->query($query)) {
                     if ($DB->numrows($result)) {
                        while ($data = $DB->fetch_array($result)) {
                           
                           //$groups = Group_User::getGroupUsers($data['groups_id']);
                           $groupusers = Group_User::getGroupUsers($data['groups_id']);
                           $groups = array();
                           foreach ($groupusers as $groupuser) {
                              $groups[] = $groupuser["id"];
                           }
                           if (
                              ($data['visibility'] ==1 && $data['users_id'] == Session::getLoginUserID())
                                 || 
                                    ($data['visibility'] ==2 && ($data['users_id'] == Session::getLoginUserID() 
                                                                  || in_array(Session::getLoginUserID(),$groups)
                                                                  )) 
                                    || 
                                    ($data['visibility'] ==3)
                                    ) {
                                    
                              $ID = $data['id'];
                              $rand = mt_rand();
                              $url = Toolbox::getItemTypeFormURL("PluginTasklistsTask") . "?id=" . $data['id'];
                              $tasks[$data['id']][0] = "<a id='task".$data["id"].$rand."' target='_blank' href='$url'>" . $data['name'] . "</a>";
                              
                              $tasks[$data['id']][0] .= Html::showToolTip($data['comment'],
                                          array('applyto' => 'task'.$data["id"].$rand,
                                                'display' => false));
                                                
                              $bgcolor = $_SESSION["glpipriority_".$data['priority']];
                              $tasks[$data['id']][1] = "<div class='center' style='background-color:$bgcolor;'>".CommonITILObject::getPriorityName($data['priority'])."</div>";
                              $tasks[$data['id']][2] = $data['type'];
                              $tasks[$data['id']][3] = getUserName($data['users_id']);
                              $tasks[$data['id']][4] = Dropdown::getValueWithUnit($data['percent_done'],"%");
                              $due_date = $data['due_date'];
                              $display =Html::convDate($data['due_date']);
                              if ($due_date <= date('Y-m-d') && !empty($due_date)) {
                                 $display ="<div class='deleted'>".Html::convDate($data['due_date'])."</div>";
                              }
                              $tasks[$data['id']][5] = $display;
                              $tasks[$data['id']][6] = "<div align='center'>";
                              if (Session::haveRight("plugin_tasklists", UPDATE)) {
                                 $tasks[$data['id']][6] .= "<a class='pointer' onclick=\" submitGetLink('".$CFG_GLPI['root_doc']."/plugins/tasklists/front/task.form.php', {'done': 'done', 'id': '".$data['id']."', '_glpi_csrf_token': '".Session::getNewCSRFToken()."', '_glpi_simple_form': '1'});\"><img src='".$CFG_GLPI['root_doc']."/plugins/tasklists/pics/ok.png' title='".__('Mark as done', 'tasklists')."'></a>";
                              }
                              if (Session::haveRight("plugin_tasklists", UPDATENOTE)) {
                                 
                                 $link ="&nbsp;<a href=\"javascript:".Html::jsGetElementbyID('comment'.$rand).".dialog('open');\">";
                                 $link.= "<img class='pointer' src='".$CFG_GLPI['root_doc']."/plugins/tasklists/pics/plus.png' title='".__('Add comment', 'tasklists')."'>";
                                 $link.="</a>";
                                       
                                 $link.=Ajax::createIframeModalWindow('comment'.$rand,
                                          $CFG_GLPI["root_doc"]."/plugins/tasklists/front/comment.form.php?id=".$ID,
                                          array('title'         => __('Add comment', 'tasklists'),
                                                'reloadonclose' => false,
                                                'width'         => 1100,
                                                'display'       => false,
                                                'height'        => 300
                                            ));
                                 $tasks[$data['id']][6] .= $link;
                              }
                              $tasks[$data['id']][6] .= "</div>";
                           }
                        }
                     }
                  }

                  $widget->setTabDatas($tasks);
                  $widget->setTabNames($headers);
                  $widget->setOption("bSort", false);
                  $widget->toggleWidgetRefresh();
                  
                  $link ="<div align='right'><a class='vsubmit' href=\"javascript:".Html::jsGetElementbyID('task').".dialog('open');\">";
                  $link.= __('Add task', 'tasklists');
                  $link.="</a></div>";
                        
                  $link.=Ajax::createIframeModalWindow('task',
                                          $CFG_GLPI["root_doc"]."/plugins/tasklists/front/task.form.php",
                                          array('title'         => __('Add task', 'tasklists'),
                                                'reloadonclose' => false,
                                                'width'         => 1180,
                                                'display'       => false,
                                                'height'        => 600
                                            ));
                  $widget->appendWidgetHtmlContent($link);
                  
                  $widget->setWidgetTitle(__("Tasks list",'tasklists'));

                  return $widget;
               } else {
                  $widget = new PluginMydashboardDatatable();
                  $widget->setWidgetTitle(__("Tasks list",'tasklists'));
                  return $widget;
               }
             break;
      }
   }
   
   static function addTask() {
      global $CFG_GLPI;

      

      //$task->showFormButtons($options);
      return $form;
   }
}