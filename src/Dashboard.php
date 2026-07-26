<?php

/*
 -------------------------------------------------------------------------
 tasklists plugin for GLPI
 Copyright (C) 2016-2026 by the tasklists Development Team.

 https://github.com/InfotelGLPI/tasklists
 -------------------------------------------------------------------------

 LICENSE

 This file is part of tasklists.

 tasklists is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 tasklists is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with tasklists. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Tasklists;

use Ajax;
use CommonGLPI;
use CommonITILObject;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
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
                    $tasks_table     = 'glpi_plugin_tasklists_tasks';
                    $tasktypes_table = 'glpi_plugin_tasklists_tasktypes';

                    $where = [
                        "$tasks_table.is_deleted"  => 0,
                        "$tasks_table.is_template" => 0,
                        "$tasks_table.users_id"    => Session::getLoginUserID(),
                    ];
                    if (is_array($states) && count($states) > 0) {
                        $where["$tasks_table.plugin_tasklists_taskstates_id"] = $states_founded;
                    }
                    $entities_crit = $dbu->getEntitiesRestrictCriteria($tasks_table, '', $_SESSION["glpiactiveentities"], true);
                    if (count($entities_crit)) {
                        $where[] = $entities_crit;
                    }

                    $iterator = $DB->request([
                        'SELECT'    => [
                            "$tasks_table.*",
                            "$tasktypes_table.completename AS type",
                        ],
                        'FROM'      => $tasks_table,
                        'LEFT JOIN' => [
                            $tasktypes_table => [
                                'ON' => [
                                    $tasks_table     => 'plugin_tasklists_tasktypes_id',
                                    $tasktypes_table => 'id',
                                ],
                            ],
                        ],
                        'WHERE'     => $where,
                        'ORDER'     => "$tasks_table.priority DESC",
                    ]);

                    $tasks = [];
                    foreach ($iterator as $data) {
                        $ID   = $data['id'];
                        $task = new Task();
                        if ($task->checkVisibility($ID) == true) {
                            $rand                  = mt_rand();
                            $url                   = Toolbox::getItemTypeFormURL(Task::class) . "?id=" . $data['id'];
                            $tasks[$data['id']][0] = TemplateRenderer::getInstance()->render(
                                '@tasklists/dashboard/task_cell.html.twig',
                                [
                                    'dom_id' => $data["id"] . $rand,
                                    'url'    => $url,
                                    'name'   => $data['name'],
                                ]
                            );

                            $tasks[$data['id']][0] .= Html::showToolTip(
                                RichText::getSafeHtml($data['content']),
                                ['applyto' => 'task' . $data["id"] . $rand,
                                 'display' => false]
                            );

                            $bgcolor               = $_SESSION["glpipriority_" . $data['priority']];
                            $tasks[$data['id']][1] = "<div class='center' style='background-color:$bgcolor;'>" . CommonITILObject::getPriorityName($data['priority']) . "</div>";
                            $tasks[$data['id']][2] = htmlescape($data['type']);
                            $tasks[$data['id']][3] = htmlescape($dbu->getUserName($data['users_id']));
                            $tasks[$data['id']][4] = Dropdown::getValueWithUnit($data['percent_done'], "%");
                            $due_date              = $data['due_date'];
                            $display               = Html::convDate($data['due_date']);
                            if ($due_date <= date('Y-m-d') && !empty($due_date)) {
                                $display = "<div class='deleted'>" . Html::convDate($data['due_date']) . "</div>";
                            }
                            $tasks[$data['id']][5] = $display;
                        }
                    }
                    $widget->setTabDatas($tasks);
                    $widget->setTabNames($headers);
                    //$widget->setOption("bSort", false);
                    $widget->toggleWidgetRefresh();
                    $link = TemplateRenderer::getInstance()->render(
                        '@tasklists/dashboard/add_task_button.html.twig',
                        ['label' => __('Add task', 'tasklists')]
                    );
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
