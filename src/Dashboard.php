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

namespace GlpiPlugin\Tasklists;

use Ajax;
use CommonGLPI;
use DbUtils;
use Dropdown;
use Glpi\RichText\RichText;
use GlpiPlugin\Mydashboard\Datatable;
use Group_User;
use Html;
use Plugin;
use Session;
use Toolbox;

/**
 * Class Dashboard
 */
class Dashboard extends CommonGLPI
{
    public $widgets = [];
    private $options;
    private $datas;
    private $form;

    /**
     * Dashboard constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->options = $options;
    }

    public function init()
    {
    }


    public function getWidgetsForItem()
    {
        $widgets = [
           __('Tables', "mydashboard") => [
              $this->getType() . "1" => ["title"   => __("Tasks list", 'tasklists'),
                                         "icon"    => "ti ti-table",
                                         "comment" => ""],
           ],
        ];
        return $widgets;
    }

    /**
     * @param $widgetId
     *
     * @return Datatable
     * @throws \GlpitestSQLError
     */
    public function getWidgetContentForItem($widgetId)
    {
        global $CFG_GLPI, $DB;

        if (empty($this->form)) {
            $this->init();
        }
        switch ($widgetId) {
            case $this->getType() . "1":
                if (Plugin::isPluginActive("tasklists")) {
                    $dbu    = new DbUtils();
                    $widget = new Datatable();

                    $st             = new TaskState();
                    $states_founded = [];
                    $states         = $st->find(['is_finished' => 0]);
                    foreach ($states as $state) {
                        $states_founded[] = $state["id"];
                    }
                    $groups_founded = [];
                    $groups         = Group_User::getUserGroups(Session::getLoginUserID());
                    foreach ($groups as $group) {
                        $groups_founded[] = $group["id"];
                    }

                    $headers = [__('Name'),
                                __('Priority'),
                                _n(
                                    'Context',
                                    'Contexts',
                                    1,
                                    'tasklists'
                                ),
                                __('User'), __('Percent done'),
                                __('Due date', 'tasklists')];//, __('Action')
                    $query   = "SELECT `glpi_plugin_tasklists_tasks`.*,`glpi_plugin_tasklists_tasktypes`.`completename` AS 'type'
                            FROM `glpi_plugin_tasklists_tasks`
                            LEFT JOIN `glpi_plugin_tasklists_tasktypes`
                            ON (`glpi_plugin_tasklists_tasks`.`plugin_tasklists_tasktypes_id` = `glpi_plugin_tasklists_tasktypes`.`id`)
                            WHERE `glpi_plugin_tasklists_tasks`.`is_deleted` = 0
                              AND `glpi_plugin_tasklists_tasks`.`is_template` = 0 ";
                    if (is_array($states) && count($states) > 0) {
                        $query .= " AND `glpi_plugin_tasklists_tasks`.`plugin_tasklists_taskstates_id` IN (" . implode(",", $states_founded) . ") ";
                    }
                    $query .= " AND (`glpi_plugin_tasklists_tasks`.`users_id` = '" . Session::getLoginUserID() . "'";
                    //if (count($groups) > 0){
                    //   $query .= " OR `glpi_plugin_tasklists_tasks`.`groups_id` IN (" . implode(",", $groups_founded) . ")";
                    //}
                    //$query .= "OR `glpi_plugin_tasklists_tasks`.`visibility` = 3)";
                    $query .= ") ";
                    $query .= $dbu->getEntitiesRestrictRequest('AND', 'glpi_plugin_tasklists_tasks', '', $_SESSION["glpiactiveentities"], true);
                    $query .= "ORDER BY `glpi_plugin_tasklists_tasks`.`priority` DESC ";

                    $tasks = [];
                    if ($result = $DB->doQuery($query)) {
                        if ($DB->numrows($result)) {
                            while ($data = $DB->fetchArray($result)) {
                                $ID   = $data['id'];
                                $task = new Task();
                                if ($task->checkVisibility($ID) == true) {
                                    $rand                  = mt_rand();
                                    $url                   = Toolbox::getItemTypeFormURL(Task::class) . "?id=" . $data['id'];
                                    $tasks[$data['id']][0] = "<a id='task" . $data["id"] . $rand . "' target='_blank' href='$url'>" . $data['name'] . "</a>";

                                    $tasks[$data['id']][0] .= Html::showToolTip(
                                        RichText::getSafeHtml($data['content']),
                                        ['applyto' => 'task' . $data["id"] . $rand,
                                         'display' => false]
                                    );

                                    $bgcolor               = $_SESSION["glpipriority_" . $data['priority']];
                                    $tasks[$data['id']][1] = "<div class='center' style='background-color:$bgcolor;'>" . CommonITILObject::getPriorityName($data['priority']) . "</div>";
                                    $tasks[$data['id']][2] = $data['type'];
                                    $tasks[$data['id']][3] = $dbu->getUserName($data['users_id']);
                                    $tasks[$data['id']][4] = Dropdown::getValueWithUnit($data['percent_done'], "%");
                                    $due_date              = $data['due_date'];
                                    $display               = Html::convDate($data['due_date']);
                                    if ($due_date <= date('Y-m-d') && !empty($due_date)) {
                                        $display = "<div class='deleted'>" . Html::convDate($data['due_date']) . "</div>";
                                    }
                                    $tasks[$data['id']][5] = $display;
                                }
                            }
                        }
                    }
                    $widget->setTabDatas($tasks);
                    $widget->setTabNames($headers);
                    //$widget->setOption("bSort", false);
                    $widget->toggleWidgetRefresh();
                    $link = "<div align='right'><a href='#' data-bs-toggle='modal' data-bs-target='#task' class='submit btn btn-primary' title='" . __('Add task', 'tasklists') . "' >";
                    $link .= __('Add task', 'tasklists');
                    $link .= "</a></div>";
                    $link .= Ajax::createIframeModalWindow(
                        'task',
                        $CFG_GLPI['root_doc'] . "/plugins/tasklists/front/task.form.php",
                        ['title'         => __('Add task', 'tasklists'),
                         'reloadonclose' => false,
                         'width'         => 1180,
                         'display'       => false,
                         'height'        => 600
                        ]
                    );
                    $widget->appendWidgetHtmlContent($link);

                    $widget->setWidgetTitle(__("Tasks list", 'tasklists'));

                    return $widget;
                } else {
                    $widget = new Datatable();
                    $widget->setWidgetTitle(__("Tasks list", 'tasklists'));
                    return $widget;
                }
                break;
        }
    }

    /**
     * @return mixed
     */
    public static function addTask()
    {
        //$task->showFormButtons($options);
        //return $form;
    }
}
