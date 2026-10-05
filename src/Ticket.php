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

use CommonDBTM;
use CommonGLPI;
use CommonITILObject;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use Html;
use Session;
use Toolbox;

/**
 * Class Ticket
 */
class Ticket extends CommonDBTM
{
    public static string $rightname = 'plugin_tasklists';

    /**
     * The link table carries no entities_id, so checkEntity() is a no-op and can($id, PURGE)
     * (used by the massive actions) only tested the global right: any id could be purged
     * across entities. Delegate the item-level checks to both ends of the link.
     */
    private function canAccessLinkedItems(int $task_right): bool
    {
        $tasks_id = (int) ($this->fields['plugin_tasklists_tasks_id'] ?? 0);
        $task     = new Task();

        return (new \Ticket())->can((int) ($this->fields['tickets_id'] ?? 0), READ)
            && $task->can($tasks_id, $task_right)
            && $task->checkVisibility($tasks_id);
    }

    public function canViewItem(): bool
    {
        return parent::canViewItem() && $this->canAccessLinkedItems(READ);
    }

    public function canUpdateItem(): bool
    {
        return parent::canUpdateItem() && $this->canAccessLinkedItems(UPDATE);
    }

    public function canPurgeItem(): bool
    {
        return parent::canPurgeItem() && $this->canAccessLinkedItems(UPDATE);
    }

    /**
     * Returns the type name with consideration of plural
     *
     * @param int $nb Number of item(s)
     *
     * @return string Itemtype name
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Ticket', 'Tickets', $nb);
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return Task::getIcon();
    }

    /**
    * Return the name of the tab for item including forms like the config page
    *
    * @param CommonGLPI $item Instance of a CommonGLPI Item (The Config Item)
    * @param integer    $withtemplate
    *
    * @return String                   Name to be displayed
    */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $dbu = new DbUtils();
        if (Session::getCurrentInterface() == 'central' && Session::haveRight(self::$rightname, READ)) {
            switch ($item->getType()) {
                case Task::class:
                    $nb = 0;
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = $dbu->countElementsInTable(
                            'glpi_plugin_tasklists_tickets',
                            ["plugin_tasklists_tasks_id" => $item->getID()],
                        );
                    }
                    return self::createTabEntry(self::getTypeName(2), $nb);
                    break;
                case "Ticket":
                    $nb = 0;
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = $dbu->countElementsInTable(
                            'glpi_plugin_tasklists_tickets',
                            ["tickets_id" => $item->getID()],
                        );
                    }
                    return self::createTabEntry(_n('Linked task', 'Linked tasks', $nb, 'tasklists'), $nb);
                    break;
            }
        }
        return '';
    }

    /**
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     *
     * @return void
     * @throws \GlpitestSQLError
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $ticket = new self();

        switch ($item->getType()) {
            case Task::class:
                $ID = $item->getField('id');
                $ticket->showForTask($ID);
                break;
            case "Ticket":
                $ticket->showForTicket($item);
                break;
        }
    }

    /**
     * @param $item
     */
    public static function cleanForTicket($item)
    {

        $temp = new self();
        $temp->deleteByCriteria(['tickets_id' => $item->getID()]);
    }

    /**
     * @param $ticket
     *
     * @return bool
     * @throws \GlpitestSQLError
     */
    public function showForTicket($ticket)
    {
        global $DB;

        $ID = $ticket->getField('id');
        if (!$ticket->can($ID, READ)) {
            return false;
        }

        $canedit = $ticket->canEdit($ID);
        $rand    = mt_rand();

        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_plugin_tasklists_tasks.*',
                'glpi_plugin_tasklists_tickets.id AS LinkID',
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_plugin_tasklists_tickets',
            'LEFT JOIN' => [
                'glpi_plugin_tasklists_tasks' => [
                    'ON' => [
                        'glpi_plugin_tasklists_tickets' => 'plugin_tasklists_tasks_id',
                        'glpi_plugin_tasklists_tasks'   => 'id',
                    ],
                ],
            ],
            'WHERE'   => ['glpi_plugin_tasklists_tickets.tickets_id' => (int) $ID],
            'ORDERBY' => 'glpi_plugin_tasklists_tasks.date_creation',
        ]);
        $number  = count($iterator);
        $numrows = $number;

        $tickets = [];
        $used    = [];
        foreach ($iterator as $data) {
            $tickets[$data['id']] = $data;
            $used[$data['id']]    = $data['id'];
        }
        if ($canedit) {
            // Same omission as ajax/dropdownTypeTasks.php: the generic dropdown restricts on the
            // entity passed to it, never on the visibility model of the plugin, so the selector
            // offered the names of private and group-restricted tasks of other users.
            TemplateRenderer::getInstance()->display('@tasklists/ticket/link_form.html.twig', [
                'action'       => Toolbox::getItemTypeFormURL(__CLASS__),
                'title'        => __('Add task', 'tasklists'),
                'hidden_name'  => 'tickets_id',
                'hidden_value' => (int) $ID,
                'selector'     => Task::dropdown([
                    'used'      => $used,
                    'entity'    => $ticket->getEntityID(),
                    'condition' => array_merge([
                        'is_archived' => 0,
                        'is_deleted'  => 0,
                        'is_template' => 0,
                    ], Task::getVisibilityCriteria()),
                    'display'   => false,
                ]),
                'button_name'  => 'add',
                'button_label' => _x('button', 'Add'),
            ]);
        }

        $entries = [];
        $task    = new Task();
        foreach ($tickets as $data) {
            // Defense in depth: never disclose the name/content of a task the caller is not
            // allowed to see, even if a link row was forged (see front/ticket.form.php add path).
            if (!$task->checkVisibility((int) $data['id'])) {
                continue;
            }
            $entries[] = [
                // Massive actions act on the link row
                'itemtype'    => __CLASS__,
                'id'          => $data['LinkID'],
                'name'        => sprintf(
                    '<a href="%s">%s</a>',
                    htmlescape(Toolbox::getItemTypeFormURL(Task::class) . '?id=' . (int) $data['id']),
                    htmlescape($data['name']),
                ),
                'date'        => Html::convDateTime($data['date_creation'], 1),
                'priority'    => CommonITILObject::getPriorityName($data['priority']),
                // Plain text, escaped by the default formatter
                // Task content is stored encoded (Task::prepareInputForAdd(), getSafeHtml(..., true)):
                // decoded before the tags are stripped, escaped once by the default formatter
                'description' => Html::resume_text(
                    RichText::getTextFromHtml(html_entity_decode((string) $data['content'], ENT_QUOTES | ENT_HTML5), false),
                    80,
                ),
                'row_class'   => '',
            ];
        }

        $container = 'mass' . str_replace('\\', '', __CLASS__) . $rand;
        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab'              => true,
            'nofilter'            => true,
            'nosort'              => true,
            'super_header'        => _n('Linked task', 'Linked tasks', count($entries), 'tasklists'),
            'columns'             => [
                'name'        => __('Name'),
                'date'        => __('Date'),
                'priority'    => __('Priority'),
                'description' => __('Description'),
            ],
            'formatters'          => [
                'name' => 'raw_html',
            ],
            'entries'             => $entries,
            'total_number'        => count($entries),
            'filtered_number'     => count($entries),
            'showmassiveactions'  => $canedit,
            'massiveactionparams' => [
                'num_displayed'    => count($entries),
                'specific_actions' => ['purge' => _x('button', 'Delete permanently')],
                'container'        => $container,
            ],
        ]);
    }

    /**
     * @param       $ID
     * @param array $options
     */
    public function showForTask($ID)
    {

        $task   = new Task();
        $ticket = new Ticket();

        $task->getFromDB($ID);
        // Only offer the link form to users allowed to write it (front/task.form.php
        // ticket_link requires UPDATE + visibility on the task).
        $canedit = $task->can($ID, UPDATE) && $task->checkVisibility((int) $ID);
        if ($canedit) {
            TemplateRenderer::getInstance()->display('@tasklists/ticket/link_form.html.twig', [
                'action'       => Toolbox::getItemTypeFormURL(Task::class),
                'title'        => __('Link a existant ticket', 'tasklists'),
                'hidden_name'  => 'plugin_tasklists_tasks_id',
                'hidden_value' => (int) $ID,
                'selector'     => \Ticket::dropdown([
                    'name'        => "tickets_id",
                    'entity'      => $task->getEntityID(),
                    'entity_sons' => $task->isRecursive(),
                    'displaywith' => ['id'],
                    'display'     => false,
                ]),
                'button_name'  => 'ticket_link',
                'button_label' => _x('button', 'Save'),
            ]);
        }

        $task_ticket = new Ticket();
        $tickets     = $task_ticket->find(['plugin_tasklists_tasks_id' => $task->fields['id']]);

        // Use a real core ticket object: the plugin Ticket class is only the link table
        // (rightname plugin_tasklists), so its can()/fields would be wrong here.
        $core_ticket = new \Ticket();
        $entries     = [];
        foreach ($tickets as $data) {
            // can(READ) validates the global right AND the entity: never disclose the
            // title/date/status/priority of a linked ticket the caller cannot read
            // (closes the cross-entity ticket enumeration via forged ticket_link).
            if (!$core_ticket->can((int) $data['tickets_id'], READ)) {
                continue;
            }
            $entries[] = [
                'name'     => $core_ticket->getLink(),
                'date'     => Html::convDateTime($core_ticket->fields["date"]),
                'status'   => \Ticket::getStatus($core_ticket->fields["status"]),
                'priority' => CommonITILObject::getPriorityName($core_ticket->fields["priority"]),
            ];
        }

        if (count($entries) > 0) {
            TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
                'is_tab'          => true,
                'nofilter'        => true,
                'nosort'          => true,
                'super_header'    => __('Linked tickets', 'tasklists'),
                'columns'         => [
                    'name'     => __('Name'),
                    'date'     => __('Date'),
                    'status'   => __('Status'),
                    'priority' => __('Priority'),
                ],
                'formatters'      => [
                    'name' => 'raw_html',
                ],
                'entries'         => $entries,
                'total_number'    => count($entries),
                'filtered_number' => count($entries),
            ]);
        }
    }
}
