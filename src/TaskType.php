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

namespace GlpiPlugin\Tasklists;

// Class for a Dropdown
use CommonITILObject;
use CommonTreeDropdown;
use DbUtils;
use DropdownTranslation;
use Entity;
use Glpi\Features\KanbanInterface;
use Glpi\RichText\RichText;
use Html;
use Mexitek\PHPColors\Color;
use Session;
use Toolbox;

/**
 * Class TaskType
 */
class TaskType extends CommonTreeDropdown implements KanbanInterface
{
    use \Glpi\Features\Kanban;

    // Contexts are the boards of the Kanban and, at the same time, configuration objects:
    // Glpi\Controller\DropdownFormController gates create/update/purge on this very property
    // (check(-1, CREATE), check($id, UPDATE), check($id, PURGE)). Declaring the class under
    // plugin_tasklists therefore meant that every user allowed to file a task was also allowed
    // to create, rename, move and purge the contexts of the whole instance - and purging one
    // cascades on its TypeVisibility rows and orphans every task filed under it. Writing now
    // follows the configuration right, as TaskState already does; reading is kept on the user
    // right by the canView() override below, because the dropdown and the board switcher have
    // to stay readable by everyone.
    public static $rightname = 'plugin_tasklists_config';

    /**
     * Read access is deliberately decoupled from $rightname: a context is both a configuration
     * object and the dropdown every task is filed under, so it has to remain visible to the
     * ordinary users of the plugin while its write operations stay with the administrators.
     * Dropdown::show() and the tabs test canView(), never $rightname directly.
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return (bool) Session::haveRight('plugin_tasklists', READ);
    }

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {

        return _n('Context', 'Contexts', $nb, 'tasklists');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return "ti ti-layout-kanban";
    }

    /**
     * @param array $options
     *
     * @return array
     * @see CommonGLPI::defineTabs()
     *
     */
    public function defineTabs($options = [])
    {

        $ong = parent::defineTabs($options);
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab(TypeVisibility::class, $ong, $options);
        return $ong;
    }


    /**
     * @return array
     */
    public static function getAllForKanban($active = true, $current_id = -1)
    {
        $self = new self();
        $dbu  = new DbUtils();

        // find() adds no entity boundary of its own and isUserHaveRight() only reasons about
        // visibility groups, so both filters are applied here. The criteria used to be empty:
        // ajax/kanban.php (actions get_kanbans and get_switcher_dropdown, guarded by nothing
        // more than plugin_tasklists in READ) answered with the completename of every context
        // of every entity, including the ones restricted to groups the caller does not belong
        // to. Every other entry point handing out a context - getKanbanColumns(),
        // Kanban::getTabNameForItem(), Preference - already applies exactly these two filters.
        $list  = $self->find(
            $dbu->getEntitiesRestrictCriteria(self::getTable(), '', $_SESSION['glpiactiveentities'], true),
            ["completename ASC"],
        );
        $items = [

        ];

        foreach ($list as $key => $value) {
            if (!TypeVisibility::isUserHaveRight($value['id'])) {
                continue;
            }
            $self->getFromDB($value['id']);
            if (!$self->haveChildren()) {
                $items[$value['id']] = $value['completename'];
            }
        }
        return $items;
    }

    /**
     * @return bool
     */
    public function forceGlobalState()
    {
        // All users must be using the global state unless viewing the global Kanban
        return false;
    }

    /**
     * @param $ID
     * @param $entity
     *
     * @return ID|int|the
     * @throws \GlpitestSQLError
     */
    public static function transfer($ID, $entity)
    {
        global $DB;

        if ($ID > 0) {
            // Not already transfer
            // Search init item
            $iterator = $DB->request([
                'FROM'  => 'glpi_plugin_tasklists_tasktypes',
                'WHERE' => ['id' => (int) $ID],
            ]);

            if (count($iterator)) {
                $data                                   = $iterator->current();
                $input['name']                          = $data['name'];
                $input['entities_id']                   = $entity;
                $input['is_recursive']                  = $data['is_recursive'];
                $input['plugin_tasklists_tasktypes_id'] = $data['plugin_tasklists_tasktypes_id'];
                $temp                                   = new self();

                // $temp has just been instantiated, so getID() could only ever answer -1 and the
                // test that stood here was always true. The lookup it was meant to perform lives
                // inside import(), which calls findID() before adding anything; splitting the
                // two makes explicit which half is a read and which half is a write.
                //
                // TaskType is protected by plugin_tasklists_config, deliberately separate from
                // plugin_tasklists so that a profile may use the plugin without touching its
                // referential - the canCreate()/canUpdate() overrides were removed from these
                // classes for exactly that reason. The massive action reaching this method is
                // gated by "transfer" READ and Task::canUpdate() only, neither of which says
                // anything about the configuration right: transferring tasks used to create
                // contexts in the target entity on behalf of a caller who cannot write that
                // referential, and those contexts then appeared in the dropdowns and the Kanban
                // columns of everyone in that entity. Without the bit, the transfer resolves an
                // already existing equivalent and stops there.
                if (Session::haveRight(self::$rightname, CREATE)) {
                    return $temp->import($input);
                }

                return max(0, (int) $temp->findID($input));
            }
        }
        return 0;
    }

    /**
     * @param       $ID
     * @param       $column_field
     * @param array $column_ids
     * @param bool  $get_default
     *
     * @return array
     */

    public static function getKanbanColumns($ID, $column_field = null, $column_ids = [], $get_default = false)
    {

        if (!TypeVisibility::isUserHaveRight($ID)) {
            return [];
        }
        // Entity isolation: isUserHaveRight() only reasons about visibility groups and
        // ignores entities. Refuse to expose a task type (and therefore its tasks) that
        // lives outside the caller's entity scope, closing the cross-entity Kanban leak.
        $tasktype = new self();
        if (!$tasktype->getFromDB($ID)
            || !Session::haveAccessToEntity($tasktype->fields['entities_id'], $tasktype->fields['is_recursive'])) {
            return [];
        }
        $dbu = new DbUtils();
        //      $datastates = $dbu->getAllDataFromTable($dbu->getTableForItemType('TaskState'));

        $states[0] = [
            'id'              => 0,
            'name'            => __('Backlog', 'tasklists'),
            'header_color'    => "#CCC",
            'header_fg_color' => Toolbox::getFgColor("#CCC", 50),
            'drop_only'       => 0,
            'finished'        => 0,
            '_protected'   => true,
        ];

        if (!empty($column_ids)) {
            $TaskState = new TaskState();
            // The statuses are entity-scoped - glpi_plugin_tasklists_taskstates carries
            // entities_id and is_recursive, and ajax/kanban.php forces the active entity at
            // creation - but this listing queried on the posted ids alone, so naming an id of
            // another entity returned its label and its colour.
            $states_crit   = ["id" => $column_ids];
            $entities_crit = $dbu->getEntitiesRestrictCriteria(TaskState::getTable(), '', '', true);
            if (count($entities_crit)) {
                $states_crit[] = $entities_crit;
            }
            $datastates               = $TaskState->find($states_crit);
        }

        if (!empty($column_ids) && !empty($datastates)) {
            foreach ($datastates as $datastate) {
                if (empty($name = DropdownTranslation::getTranslatedValue($datastate['id'], TaskState::class, 'name', $_SESSION['glpilanguage']))) {
                    $name = $datastate['name'];
                }
                $states[$datastate['id']] = [
                    'id'              => $datastate['id'],
                    'header_color'    => $datastate['color'],
                    'header_fg_color' => Toolbox::getFgColor($datastate['color'], 50),
                    'name'            => $name,
                    'finished'        => $datastate['is_finished']];
                $colors[$datastate['id']] = $datastate['color'];
            }
        }
        $nstates = [];

        $task = new Task();
        foreach ($states as $state) {
            $selected_state = $state;
            $tasks          = [];
            $task_crit      = ["plugin_tasklists_tasktypes_id"  => $ID,
                "plugin_tasklists_taskstates_id" => $state['id'],
                'is_deleted'                     => 0,
                'is_template'                    => 0];
            // Defence in depth: this find() filters on the task type, the state and the flags,
            // and the entity boundary of the whole path rests on the single checkVisibility()
            // call further down. Restricting the query itself means the boundary no longer
            // depends on one call site remaining correct, which is what Dashboard::showWidget()
            // and findUsers() already do.
            $entities_crit  = $dbu->getEntitiesRestrictCriteria(Task::getTable(), '', '', true);
            if (count($entities_crit)) {
                $task_crit[] = $entities_crit;
            }
            $datas          = $task->find($task_crit, ['priority DESC,name']);

            foreach ($datas as $data) {
                // Read back defensively: the session may still hold a value written by an older
                // version of ajax/addOptions.php - the empty string, the literal "null", or a
                // scalar - and json_decode() answers null for all three, which in_array() turns
                // into a fatal TypeError since PHP 8.0. Falling back on the defaults the
                // dropdowns are seeded with restores the board instead of breaking it.
                $array = isset($_SESSION["archive"][Session::getLoginUserID()]) ? json_decode($_SESSION["archive"][Session::getLoginUserID()]) : [0];
                if (!is_array($array)) {
                    $array = [0];
                }
                if (!in_array($data["is_archived"], $array)) {
                    continue;
                }
                $usersallowed = isset($_SESSION["usersKanban"][Session::getLoginUserID()]) ? json_decode($_SESSION["usersKanban"][Session::getLoginUserID()]) : [-1];
                if (!is_array($usersallowed)) {
                    $usersallowed = [-1];
                }
                if (!in_array(-1, $usersallowed) && !in_array($data['users_id'], $usersallowed)) {
                    continue;
                }

                $plugin_tasklists_taskstates_id = $data['plugin_tasklists_taskstates_id'];
                $finished                       = 0;
                $finished_style                 = 'style="display: inline;"';
                $stateT                         = new TaskState();
                if ($stateT->getFromDB($plugin_tasklists_taskstates_id)) {
                    if ($stateT->getFinishedState()) {
                        $finished_style = 'style="display: none;"';
                        $finished       = 1;
                    }
                }
                $task = new Task();
                if ($task->checkVisibility($data['id']) == true) {
                    $duedate = '';
                    if (!empty($data['due_date'])) {
                        $duedate = __('Due date', 'tasklists') . " " . Html::convDate($data['due_date']);
                    }
                    $actiontime = '';
                    if ($data['actiontime'] != 0) {
                        $actiontime = Html::timestampToString($data['actiontime'], false, true);
                    }
                    $archived = $data['is_archived'];

                    if (isset($data['users_id'])
                    && $data['users_id'] != Session::getLoginUserID()) {
                        $finished_style = 'style="display: none;"';
                    }

                    $right = 0;
                    if (($data['users_id'] == Session::getLoginUserID()
                     && Session::haveRight("plugin_tasklists", UPDATE))
                    || Session::haveRight("plugin_tasklists_see_all", 1)) {
                        $right = 1;
                    }

                    if ($data['users_id'] == 0) {
                        $right          = 1;
                        $finished_style = 'style="display: inline;"';
                    }

                    $entity      = new Entity();
                    $entity_name = __('None');
                    if ($entity->getFromDB($data['entities_id'])) {
                        $entity_name = $entity->fields['name'];
                    }
                    // Free-text field rendered as raw HTML into the Kanban card by Kanban.js:
                    // escape it here (GLPI 11 stores raw, sanitizes on display) to prevent
                    // stored XSS. entity_name is a raw DB value too, so escape both branches.
                    $client = htmlescape((empty($data['client'])) ? $entity_name : $data['client']);

                    //               $comment = Glpi\Toolbox\Sanitizer::unsanitize($data["content"]);

                    // Core content
                    $content      = "<div class='kanban-core-content'>";
                    $content      .= "<div class='flex-break'>";
                    $bgcolor      = $_SESSION["glpipriority_" . $data['priority']];
                    $content      .= __('Priority') . "&nbsp;:&nbsp;<i class='fas fa-circle' style='color: $bgcolor'></i>&nbsp;" . CommonITILObject::getPriorityName($data['priority']);
                    $content      .= "</div>";
                    $rich_content = "";
                    if ($data['content'] != null) {
                        $rich_content = RichText::getTextFromHtml($data['content'], false, true, true);
                    }
                    $content .= Html::resume_text($rich_content, 100);
                    $content .= "</div>";
                    $content .= "<div align='right' class='endfooter b'>" . $client . "</div>";
                    $content .= "<div align='right' class='endfooter'>" . $actiontime . "</div>";
                    $content .= "<div align='right' class='endfooter'>" . $duedate . "</div>";
                    // Percent Done
                    $content    .= "<div class='flex-break'></div>";
                    $content    .= Html::progress(100, $data['percent_done']);
                    $content    .= "</div>";
                    $content    .= "<div align='right' class='endfooter'>" . $data['percent_done'] . "%</div>";
                    $nbcomments = "";
                    $nb         = 0;
                    $where      = [
                        'plugin_tasklists_tasks_id' => $data['id'],
                        'language'                  => null,
                    ];
                    $nb         = countElementsInTable(
                        'glpi_plugin_tasklists_tasks_comments',
                        $where,
                    );
                    if ($nb > 0) {
                        $nbcomments = " (" . $nb . ") ";
                    }

                    $itemtype        = Task::class;
                    $meta            = [];
                    $metadata_values = ['name', 'content'];
                    foreach ($metadata_values as $metadata_value) {
                        if (isset($data[$metadata_value])) {
                            $meta[$metadata_value] = $data[$metadata_value];
                        }
                    }
                    //               if (isset($meta['_metadata']['content']) && is_string($meta['_metadata']['content'])) {
                    //                  $meta['_metadata']['content'] = Glpi\RichText\RichText::getTextFromHtml($tasks['_metadata']['content'], false, true);
                    //               } else {
                    //                  $meta['_metadata']['content'] = '';
                    //               }

                    // Create a fake item to get just the actors without loading all other information about items.
                    //               $temp_item = new Task();
                    //               $temp_item->fields['id'] = $data['id'];
                    //               $temp_item->loadActors();

                    // Build team member data
                    $supported_teamtypes = [
                        //                  'User' => ['id', 'firstname', 'realname'],
                        //                  'Group' => ['id', 'name'],
                        //                  'Supplier' => ['id', 'name'],
                    ];
                    //               $members = [
                    //                  'User'      => $temp_item->fields['users_id'],
                    //                  'Group'     => $temp_item->fields['groups_id'],
                    //                  'Supplier'   => $temp_item->getSuppliers(CommonITILActor::ASSIGN),
                    //               ];
                    $team = [];
                    //               foreach ($supported_teamtypes as $itemtype => $fields) {
                    //                  $fields[] = 'id';
                    //                  $fields[] = new QueryExpression($DB->quoteValue($itemtype) . ' AS ' . $DB->quoteName('itemtype'));
                    //
                    //                  $member_ids = array_map(static function ($e) use ($itemtype) {
                    //                     return $e[$itemtype::getForeignKeyField()];
                    //                  }, $members[$itemtype]);
                    //                  if (count($member_ids)) {
                    //                     $itemtable = $itemtype::getTable();
                    //                     $all_items = $DB->request([
                    //                                                  'SELECT'    => $fields,
                    //                                                  'FROM'      => $itemtable,
                    //                                                  'WHERE'     => [
                    //                                                     "{$itemtable}.id"   => $member_ids
                    //                                                  ]
                    ////                                               ]);
                    //               $team = [];
                    //                  $all_items[] = ['itemtype' => 'User', 'items_id'=> $data['users_id']];
                    //                  $all_items[] = ['itemtype' => 'Group', 'items_id'=> $data['groups_id']];
                    ////                     $all_members = [];
                    //                     foreach ($all_items as $k => $member_data) {
                    //                        $member_data['itemtype'] = $member_data['itemtype'];
                    //                        $member_data['id'] = $member_data['items_id'];
                    //                        $member_data['role'] = 2;
                    ////                        if ($member_data['itemtype'] === User::class) {
                    ////                           $member_data['name'] = formatUserName(
                    ////                              $member_data['id'],
                    ////                              '',
                    ////                              $member_data['realname'],
                    ////                              $member_data['firstname']
                    ////                           );
                    ////                        }
                    //                        $team[] = $member_data;
                    //                     }
                    ////                  }
                    ////               }
                    //               Toolbox::logInfo($team);
                    $task->getFromDB($data['id']);
                    $team = $task->getTeam();

                    if (isset($stateT->fields['color']) && $stateT->fields['color'] != null) {
                        $bgcolor = self::getFgColor($stateT->fields['color'], 1);
                    } else {
                        $bgcolor = "#FFF";
                    }

                    $rich_content = "";
                    if ($data['content'] != null) {
                        // The fourth argument re-encodes the output: getTextFromHtml() ends on
                        // html_entity_decode(), so without it the plain text handed back still
                        // carries the markup that was stored in the task content. The value is
                        // published as 'title_tooltip' and interpolated by Kanban.js straight
                        // into a title="" attribute, which closed on the first quote. Same call
                        // as the sibling one in getKanbanColumns() above.
                        $rich_content = RichText::getTextFromHtml($data['content'], false, true, true);
                    }

                    $title = Html::link($data['name'], $itemtype::getFormURLWithID($data['id'])) . $nbcomments;
                    //               $ID    = $data['id'];
                    //               if ($finished == 1 && $archived == 0) {
                    //                  $title .= "&nbsp;<a id='archivetask$ID' href='#' title='" . __('Archive this task', 'tasklists') . "'><i class='ti ti-archive'></i></a>";
                    //               }
                    //               if ($finished == 1 && $data['priority'] < 5) {
                    //                  $title .= "&nbsp;<a id='updatepriority$ID' href='#' title='" . __('Update priority of task', 'tasklists') . "'><i class='ti ti-arrow-up'></i></a>";
                    //               }

                    $tasks[] = ['id'            => "{$itemtype}-{$data['id']}",
                        'title'         => $title,
                        'title_tooltip' => Html::resume_text($rich_content, 100),
                        'is_deleted'    => $data['is_deleted'] ?? false,
                        'content'       => $content,
                        '_team'         => $team,
                        '_form_link'    => $itemtype::getFormUrlWithID($data['id']),

                        'block'          => ($ID > 0 ? $ID : 0),
                        'priority'       => CommonITILObject::getPriorityName($data['priority']),
                        'priority_id'    => $data['priority'],
                        'bgcolor'        => "#FFF",
                        'bordercolor'        => $bgcolor,
                        'percent'        => $data['percent_done'],
                        'actiontime'     => $actiontime,
                        'duedate'        => $duedate,
                        //                           'user'           => $link,
                        'client'         => $client,
                        'finished'       => $finished,
                        'archived'       => $archived,
                        'finished_style' => $finished_style,
                        'right'          => $right,
                        'users_id'       => $data['users_id'],
                        '_readonly'      => false,
                        '_metadata'      => $meta,
                    ];
                }
            }
            $selected_state["items"] = $tasks;
            $nstates[$state["id"]]   = $selected_state;
        }

        return $nstates;
    }

    public static function getFgColor(string $color = "", int $offset = 40, bool $inherit_if_transparent = false): string
    {
        $fg_color = "FFFFFF";
        if ($color !== "") {
            $color = str_replace("#", "", $color);

            // if transparency present, get only the color part
            if (strlen($color) === 8 && preg_match('/^[a-fA-F0-9]+$/', $color)) {
                $tmp   = $color;
                $alpha = hexdec(substr($tmp, 6, 2));
                $color = substr($color, 0, 6);

                if ($alpha <= 100) {
                    return "inherit";
                }
            }

            $color_inst = new Color($color);

            // adapt luminance part
            //         if ($color_inst->isLight()) {
            //            $hsl = Color::hexToHsl($color);
            //            $hsl['L'] = max(0, $hsl['L'] - ($offset / 100));
            //            $fg_color = Color::hslToHex($hsl);
            //         } else {
            $hsl      = Color::hexToHsl($color);
            $hsl['L'] = ($hsl['L'] * 110) + 5;
            $hsl['L'] = ($hsl['L'] > 110) ? $hsl['L'] / 50 : $hsl['L'] / 90;
            $fg_color = Color::hslToHex($hsl);
            //         }
        }

        return "#" . $fg_color;
    }

    /**
     * @param $plugin_tasklists_tasktypes_id
     *
     * @return array
     */
    public static function findUsers($plugin_tasklists_tasktypes_id)
    {
        $dbu   = new DbUtils();
        $users = [];
        $task  = new Task();
        // Entity isolation + per-task visibility: this helper is reachable from an AJAX
        // endpoint (ajax/addOptions.php?action=addUsers) with a client-supplied context
        // id. Restrict the lookup to accessible entities and drop any task the caller
        // cannot actually see, so owners' names cannot be enumerated across entities.
        $criteria = [
            "plugin_tasklists_tasktypes_id" => $plugin_tasklists_tasktypes_id,
            "is_archived"                   => 0,
            "is_deleted"                    => 0,
        ];
        $criteria = array_merge($criteria, $dbu->getEntitiesRestrictCriteria(Task::getTable()));
        $tasks = $task->find($criteria);
        foreach ($tasks as $t) {
            if (!$task->checkVisibility($t["id"])) {
                continue;
            }
            $users[$t["users_id"]] = $dbu->getUserName($t["users_id"]);
        }
        $users     = array_unique($users);
        $users[-1] = __("All");


        return $users;
    }

    // canCreate(), canUpdate() and canDelete() used to be overridden here as
    // Session::haveRight(static::$rightname, 1). The literal 1 is READ, not the bit each
    // operation calls for (CREATE = 4, UPDATE = 2, PURGE = 32), so the three write
    // operations were gated on read access. This dropdown is reachable through the generic
    // Glpi\Controller\DropdownFormController, whose sequence is exactly canView() then
    // check(-1, CREATE) / check($id, UPDATE) / check($id, PURGE): a profile holding
    // plugin_tasklists in read-only mode could create, rename and purge the Kanban contexts,
    // taking their TypeVisibility rows with them and orphaning every task filed under them.
    // The inherited CommonDropdown implementations test the right bit, so the overrides are
    // gone. A separate right for context management would have to be declared in
    // Profile::getAllRights() and tested explicitly, not folded into this one.

    public static function getDataToDisplayOnKanban($ID, $criteria = [])
    {
        // Not needed
    }

    public static function showKanban($ID)
    {
        // Not needed
    }

    public static function getAllKanbanColumns($column_field = null, $column_ids = [], $get_default = false)
    {

        if ($column_field === null || $column_field === 'plugin_tasklists_taskstates_id') {
            $columns  = ['plugin_tasklists_taskstates_id' => []];
            // Security: this listing feeds the column picker of the Kanban and ran with no
            // criterion at all, so it enumerated the statuses - name and colour - configured in
            // every entity of the instance. The table is entity-scoped; the scope was simply not
            // applied on the way out.
            $dbu           = new DbUtils();
            $restrict      = [];
            $entities_crit = $dbu->getEntitiesRestrictCriteria(TaskState::getTable(), '', '', true);
            if (count($entities_crit)) {
                $restrict[] = $entities_crit;
            }
            //         if (!empty($column_ids) && !$get_default) {
            //            $restrict = ['id' => $column_ids];
            //         }

            $Taskstate    = new TaskState();
            $all_statuses = $Taskstate->find($restrict, ['is_finished ASC', 'id']);

            $columns['plugin_tasklists_taskstates_id'][0] = [
                //            'id'        => 0,
                'name'            => __('Backlog', 'tasklists'),
                'header_color'    => "#CCC",
                'header_fg_color' => Toolbox::getFgColor("#CCC", 50),
                'drop_only'       => 0,
            ];

            foreach ($all_statuses as $status) {
                $columns['plugin_tasklists_taskstates_id'][$status['id']] = [
                    'name'            => $status['name'],
                    'header_color'    => $status['color'],
                    'header_fg_color' => Toolbox::getFgColor($status['color'], 50),
                    'drop_only'       => 0,//$status['is_finished'] ??
                ];
            }

            return $columns['plugin_tasklists_taskstates_id'];
        } else {
            return [];
        }
    }

    //   public static function getGlobalKanbanUrl(bool $full = true): string
    //   {
    //      if (method_exists(static::class, 'getFormUrl')) {
    //         return static::getFormURL($full) . '?showglobalkanban=1';
    //      }
    //      //      $kb = new Kanban();
    //      //      echo $kb->getSearchURL() . '?context_id=' . $_REQUEST['items_id'];
    //      //
    //      return '';
    //   }

    public function getKanbanUrlWithID(int $items_id, bool $full = true): string
    {
        $kb = new Kanban();
        return $kb->getSearchURL() . '?context_id=' . $items_id;
    }
}
