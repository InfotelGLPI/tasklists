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

use Ajax;
use CommonDBTM;
use CommonITILActor;
use CommonITILObject;
use DbUtils;
use Document_Item;
use Dropdown;
use DropdownTranslation;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
//Needed for save cards
use Glpi\RichText\RichText;
use Group;
use Group_User;
use Html;
use MassiveAction;
use Notepad;
use NotificationEvent;
use Profile_User;
use Session;
use User;

/**
 * Class Task
 */
class Task extends CommonDBTM
{
    public $dohistory = true;
    public static $rightname = 'plugin_tasklists';
    protected $usenotepad = true;
    public static $types = [];

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Task', 'Tasks', $nb);
    }


    /**
     * @return string
     */
    public static function getIcon()
    {
        return "ti ti-layout-kanban";
    }

    /**
     * @return array
     */
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name' => __('Name'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];

        $tab[] = [
            'id' => '2',
            'table' => 'glpi_plugin_tasklists_tasktypes',
            'field' => 'name',
            'name' => _n('Context', 'Contexts', 1, 'tasklists'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '3',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id',
            'name' => __('User'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4',
            'table' => $this->getTable(),
            'field' => 'actiontime',
            'name' => __('Planned duration'),
            'datatype' => 'timestamp',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'percent_done',
            'name' => __('Percent done'),
            'datatype' => 'number',
            'unit' => '%',
            'min' => 0,
            'max' => 100,
            'step' => 5,
        ];

        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'due_date',
            'name' => __('Due date', 'tasklists'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'content',
            'name' => __('Description'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => '8',
            'table' => $this->getTable(),
            'field' => 'priority',
            'name' => __('Priority'),
            'searchtype' => 'equals',
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id' => '9',
            'table' => $this->getTable(),
            'field' => 'visibility',
            'name' => __('Visibility'),
            'searchtype' => 'equals',
            'datatype' => 'specific',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '10',
            'table' => 'glpi_groups',
            'field' => 'name',
            'linkfield' => 'groups_id',
            'name' => __('Group'),
            'condition' => '`is_usergroup`',
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '11',
            'table' => $this->getTable(),
            'field' => 'plugin_tasklists_taskstates_id',
            'name' => __('Status'),
            'searchtype' => ['equals', 'notequals'],
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id' => '12',
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'massiveaction' => false,
            'name' => __('Last update'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id' => '13',
            'table' => $this->getTable(),
            'field' => 'is_archived',
            'name' => __('Archived', 'tasklists'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id' => '14',
            'table' => $this->getTable(),
            'field' => 'client',
            'name' => __('Other client', 'tasklists'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => '121',
            'table' => $this->getTable(),
            'field' => 'date_creation',
            'name' => __('Creation date'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '18',
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __('Child entities'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id' => '30',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number',
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '81',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_requester',
            'name' => _n('Requester', 'Requesters', 1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '19',
            'table' => 'glpi_plugin_tasklists_tasks_comments',
            'field' => 'id',
            'name' => _x('quantity', 'Number of comments', 'tasklists'),
            'forcegroupby' => true,
            'usehaving' => true,
            'datatype' => 'count',
            'massiveaction' => false,
            'joinparams' => [
                'jointype' => 'child',
            ],
        ];

        $tab[] = [
            'id' => '20',
            'table' => 'glpi_plugin_tasklists_tickets',
            'field' => 'id',
            'name' => __('Number of tickets'),
            'forcegroupby' => true,
            'usehaving' => true,
            'datatype' => 'count',
            'massiveaction' => false,
            'joinparams' => [
                'jointype' => 'child',
            ],
        ];

        return $tab;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Document_Item', $ong, $options);
        if (!isset($options['withtemplate']) || empty($options['withtemplate'])) {
            $this->addStandardTab(Task_Comment::class, $ong, $options);
            $this->addStandardTab(Ticket::class, $ong, $options);
        }
        $this->addStandardTab('Notepad', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     *
     */
    public function post_getEmpty()
    {
        $this->fields['priority'] = 3;
        $this->fields['percent_done'] = 0;
        $this->fields['visibility'] = 2;
    }


    public function getCloneRelations(): array
    {
        return [
            Document_Item::class,
            Notepad::class,
        ];
    }

    /**
     * @see CommonDBTM::cleanDBonPurge()
     *
     * @since 0.83.1
     **/
    public function cleanDBonPurge()
    {
        /// Task_Comment does not extends CommonDBConnexity
        $kbic = new Task_Comment();
        $kbic->deleteByCriteria(['plugin_tasklists_tasks_id' => $this->fields['id']]);

        // The ticket link table was not cleaned, although the opposite direction is:
        // Ticket::cleanForTicket() is wired on Hooks::ITEM_PURGE and removes the same rows when
        // it is the ticket that disappears. Purging a task therefore left rows pointing at an
        // identifier that no longer exists, and MySQL reuses AUTO_INCREMENT values on an InnoDB
        // table after a server restart or a restore - a task created later, possibly in another
        // entity and with a private visibility, then silently inherited the links of the purged
        // one and showed up in the tab of tickets its own visibility model excluded it from.
        // Ticket here is GlpiPlugin\Tasklists\Ticket, the link table of the plugin: this file
        // has no `use Ticket;`, so the name resolves in the plugin namespace and not to the core
        // class it shares its name with.
        $task_ticket = new Ticket();
        $task_ticket->deleteByCriteria(['plugin_tasklists_tasks_id' => $this->fields['id']]);
    }

    /**
     * Revalidate the context and the entity a task is being written into.
     *
     * plugin_tasklists_tasktypes_id and entities_id are posted by the client on both the full
     * form and the Kanban, where Kanban::showKanban() declares them as hidden fields, and
     * can(-1, CREATE) / can($id, UPDATE) say nothing about their values. The rules replayed
     * here are the ones the interface itself is built on: a context the caller may open
     * (TaskType::getKanbanColumns() and Kanban::showKanban() use exactly this pair of tests),
     * and an entity of the dropdown showForm() renders, which is restricted to the active
     * entities of the session.
     *
     * @param array $input
     *
     * @return array|false The input, or false to refuse the write.
     */
    /**
     * Replay, on the server, the restriction the User dropdowns of showForm() carry.
     *
     * The form renders users_id and users_id_requester with 'entity' => the entity of the task,
     * which restricts the offered list to the users holding a profile there. That restriction
     * lives in the client only: the sink accepted whatever identifier was posted back. The
     * criterion of the dropdown is exactly the entity set of the target user, which
     * Profile_User::getUserEntities() resolves, recursive profiles expanded.
     *
     * @param int $users_id
     * @param int $entities_id Entity of the task, not the active entity of the caller.
     *
     * @return bool
     */
    public static function isUserAllowedInEntity(int $users_id, int $entities_id): bool
    {
        $user = new User();
        if ($users_id <= 0 || !$user->getFromDB($users_id)) {
            return false;
        }

        $entities = array_map('intval', Profile_User::getUserEntities($users_id, true));

        return in_array($entities_id, $entities, true);
    }

    /**
     * Replay, on the server, the restriction the Group dropdown of showForm() carries.
     *
     * showForm() renders it with 'entity' => the entity of the task and
     * 'condition' => ['is_usergroup' => 1]. A dropdown restricted to an entity offers the rows
     * of that entity plus the recursive rows of its ancestors, which is what the test below
     * rebuilds. The is_usergroup flag matters as much as the entity: a group that is not a user
     * group has no members to notify and has no business owning a task.
     *
     * @param int $groups_id
     * @param int $entities_id Entity of the task, not the active entity of the caller.
     *
     * @return bool
     */
    public static function isGroupAllowedInEntity(int $groups_id, int $entities_id): bool
    {
        $group = new Group();
        if ($groups_id <= 0 || !$group->getFromDB($groups_id)) {
            return false;
        }
        if (!$group->fields['is_usergroup']) {
            return false;
        }

        $group_entity = (int) $group->fields['entities_id'];
        if ($group_entity === $entities_id) {
            return true;
        }
        if (!$group->fields['is_recursive']) {
            return false;
        }

        $dbu = new DbUtils();

        return in_array(
            $group_entity,
            array_map('intval', $dbu->getAncestorsOf('glpi_entities', $entities_id)),
            true,
        );
    }

    /**
     * Security: the three actor fields of the form were restricted to the entity of the task by
     * their dropdowns and by nothing else. The sink revalidated the context and the entity but
     * never the actors, so a caller posted the identifier of a user or of a group of any other
     * entity - identifiers are sequential, they are guessed, not discovered. The exit point is
     * the notification channel: post_addItem() raises "newtask", and
     * NotificationTargetTask::getGroupAddress() joins glpi_groups_users with no entity
     * restriction whatsoever, so the name and the content of the task were mailed to a
     * perimeter the entity isolation forbids. checkVisibility() holds that isolation for every
     * application read; it was the e-mail that walked around it.
     *
     * The validation bears on the entity of the TASK - the one the dropdowns were rendered with
     * and the one validatePostedContext() has just settled - and not on the active entity of
     * the caller, which may be an ancestor.
     *
     * @param array $input
     *
     * @return array|false The input, or false to refuse the write.
     */
    private function validatePostedActors($input)
    {
        // No session means no posted form: the mail collector rule creates tasks from the cron,
        // with actors coming from the rule action rather than from a client.
        if (Session::getLoginUserID() === false) {
            return $input;
        }

        $entities_id = (int) ($input['entities_id'] ?? $this->fields['entities_id'] ?? Session::getActiveEntity());

        foreach (['users_id', 'users_id_requester'] as $field) {
            if (!isset($input[$field]) || (int) $input[$field] <= 0) {
                continue;
            }
            if (!self::isUserAllowedInEntity((int) $input[$field], $entities_id)) {
                Session::addMessageAfterRedirect(
                    __('You are not allowed to use this user', 'tasklists'),
                    false,
                    ERROR,
                );
                return false;
            }
        }

        if (isset($input['groups_id']) && (int) $input['groups_id'] > 0) {
            if (!self::isGroupAllowedInEntity((int) $input['groups_id'], $entities_id)) {
                Session::addMessageAfterRedirect(
                    __('You are not allowed to use this group', 'tasklists'),
                    false,
                    ERROR,
                );
                return false;
            }
        }

        return $input;
    }

    private function validatePostedContext($input)
    {
        // No session means no posted form: the mail collector rule creates tasks from the
        // cron, with an entity coming from the rule action and no context at all.
        if (Session::getLoginUserID() === false) {
            return $input;
        }

        if (isset($input['plugin_tasklists_tasktypes_id']) && $input['plugin_tasklists_tasktypes_id'] > 0) {
            $tasktype = new TaskType();
            if (!$tasktype->getFromDB($input['plugin_tasklists_tasktypes_id'])
                || !Session::haveAccessToEntity($tasktype->fields['entities_id'], $tasktype->fields['is_recursive'])
                || !TypeVisibility::isUserHaveRight($input['plugin_tasklists_tasktypes_id'])) {
                Session::addMessageAfterRedirect(
                    __('You are not allowed to use this context', 'tasklists'),
                    false,
                    ERROR,
                );
                return false;
            }
        }

        // The Kanban drag and drop validates the state it posts against the states the task type
        // declares (ajax/kanban.php, update action), and that was the only path doing so: the
        // creation and edition forms accepted any row of the state table, including one
        // belonging to another type or another entity. A task parked in a state its type does
        // not declare is rendered in no column of its board and is unreachable from the dropdown
        // that would have moved it back. getAllowedStates() is the single source of truth all
        // the paths now share. The type used is the posted one when the input carries it, the
        // stored one otherwise, since the edition form posts the state on its own.
        if (isset($input['plugin_tasklists_taskstates_id'])) {
            $posted_state = (int) $input['plugin_tasklists_taskstates_id'];
            $tasktypes_id = (int) ($input['plugin_tasklists_tasktypes_id']
                ?? $this->fields['plugin_tasklists_tasktypes_id']
                ?? 0);
            // An unchanged value is always accepted: a state can be detached from a type after
            // the fact, and refusing it here would freeze every task still holding it.
            if ($posted_state !== (int) ($this->fields['plugin_tasklists_taskstates_id'] ?? -1)
                && !in_array($posted_state, self::getAllowedStates($tasktypes_id), true)) {
                Session::addMessageAfterRedirect(
                    __('You are not allowed to use this status', 'tasklists'),
                    false,
                    ERROR,
                );
                return false;
            }
        }

        if (isset($input['entities_id']) && !Session::haveAccessToEntity($input['entities_id'])) {
            return false;
        }

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->validatePostedContext($input);
        if ($input === false) {
            return false;
        }

        // The Kanban posts users_id as a hidden field too, so the author of a card was whatever
        // the client said it was: the task was attributed to a colleague, who then received the
        // notification raised by post_addItem(). front/task_comment.form.php already pins the
        // owner to the session for the same reason. canUpdate() is the plugin's administration
        // flag, the one getSpecificMassiveActions() reads to decide what a profile may do to
        // other people's tasks, so a profile holding it keeps the ability to file a task on
        // somebody else's behalf.
        if (Session::getLoginUserID() !== false && !static::canUpdate()) {
            $input['users_id'] = Session::getLoginUserID();
        }

        // After the pinning above, so that the value it forces is checked like any other: the
        // session user always belongs to the entity validatePostedContext() has just accepted,
        // so the test is free for him and closes the branch where canUpdate() lets the client
        // keep its own users_id.
        $input = $this->validatePostedActors($input);
        if ($input === false) {
            return false;
        }

        if (isset($input['due_date']) && empty($input['due_date'])) {
            $input['due_date'] = 'NULL';
        }
        if (isset($input['content'])) {
            $input['content'] = RichText::getSafeHtml($input['content'], true);
        }

        if (isset($input["id"]) && ($input["id"] > 0)) {
            $input["_oldID"] = $input["id"];
        }
        unset($input['id']);

        return $input;
    }

    public function post_addItem()
    {
        global $CFG_GLPI;

        if (!(isset($this->input['withtemplate'])
            || (isset($this->input['withtemplate'])
                && $this->input["withtemplate"] != 1))
        ) {
            if ($CFG_GLPI["notifications_mailing"]) {
                NotificationEvent::raiseEvent("newtask", $this);
            }
        }
    }

    public function prepareInputForUpdate($input)
    {
        // The same two fields are posted by the edition form, and CommonDBTM::update() does not
        // revalidate either of them: check($id, UPDATE) settles the row the caller is editing,
        // never the entity or the context they are moving it to.
        $input = $this->validatePostedContext($input);
        if ($input === false) {
            return false;
        }

        // Same reason as on add, and the same blind spot: check($id, UPDATE) settles the row
        // being edited, never the actors it is being reassigned to. The entity used is the one
        // the row is moving to when the input carries it, the current one otherwise.
        $input = $this->validatePostedActors($input);
        if ($input === false) {
            return false;
        }

        if (isset($input['due_date']) && empty($input['due_date'])) {
            $input['due_date'] = 'NULL';
        }
        // Mirror prepareInputForAdd(): sanitize the rich-text content on update as well, so the
        // stored value cannot carry unfiltered markup regardless of how a future path renders it.
        if (isset($input['content'])) {
            $input['content'] = RichText::getSafeHtml($input['content'], true);
        }
        if (isset($input['plugin_tasklists_taskstates_id'])) {
            $state = new TaskState();
            if ($state->getFromDB($input['plugin_tasklists_taskstates_id'])) {
                if ($state->getFinishedState()) {
                    $input['percent_done'] = 100;
                }
            }
        }
        if (isset($input['is_archived'])
            && $input['is_archived'] == 1) {
            $state = new TaskState();
            if ($state->getFromDB($this->fields['plugin_tasklists_taskstates_id'])) {
                if (!$state->getFinishedState()) {
                    Session::addMessageAfterRedirect(
                        __('You cannot archive a task with this state', 'tasklists'),
                        false,
                        ERROR,
                    );
                    return false;
                }
            }
        }
        return $input;
    }

    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @param int $history store changes history ? (default 1)
     *
     * @return void
     */
    public function post_updateItem($history = 1)
    {
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"]) {
            NotificationEvent::raiseEvent("updatetask", $this);
        }
    }


    /**
     * Actions done before the DELETE of the item in the database /
     * Maybe used to add another check for deletion
     *
     * @return bool : true if item need to be deleted else false
     **/
    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"]
            && !(isset($this->input['withtemplate'])
                || (isset($this->input['withtemplate'])
                    && $this->input["withtemplate"] != 1))
            && isset($this->input['_delete'])
        ) {
            NotificationEvent::raiseEvent("deletetask", $this);
        }

        return true;
    }


    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        global $CFG_GLPI;

        Html::initEditorSystem('comment');

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Capture every GLPI dropdown/input (they echo directly) into strings so the Twig
        // template can lay them out. User-supplied labels are auto-escaped by Twig; these
        // captured fragments are already-safe framework HTML rendered with |raw.
        $id_field = Html::hidden('id', ['value' => $ID]);

        ob_start();
        echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
        $name_field = ob_get_clean();

        $plugin_tasklists_tasktypes_id = $this->fields["plugin_tasklists_tasktypes_id"];
        if (isset($options['plugin_tasklists_tasktypes_id'])
            && $options['plugin_tasklists_tasktypes_id']) {
            $plugin_tasklists_tasktypes_id = $options['plugin_tasklists_tasktypes_id'];
        }
        $types = TypeVisibility::seeAllowedTypes();
        ob_start();
        Dropdown::show(TaskType::class, [
            'name' => "plugin_tasklists_tasktypes_id",
            'value' => $plugin_tasklists_tasktypes_id,
            'entity' => $this->fields["entities_id"],
            'condition' => ['id' => $types],
            'on_change' => "plugin_tasklists_load_states();",
        ]);
        $type_field = ob_get_clean();

        $priority = $this->fields['priority'];
        if (isset($options['priority'])
            && $options['priority']) {
            $priority = $options['priority'];
        }
        ob_start();
        CommonITILObject::dropdownPriority([
            'value' => $priority,
            'withmajor' => 1,
        ]);
        $priority_field = ob_get_clean();

        ob_start();
        Dropdown::showTimeStamp("actiontime", [
            'min' => HOUR_TIMESTAMP * 2,
            'max' => MONTH_TIMESTAMP * 2,
            'step' => HOUR_TIMESTAMP * 2,
            'value' => $this->fields["actiontime"],
        ]);
        $duration_field = ob_get_clean();

        $show_entity = false;
        $entity_field = '';
        $entity_js = '';
        if (isset($_SESSION["glpiactiveentities"])
            && count($_SESSION["glpiactiveentities"]) > 1
            && ($ID == 0 || (isset($options['withtemplate']) && ($options['withtemplate'] == 2)))) {
            $show_entity = true;
            $entities_id = $this->fields['entities_id'];
            if (isset($options['entities_id'])
                && $options['entities_id']) {
                $entities_id = $options['entities_id'];
            }
            ob_start();
            $rand_entity = Dropdown::show('Entity', [
                'name' => "entities_id",
                'value' => $entities_id,
                'entity' => $_SESSION["glpiactiveentities"],
                'is_recursive' => true,
                'on_change' => "plugin_tasklists_load_entities();",
            ]);
            $entity_field = ob_get_clean();

            $JS = "function plugin_tasklists_load_entities(){";
            $params = [
                'entities_id' => '__VALUE__',
                'entity' => $this->fields["entities_id"],
            ];
            $JS .= Ajax::updateItemJsCode(
                "plugin_tasklists_entity",
                $CFG_GLPI['root_doc'] . "/plugins/tasklists/ajax/inputEntity.php",
                $params,
                'dropdown_entities_id' . $rand_entity,
                false,
            );
            $JS .= "}";
            $entity_js = Html::scriptBlock($JS);
        }

        $client = $this->fields['client'];
        if (isset($options['client'])
            && $options['client']) {
            $client = $options['client'];
        }
        $client_field = Html::input('client', ['value' => $client, 'size' => 40]);

        ob_start();
        Html::showDateField("due_date", ['value' => $this->fields["due_date"]]);
        $due_date_field = ob_get_clean();

        $users_id_requester = $this->fields['users_id_requester'];
        if (isset($options['users_id_requester'])
            && $options['users_id_requester']) {
            $users_id_requester = $options['users_id_requester'];
        }
        ob_start();
        User::dropdown([
            'name' => "users_id_requester",
            'value' => $users_id_requester,
            'entity' => $this->fields["entities_id"],
            'right' => 'all',
        ]);
        $requester_field = ob_get_clean();

        $users_id = $this->fields['users_id'];
        if (isset($options['users_id'])
            && $options['users_id']) {
            $users_id = $options['users_id'];
        }
        ob_start();
        User::dropdown([
            'name' => "users_id",
            'value' => $users_id,
            'entity' => $this->fields["entities_id"],
            'right' => 'all',
        ]);
        $technician_field = ob_get_clean();

        ob_start();
        Dropdown::showNumber("percent_done", [
            'value' => $this->fields['percent_done'],
            'min' => 0,
            'max' => 100,
            'step' => 10,
            'unit' => '%',
        ]);
        $percent_field = ob_get_clean();

        $groups_id = $this->fields['groups_id'];
        if (isset($options['groups_id'])
            && $options['groups_id']) {
            $groups_id = $options['groups_id'];
        }
        ob_start();
        Dropdown::show('Group', [
            'name' => "groups_id",
            'value' => $groups_id,
            'entity' => $this->fields["entities_id"],
            'condition' => ['is_usergroup' => 1],
        ]);
        $group_field = ob_get_clean();

        ob_start();
        Dropdown::show(
            TaskState::class,
            ['value' => $this->fields["plugin_tasklists_taskstates_id"]],
        );
        $status_field = ob_get_clean();

        $rand_text = mt_rand();
        $content_id = "comment$rand_text";
        ob_start();
        Html::textarea([
            'name' => 'content',
            'value' => $this->fields["content"],
            'rand' => $rand_text,
            'editor_id' => $content_id,
            'enable_richtext' => true,
            'cols' => 100,
            'rows' => 15,
        ]);
        $content_field = ob_get_clean();

        $visibility = $this->fields['visibility'];
        if (isset($options['visibility'])
            && $options['visibility']) {
            $visibility = $options['visibility'];
        }
        ob_start();
        self::dropdownVisibility(['value' => $visibility]);
        $visibility_field = ob_get_clean();

        ob_start();
        Dropdown::showYesNo("is_archived", $this->fields["is_archived"]);
        $archived_field = ob_get_clean();

        TemplateRenderer::getInstance()->display('@tasklists/task/form.html.twig', [
            'id_field'         => $id_field,
            'name_field'       => $name_field,
            'type_field'       => $type_field,
            'priority_field'   => $priority_field,
            'duration_field'   => $duration_field,
            'show_entity'      => $show_entity,
            'entity_field'     => $entity_field,
            'entity_js'        => $entity_js,
            'client_field'     => $client_field,
            'due_date_field'   => $due_date_field,
            'requester_field'  => $requester_field,
            'technician_field' => $technician_field,
            'percent_field'    => $percent_field,
            'group_field'      => $group_field,
            'status_field'     => $status_field,
            'content_field'    => $content_field,
            'visibility_field' => $visibility_field,
            'archived_field'   => $archived_field,
        ]);

        $this->showFormButtons($options);

        return true;
    }


    /**
     * Identifiers of the states a task of the given type is allowed to hold.
     *
     * This is the rule the state dropdown is built on. It lives in its own method so that the
     * paths which accept a state from the client - the Kanban drag and drop of ajax/kanban.php
     * above all - can replay it at the point where the value is written, rather than restate
     * it and drift from it. Entity is deliberately not part of it: the dropdown itself does
     * not filter states on the entity, so filtering here would refuse values the interface
     * legitimately offers.
     *
     * @param int|string $plugin_tasklists_tasktypes_id
     *
     * @return int[] Backlog (0) included.
     */
    public static function getAllowedStates($plugin_tasklists_tasktypes_id): array
    {
        // Backlog is the pseudo state the dropdown prepends; it is the empty column of the
        // Kanban and carries no row of its own in the state table.
        $allowed = [0];

        $dbu = new DbUtils();
        $datastates = $dbu->getAllDataFromTable($dbu->getTableForItemType(TaskState::class));
        foreach ($datastates as $datastate) {
            if ($datastate['tasktypes'] == null) {
                continue;
            }
            $tasktypes = json_decode($datastate['tasktypes']);
            if (is_array($tasktypes) && in_array($plugin_tasklists_tasktypes_id, $tasktypes)) {
                $allowed[] = (int) $datastate['id'];
            }
        }

        return $allowed;
    }

    /**
     * Columns a Kanban card creation is allowed to set.
     *
     * The add-item form of the board declares five fields - see the supported_itemtypes array
     * built in src/Kanban.php - and the Vue component adds the column field of the board to the
     * payload. Nothing else belongs in a card creation, so ajax/kanban.php reduces the posted
     * inputs to this list rather than handing CommonDBTM a client-shaped array.
     *
     * @return string[]
     */
    public static function getKanbanCreationFields(): array
    {
        return [
            'name',
            'content',
            'entities_id',
            'users_id',
            'plugin_tasklists_tasktypes_id',
            'plugin_tasklists_taskstates_id',
        ];
    }

    /**
     * States by type dropdown list
     *
     * @param     $plugin_tasklists_tasktypes_id
     * @param int $plugin_tasklists_taskstates_id
     */
    public static function displayState($plugin_tasklists_tasktypes_id, $plugin_tasklists_taskstates_id = 0)
    {
        $states[] = [
            'id' => 0,
            'name' => __('Backlog', 'tasklists'),
            'rank' => 0,
        ];

        $allowed_states = self::getAllowedStates($plugin_tasklists_tasktypes_id);

        $states_ranked = [];
        $dbu = new DbUtils();
        $datastates = $dbu->getAllDataFromTable($dbu->getTableForItemType(TaskState::class));
        if (!empty($datastates)) {
            foreach ($datastates as $datastate) {
                if (in_array((int) $datastate['id'], $allowed_states, true)) {
                    if (empty(
                        $name = DropdownTranslation::getTranslatedValue(
                            $datastate['id'],
                            TaskState::class,
                            'name',
                            $_SESSION['glpilanguage'],
                        )
                    )) {
                        $name = $datastate['name'];
                    }
                    $states[] = [
                        'id' => $datastate['id'],
                        'name' => $name,
                    ];
                }
            }
        }
        foreach ($states as $k => $v) {
            $states_ranked[$v['id']] = $v['name'];
        }
        $rand = mt_rand();
        Dropdown::showFromArray('plugin_tasklists_taskstates_id', $states_ranked, [
            'rand' => $rand,
            'value' => $plugin_tasklists_taskstates_id,
            'display' => true,
        ]);
    }


    /**
     * Closed States for a task
     *
     * @param     $plugin_tasklists_tasks_id
     */
    public static function getClosedStateForTask($plugin_tasklists_tasks_id)
    {
        $task = new Task();
        if ($task->getFromDB($plugin_tasklists_tasks_id)) {
            $state = $task->fields["plugin_tasklists_taskstates_id"];
            $dbu = new DbUtils();
            $condition = ["is_finished" => 1];
            $datastates = $dbu->getAllDataFromTable($dbu->getTableForItemType(TaskState::class), $condition);
            if (!empty($datastates)) {
                foreach ($datastates as $datastate) {
                    $tasktypes = json_decode($datastate['tasktypes']);
                    if (is_array($tasktypes)) {
                        if (in_array($task->fields["plugin_tasklists_tasktypes_id"], $tasktypes)) {
                            $state = $datastate['id'];
                        }
                    }
                }
            }
            return $state;
        }
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function getStateName($value)
    {
        switch ($value) {
            case 0:
                return __('Backlog', 'tasklists');

            default:
                // Return $value if not define
                return Dropdown::getDropdownName("glpi_plugin_tasklists_taskstates", $value);
        }
    }

    /**
     * Make a select box for link tasklists
     *
     * Parameters which could be used in options array :
     *    - name : string / name of the select (default is documents_id)
     *    - entity : integer or array / restrict to a defined entity or array of entities
     *                   (default -1 : no restriction)
     *    - used : array / Already used items ID: not to display in dropdown (default empty)
     *
     * @param $options array of possible options
     *
     * @return nothing (print out an HTML select box)
     *
     * @throws \GlpitestSQLError
     */
    public static function dropdownTasklists($options = [])
    {
        global $DB, $CFG_GLPI;

        $p['name'] = 'plugin_tasklists_tasklists_id';
        $p['entity'] = '';
        $p['used'] = [];
        $p['display'] = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $rand = mt_rand();
        $dbu = new DbUtils();
        // NOTE: the legacy query filtered on the non-existent table
        // `glpi_plugin_tasklists_tasklists`; the columns actually live on
        // `glpi_plugin_tasklists_tasks`, which is also the subquery source.
        $tasks_table = 'glpi_plugin_tasklists_tasks';

        $sub_where = [
            "$tasks_table.is_deleted"  => 0,
            "$tasks_table.is_template" => 0,
        ];
        $entities_crit = $dbu->getEntitiesRestrictCriteria($tasks_table, '', $p['entity'], true);
        if (count($entities_crit)) {
            $sub_where[] = $entities_crit;
        }
        if (count($p['used'])) {
            $sub_where[] = [
                'NOT' => ["$tasks_table.id" => array_merge([0], array_map('intval', $p['used']))],
            ];
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_tasklists_tasktypes',
            'WHERE' => [
                'id' => new QuerySubQuery([
                    'SELECT'   => "$tasks_table.plugin_tasklists_tasktypes_id",
                    'DISTINCT' => true,
                    'FROM'     => $tasks_table,
                    'WHERE'    => $sub_where,
                ]),
            ],
            'ORDER' => 'name',
        ]);

        $values = [0 => Dropdown::EMPTY_VALUE];

        foreach ($iterator as $data) {
            $values[$data['id']] = $data['name'];
        }

        $out = Dropdown::showFromArray('_tasktype', $values, [
            'width' => '30%',
            'rand' => $rand,
            'display' => false,
        ]);
        $field_id = Html::cleanId("dropdown__tasktype$rand");

        $params = [
            'tasktypes' => '__VALUE__',
            'entity' => $p['entity'],
            'rand' => $rand,
            'myname' => $p['name'],
            'used' => $p['used'],
        ];

        $out .= Ajax::updateItemOnSelectEvent(
            $field_id,
            "show_" . $p['name'] . $rand,
            $CFG_GLPI['root_doc'] . "/plugins/tasklists/ajax/dropdownTypeTasks.php",
            $params,
            false,
        );

        $out .= "<span id='show_" . $p['name'] . "$rand'>";
        $out .= "</span>\n";

        $params['tasktype'] = 0;
        $out .= Ajax::updateItem(
            "show_" . $p['name'] . $rand,
            $CFG_GLPI['root_doc'] . "/plugins/tasklists/ajax/dropdownTypeTasks.php",
            $params,
            false,
        );
        if ($p['display']) {
            echo $out;
            return $rand;
        }
        return $out;
    }

    //Massive action

    /**
     * @param null $checkitem
     *
     * @return array
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
            if ($isadmin) {
                if (Session::haveRight('transfer', READ) && Session::isMultiEntitiesMode()
                ) {
                    $actions['GlpiPlugin\Tasklists\Task' . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __(
                        'Transfer',
                    );
                }
            }
        }
        return $actions;
    }

    /**
     * @param MassiveAction $ma
     *
     * @return bool|false
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     *
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case "transfer":
                Dropdown::show('Entity');
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
                break;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @param MassiveAction $ma
     * @param CommonDBTM $item
     * @param array $ids
     *
     * @return nothing|void
     * @throws \GlpitestSQLError
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     *
     * @since version 0.85
     *
     */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case "transfer":
                $input = $ma->getInput();
                if ($item->getType() == Task::class) {
                    foreach ($ids as $key) {
                        // Massive-action hardening: the core trusts previous stages and does
                        // NOT replay per-item rights at the process stage (MassiveAction), and
                        // the ids come straight from the POST. Re-check the UPDATE right and the
                        // plugin visibility model before mutating, and confine the transfer
                        // target to an entity the current user can actually access — otherwise a
                        // forged request could transfer another user's private/other-entity task
                        // into the attacker's entity (read escalation).
                        if (!$item->can((int) $key, UPDATE)
                            || !$item->checkVisibility((int) $key)
                            || !Session::haveAccessToEntity((int) $input['entities_id'])) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            continue;
                        }
                        $item->getFromDB($key);
                        $type = TaskType::transfer(
                            $item->fields["plugin_tasklists_tasktypes_id"],
                            $input['entities_id'],
                        );
                        if ($type > 0) {
                            $values["id"] = $key;
                            $values["plugin_tasklists_tasktypes_id"] = $type;
                            $item->update($values);
                        }
                        unset($values);
                        $values["id"] = $key;
                        $values["entities_id"] = $input['entities_id'];

                        if ($item->update($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                return;
        }

        // Standard actions (update / delete / purge / ...) are inherited from the core, which
        // replays can() (global right + entity) but NEVER the plugin's own visibility model.
        // Drop from $ids any task the current user may not see, so a forged POST at the process
        // stage cannot update/delete/purge another user's private (1) or group (2) tasks.
        if ($item->getType() == Task::class) {
            foreach ($ids as $index => $id) {
                if (!$item->checkVisibility((int) $id)) {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                    unset($ids[$index]);
                }
            }
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * For other plugins, add a type to the linkable types
     *
     * @param $type string class name
     * *@since version 1.3.0
     *
     */
    public static function registerType($type)
    {
        if (!in_array($type, self::$types)) {
            self::$types[] = $type;
        }
    }

    /**
     * Type than could be linked to a Rack
     *
     * @param $all boolean, all type, or only allowed ones
     *
     * @return array of types
     * */
    public static function getTypes($all = false)
    {
        if ($all) {
            return self::$types;
        }

        // Only allowed types
        $types = self::$types;

        foreach ($types as $key => $type) {
            if (!class_exists($type)) {
                continue;
            }

            $item = new $type();
            if (!$item->canView()) {
                unset($types[$key]);
            }
        }
        return $types;
    }

    /**
     * display a value according to a field
     *
     * @param $field     String         name of the field
     * @param $values    String / Array with the value to display
     * @param $options   Array          of option
     *
     * @return int|string string
     **@since version 0.83
     *
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'priority':
                return CommonITILObject::getPriorityName($values[$field]);
            case 'visibility':
                return self::getVisibilityName($values[$field]);
            case 'plugin_tasklists_taskstates_id':
                // getStateName() falls back on Dropdown::getDropdownName(), which returns the
                // raw name stored in glpi_plugin_tasklists_taskstates, and the value returned
                // here is spliced into the search results as HTML. Escape it as the core does
                // in its own getSpecificValueToDisplay() implementations (Contract::alert,
                // CommonITILObject::requesttypes_id). The two labels above are translated
                // constants and need nothing. getStateName() itself is left untouched because
                // NotificationTargetTask feeds it into the mail templates, where HTML entities
                // would be displayed literally.
                return htmlescape(self::getStateName($values[$field]));
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * @param $field
     * @param $name (default '')
     * @param $values (default '')
     * @param $options   array
     *
     * @return string
     **@since version 0.84
     *
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'priority':
                $options['name'] = $name;
                $options['value'] = $values[$field];
                $options['withmajor'] = 1;
                return CommonITILObject::dropdownPriority($options);

            case 'visibility':
                $options['name'] = $name;
                $options['value'] = $values[$field];
                return self::dropdownVisibility($options);

            case 'plugin_tasklists_taskstates_id':
                return Dropdown::show(TaskState::class, [
                    'name' => $name,
                    'value' => $values[$field],
                    'emptylabel' => __('Backlog', 'tasklists'),
                    'display' => false,
                    'width' => '200px',
                ]);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /*
     * @since  version 0.84 new proto
     *
     * @param $options array of options
     *       - name     : select name (default is urgency)
     *       - value    : default value (default 0)
     *       - showtype : list proposed : normal, search (default normal)
     *       - display  : boolean if false get string
     *
     * @return string id of the select
    **/
    /**
     * @param array $options
     *
     * @return int|string
     */
    public static function dropdownVisibility(array $options = [])
    {
        $p['name'] = 'visibility';
        $p['value'] = 0;
        $p['showtype'] = 'normal';
        $p['display'] = true;
        $p['withmajor'] = false;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $values = [];

        $values[1] = static::getVisibilityName(1);
        $values[2] = static::getVisibilityName(2);
        $values[3] = static::getVisibilityName(3);

        return Dropdown::showFromArray($p['name'], $values, $p);
    }

    /**
     * Get ITIL object priority Name
     *
     * @param $value priority ID
     *
     * @return priority|string
     */
    public static function getVisibilityName($value)
    {
        switch ($value) {
            case 1:
                return _x('visibility', 'This user', 'tasklists');

            case 2:
                return _x('visibility', 'This user and this group', 'tasklists');

            case 3:
                return _x('visibility', 'All', 'tasklists');

            default:
                // Return $value if not define
                return $value;
        }
    }

    /**
     * Enforce the task visibility model on every per-record right check.
     *
     * Task registers standard tabs (Notepad, Task_Comment, Document_Item, Log) that the
     * generic core loader ajax/common.tabs.php gates solely with $item->can($id, READ).
     * can() resolves to canView() && canViewItem(); the default canViewItem() only checks
     * the entity, so without this override a same-entity user holding the base plugin right
     * could read another user's private (visibility 1) or group-restricted (visibility 2)
     * task's notes, comments, documents and history through those tabs, bypassing
     * checkVisibility(). Overriding the can*Item() primitives closes every caller of
     * can() at a single point. parent:: is kept so the core entity check (checkEntity)
     * still runs — it must never be replaced by a bare global haveRight().
     *
     * @return bool
     */
    public function canViewItem(): bool
    {
        return parent::canViewItem() && $this->checkVisibility($this->fields['id']);
    }

    public function canUpdateItem(): bool
    {
        return parent::canUpdateItem() && $this->checkVisibility($this->fields['id']);
    }

    public function canDeleteItem(): bool
    {
        return parent::canDeleteItem() && $this->checkVisibility($this->fields['id']);
    }

    public function canPurgeItem(): bool
    {
        return parent::canPurgeItem() && $this->checkVisibility($this->fields['id']);
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function checkVisibility($id)
    {
        if (!$this->getFromDB($id)) {
            return false;
        }

        // Entity isolation: a task always stays within its entity tree, even when
        // marked public (visibility == 3). Without this gate the Kanban/dashboard
        // data paths (which rely solely on checkVisibility) would leak tasks of
        // other entities to any user holding the global plugin READ right.
        //
        // Security: plugin_tasklists_see_all used to return true above this gate, before the
        // record was even read. It relaxes the visibility model - 1 mine, 2 mine and my
        // groups', 3 everyone's - and nothing else: GLPI grants a right profile by profile AND
        // entity by entity, so holding it in one entity is not a pass to the instance. Order
        // mattered more here than anywhere else because the Kanban and dashboard paths have no
        // other entity boundary: TaskType::getKanbanColumns() filters its find() on the task
        // type, the state and the flags only, then delegates the whole decision to this method.
        if (!Session::haveAccessToEntity($this->fields['entities_id'], $this->fields['is_recursive'])) {
            return false;
        }
        if (Session::haveRight("plugin_tasklists_see_all", 1)) {
            return true;
        }

        $groupusers = Group_User::getGroupUsers($this->fields['groups_id']);
        $groups = [];
        foreach ($groupusers as $groupuser) {
            $groups[] = $groupuser["id"];
        }
        $users_id = Session::getLoginUserID();
        if (($this->fields['visibility'] == 1
                && ($this->fields['users_id'] == $users_id
                    || $this->fields['users_id_requester'] == $users_id))
            || ($this->fields['visibility'] == 2
                && ($this->fields['users_id'] == $users_id
                    || $this->fields['users_id_requester'] == $users_id
                    || in_array($users_id, $groups)))
            || ($this->fields['visibility'] == 3)) {
            return true;
        }
        return false;
    }

    /**
     * SQL counterpart of checkVisibility(), to restrict a listing of tasks.
     *
     * checkVisibility() decides record by record, which is what the form paths need; a
     * listing cannot afford to load every row to ask. The two must say the same thing,
     * otherwise a selector offers what the object then refuses - or, as was the case for the
     * two task dropdowns, offers names the caller is not allowed to read. The criteria below
     * are the literal transcription of the method above: the entity tree first, then the
     * visibility model unless plugin_tasklists_see_all relaxes it.
     *
     * @return array criteria to merge into a $DB->request() / Dropdown 'condition'
     */
    public static function getVisibilityCriteria(): array
    {
        $dbu   = new DbUtils();
        $table = self::getTable();

        $criteria      = [];
        $entities_crit = $dbu->getEntitiesRestrictCriteria($table, '', '', true);
        if (count($entities_crit)) {
            $criteria[] = $entities_crit;
        }

        if (Session::haveRight('plugin_tasklists_see_all', 1)) {
            return $criteria;
        }

        $users_id = (int) Session::getLoginUserID();
        $groups   = $_SESSION['glpigroups'] ?? [];

        $own = [
            'OR' => [
                "$table.users_id"           => $users_id,
                "$table.users_id_requester" => $users_id,
            ],
        ];
        $own_or_group = [
            'OR' => [
                "$table.users_id"           => $users_id,
                "$table.users_id_requester" => $users_id,
                // An empty group list must match nothing, not everything: a bare IN () is a
                // syntax error and an empty array is silently dropped by the builder.
                "$table.groups_id"          => count($groups) ? $groups : [-1],
            ],
        ];

        $criteria[] = [
            'OR' => [
                ["$table.visibility" => 1, $own],
                ["$table.visibility" => 2, $own_or_group],
                ["$table.visibility" => 3],
            ],
        ];

        return $criteria;
    }

    /**
     * @see Rule::getActions()
     * */
    public function getActions()
    {
        $actions = [];

        $actions['tasklists']['name'] = __('Affect entity for create task', 'tasklists');
        $actions['tasklists']['type'] = 'dropdown';
        $actions['tasklists']['table'] = 'glpi_entities';
        $actions['tasklists']['force_actions'] = ['send'];

        return $actions;
    }

    /**
     * Execute the actions as defined in the rule
     *
     * @param $action
     * @param $output the fields to manipulate
     * @param $params parameters
     *
     * @return the $output array modified
     */
    public function executeActions($action, $output, $params)
    {
        switch ($params['rule_itemtype']) {
            case 'RuleMailCollector':
                switch ($action->fields["field"]) {
                    case "tasklists":

                        if (isset($params['headers']['subject'])) {
                            $input['name'] = $params['headers']['subject'];
                        }
                        if (isset($params['ticket'])) {
                            $input['comment'] = strip_tags($params['ticket']['content']);
                        }
                        if (isset($params['headers']['from'])) {
                            $input['users_id'] = User::getOrImportByEmail($params['headers']['from']);
                        }

                        if (isset($action->fields["value"])) {
                            $input['entities_id'] = $action->fields["value"];
                        }
                        $input['state'] = 1;

                        if (isset($input['name'])
                            && $input['name'] !== false
                            && isset($input['entities_id'])
                        ) {
                            $this->add($input);
                        }
                        $output['_refuse_email_no_response'] = true;
                        break;
                }
        }
        return $output;
    }

    /**
     * Find the template task configured for the context named in $options, if any.
     *
     * @param array $options
     *
     * @return int|false identifier of the template, false when the context has none
     */
    public function hasTemplate($options)
    {
        $templates = [];
        $dbu = new DbUtils();
        $restrict = ["is_template" => 1]
            + ["is_deleted" => 0]
            + ["is_archived" => 0]
            + ["plugin_tasklists_tasktypes_id" => $options['plugin_tasklists_tasktypes_id']]
            //                  ["users_id" => Session::getLoginUserID()] +
            + $dbu->getEntitiesRestrictCriteria($this->getTable(), '', '', $this->maybeRecursive());

        $templates = $dbu->getAllDataFromTable($this->getTable(), $restrict);
        reset($templates);
        foreach ($templates as $template) {
            return (int) $template['id'];
        }
        return false;
    }


    /**
     * @param       $target
     * @param int $add
     * @param array $options
     */
    public function listOfTemplates($target, $add = 0)
    {
        $dbu = new DbUtils();

        // Every other read path of tasks in this plugin narrows by the plugin's own visibility
        // model - getVisibilityCriteria() in SQL for ajax/dropdownTypeTasks.php,
        // Kanban::showKanban() and the searches, checkVisibility() row by row for
        // ajax/seetask.php, ajax/updatetask.php and front/task.form.php. This one narrowed by
        // entity alone, so a template stored private (visibility 1) or restricted to a group
        // (visibility 2) was listed to every holder of the plugin READ right in the entity, name
        // and real identifier included - the ?id=<id>&withtemplate=2 link turned a blind
        // enumeration of identifiers into a directed one. is_deleted and is_archived are
        // excluded here too, to match hasTemplate(): templates sent to the bin stayed listed and
        // stayed clickable. getVisibilityCriteria() carries its own entity restriction, so the
        // getEntitiesRestrictCriteria() call it replaces is not lost.
        $restrict = ["is_template" => 1, "is_deleted" => 0, "is_archived" => 0]
            + self::getVisibilityCriteria()
            + ["ORDER" => "name"];

        $templates = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        // Defence in depth: getVisibilityCriteria() is the SQL mirror of checkVisibility(), and
        // the two have to keep agreeing. Replaying the row-by-row test costs one query per
        // template on a page that lists a handful of them, and it is the same belt-and-braces
        // pair the Kanban paths already apply. A dedicated instance is used so the loop does not
        // overwrite the fields of the object rendering the page.
        $visibility = new self();

        $multi_entities = Session::isMultiEntitiesMode();
        $colsup = $multi_entities ? 1 : 0;

        $rows = [];
        foreach ($templates as $template) {
            if (!$visibility->checkVisibility((int) $template["id"])) {
                continue;
            }

            // Only the delete form is framework HTML (rendered |raw); the entity label and
            // the template name are plain text, auto-escaped by Twig.
            $entity_name = '';
            if ($multi_entities) {
                $entity_name = Dropdown::getDropdownName("glpi_entities", $template['entities_id']);
            }

            $delete_form = '';
            if (!$add) {
                ob_start();
                Html::showSimpleForm(
                    $target,
                    'purge',
                    _x('button', 'Delete permanently'),
                    ['id' => $template["id"], 'withtemplate' => 1],
                );
                $delete_form = ob_get_clean();
            }

            $rows[] = [
                'id'          => (int) $template["id"],
                'name'        => $template["template_name"],
                'show_id'     => ($_SESSION["glpiis_ids_visible"] || empty($template["template_name"])),
                'entity_name' => $entity_name,
                'delete_form' => $delete_form,
            ];
        }

        TemplateRenderer::getInstance()->display('@tasklists/task/list_templates.html.twig', [
            'add'            => (bool) $add,
            'colspan'        => 2 + $colsup,
            'multi_entities' => $multi_entities,
            'target'         => $target,
            'typename'       => self::getTypeName(2),
            'templates'      => $rows,
        ]);
    }

    /**
     * @since 0.84
     **/
    public function loadActors()
    {
        //      if (!empty($this->grouplinkclass)) {
        //         $class        = new $this->grouplinkclass();
        $this->groups = [$this->fields['groups_id']];
        //      }

        //      if (!empty($this->userlinkclass)) {
        //         $class        = new $this->userlinkclass();
        $this->users = [$this->fields['users_id']];
        //      }
        //
        //      if (!empty($this->supplierlinkclass)) {
        //         $class            = new $this->supplierlinkclass();
        //         $this->suppliers  = $class->getActors($this->fields['id']);
        //      }
    }

    public static function getTeamItemtypes(): array
    {
        return ['User', 'Group'];
    }

    public function getTeam(): array
    {
        global $DB;

        $team = [];

        $team_itemtypes = static::getTeamItemtypes();

        /** @var CommonDBTM $itemtype */
        foreach ($team_itemtypes as $itemtype) {
            /** @var CommonDBTM $link_class */
            $link_class = null;
            switch ($itemtype) {
                case 'User':
                    $link_class = Task::class;
                    break;
                case 'Group':
                    $link_class = Task::class;
                    break;
            }

            if ($link_class === null) {
                continue;
            }

            $select = [];
            if ($itemtype === 'User') {
                $select = [
                    $link_class::getTable() . '.' . $itemtype::getForeignKeyField(),
                    $itemtype::getTable() . '.' . 'name',
                    'realname',
                    'firstname',
                ];
            } else {
                $select = [
                    $link_class::getTable() . '.' . $itemtype::getForeignKeyField(),
                    $itemtype::getTable() . '.' . 'name',
                    new QueryExpression('NULL as realname'),
                    new QueryExpression('NULL as firstname'),
                ];
            }

            $it = $DB->request([
                'SELECT' => $select,
                'FROM' => $link_class::getTable(),
                'WHERE' => [$link_class::getTable() . '.' . 'id' => $this->getID()],
                'LEFT JOIN' => [
                    $itemtype::getTable() => [
                        'ON' => [
                            $itemtype::getTable() => 'id',
                            $link_class::getTable() => $itemtype::getForeignKeyField(),
                        ],
                    ],
                ],
            ]);
            foreach ($it as $data) {
                $items_id = $data[$itemtype::getForeignKeyField()];
                if ($items_id <= 0) {
                    continue;
                }
                $member = [
                    'itemtype' => $itemtype,
                    'items_id' => $items_id,
                    'id' => $items_id,
                    'role' => 2,
                    'name' => $data['name'],
                    'realname' => $data['realname'],
                    'firstname' => $data['firstname'],
                    'display_name' => formatUserName($items_id, $data['name'], $data['realname'], $data['firstname']),
                ];
                $team[] = $member;
            }
        }

        return $team;
    }


    public static function getTeamRoles(): array
    {
        return [
            CommonITILActor::ASSIGN,
        ];
    }

    public static function getTeamRoleName(int $role, int $nb = 1): string
    {
        switch ($role) {
            case CommonITILActor::ASSIGN:
                return _n('Assignee', 'Assignees', $nb);
        }
        return '';
    }


    public function addTeamMember(string $itemtype, int $items_id, array $params = []): bool
    {
        $role = CommonITILActor::ASSIGN;

        /** @var CommonDBTM $link_class */
        $link_class = null;
        // $field used to be left undeclared, so the two branches of the switch were the only
        // thing defining it: on any other itemtype the method reached the update() below with an
        // undefined variable, which PHP evaluates as null and the query builder turns into a
        // meaningless column name. Declaring it here and testing it with $link_class makes the
        // refusal explicit and drops the exception that was carried in the baseline.
        $field      = null;
        switch ($itemtype) {
            case 'User':
                $link_class = Task::class;
                $field = "users_id";
                break;
            case 'Group':
                $link_class = Task::class;
                $field = "groups_id";
                break;
        }

        if ($link_class === null || $field === null) {
            return false;
        }

        // The endpoint that calls this method (ajax/kanban.php, action add_teammember) hands
        // over items_id_teammember straight from the request, so the method cannot assume its
        // caller filtered anything: it wrote an arbitrary identifier into users_id or groups_id
        // without checking that the target exists, that it belongs to the entity of the task,
        // or - for a group - that it is a user group at all. prepareInputForUpdate() below now
        // replays the same test through update(), but refusing here returns false to the client
        // instead of relying on a side effect of the model.
        // Defence in depth on the task itself, next to the write: this method is the
        // TeamworkInterface entry point, it reassigns the task, and its caller
        // (ajax/kanban.php, action add_teammember) is one of the $nonkanban_actions - the ones
        // the Kanban context check deliberately skips - so the guard up there is the only thing
        // standing between a request and the reassignment. Replaying the visibility model here
        // means the boundary no longer depends on a single remote call site.
        if (!$this->checkVisibility($this->getID())) {
            return false;
        }

        $entities_id = (int) $this->fields['entities_id'];
        if ($field === 'users_id' && !self::isUserAllowedInEntity($items_id, $entities_id)) {
            return false;
        }
        if ($field === 'groups_id' && !self::isGroupAllowedInEntity($items_id, $entities_id)) {
            return false;
        }

        $link_item = new $link_class();
        /** @var CommonDBTM $itemtype */
        $result = $link_item->update([
            $field => $items_id,
            'id' => $this->getID(),
        ]);
        return (bool) $result;
    }

    public function deleteTeamMember(string $itemtype, int $items_id, array $params = []): bool
    {
        $role = CommonITILActor::ASSIGN;

        /** @var CommonDBTM $link_class */
        $link_class = null;
        // Same declaration as addTeamMember() above.
        $field      = null;
        switch ($itemtype) {
            case 'User':
                $link_class = Task::class;
                $field = "users_id";
                break;
            case 'Group':
                $link_class = Task::class;
                $field = "groups_id";
                break;
        }

        if ($link_class === null || $field === null) {
            return false;
        }

        $link_item = new $link_class();
        /** @var CommonDBTM $itemtype */
        $result = $link_item->update([
            $field => '0',
            'id' => $this->getID(),
        ]);
        return (bool) $result;
    }

    public static function getDataToDisplayOnKanban($ID, $criteria = [])
    {
        // TODO: Implement getDataToDisplayOnKanban() method.
    }

    public static function getKanbanColumns($ID, $column_field = null, $column_ids = [], $get_default = false)
    {
        // TODO: Implement getKanbanColumns() method.
    }

    public static function showKanban($ID)
    {
        // TODO: Implement showKanban() method.
    }

    public static function getAllForKanban($active = true, $current_id = -1)
    {
        // TODO: Implement getAllForKanban() method.
    }

    public static function getAllKanbanColumns($column_field = null, $column_ids = [], $get_default = false)
    {
        // TODO: Implement getAllKanbanColumns() method.
    }
}
