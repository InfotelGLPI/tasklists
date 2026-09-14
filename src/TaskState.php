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

use CommonDropdown;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

// Class for a Dropdown

/**
 * Class TaskState
 */
class TaskState extends CommonDropdown
{
    public static $rightname = 'plugin_tasklists_config';

    // canCreate(), canUpdate(), canDelete() and canPurge() used to be overridden here, all
    // four returning Session::haveRight(static::$rightname, READ). The right was registered
    // with the READ bit alone, so those overrides were the only thing making the dropdown
    // writable - and they made "may read the configuration" and "may rewrite the statuses"
    // the same permission, through Glpi\Controller\DropdownFormController which calls
    // check(-1, CREATE) / check($id, UPDATE) / check($id, PURGE). The write bits are now
    // declared in Profile::getAllRights(), the inherited CommonDropdown implementations test
    // them, and the profile matrix can finally express the difference.

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Status', 'Statuses', $nb, 'tasklists');
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $from_edit_ajax = isset($options['from_edit_ajax']) && $options['from_edit_ajax'];

        $name_field = Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);

        $from_edit_ajax_field = '';
        if ($from_edit_ajax) {
            $from_edit_ajax_field = Html::hidden('from_edit_ajax', ['value' => $options['from_edit_ajax']]);
        }

        ob_start();
        Html::textarea(['name'            => 'comment',
            'value'           => $this->fields['comment'],
            'id'           => 'comment',
            'cols'       => 45,
            'rows'       => 3,
            'enable_richtext' => false]);
        $comment_field = ob_get_clean();

        ob_start();
        Html::showColorField('color', ['value' => $this->fields['color']]);
        $color_field = ob_get_clean();

        ob_start();
        Dropdown::showYesNo('is_finished', $this->fields['is_finished']);
        $finished_field = ob_get_clean();

        $tasktypes_hidden = Html::hidden("tasktypes");
        $tasktypes_field  = '';

        if (!$from_edit_ajax) {
            $possible_values = [];
            $dbu             = new DbUtils();
            // glpi_plugin_tasklists_tasktypes carries entities_id and is_recursive, and every
            // other read path of this plugin restricts on them - Kanban::showKanban(),
            // ajax/dropdownTypeTasks.php, TaskType::getAllForKanban(). This one listed the whole
            // table, so the form of a task state disclosed the name of every context of every
            // entity of the instance, including those of entities the caller has no access to.
            $tasktype_table  = $dbu->getTableForItemType(TaskType::class);
            $datatypes       = $dbu->getAllDataFromTable(
                $tasktype_table,
                $dbu->getEntitiesRestrictCriteria($tasktype_table, '', $_SESSION['glpiactiveentities'], true),
            );
            if (!empty($datatypes)) {
                foreach ($datatypes as $datatype) {
                    $possible_values[$datatype['id']] = $datatype['name'];
                }
            }
            $values = [];
            if ($this->fields['tasktypes'] != null) {
                $values = json_decode($this->fields['tasktypes']);
            }

            if (!is_array($values)) {
                $values = [];
            }

            $tasktypes_field = Dropdown::showFromArray(
                "tasktypes",
                $possible_values,
                ['values'   => $values,
                    'multiple' => 'multiples',
                    'display'  => false],
            );
        }

        TemplateRenderer::getInstance()->display('@tasklists/taskstate/form.html.twig', [
            'name_field'           => $name_field,
            'from_edit_ajax'       => $from_edit_ajax,
            'from_edit_ajax_field' => $from_edit_ajax_field,
            'comment_field'        => $comment_field,
            'color_field'          => $color_field,
            'finished_field'       => $finished_field,
            'tasktypes_hidden'     => $tasktypes_hidden,
            'tasktypes_field'      => $tasktypes_field,
        ]);

        $this->showFormButtons($options);

        return true;
    }


    /**
     * @return array
     */
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'         => 11,
            'table'      => $this->getTable(),
            'field'      => 'color',
            'name'       => __('Color'),
            'searchtype' => 'contains',
            'datatype'   => 'specific',
        ];

        $tab[] = [
            'id'           => '12',
            'table'        => $this->getTable(),
            'field'        => 'tasktypes',
            'name'         => _n('Context', 'Contexts', 1, 'tasklists'),
            'nosearch'     => true,
            'masiveaction' => false,
            'datatype'     => 'specific',
        ];

        $tab[] = [
            'id'       => '13',
            'table'    => $this->getTable(),
            'field'    => 'is_finished',
            'name'     => __('Finished state'),
            'datatype' => 'bool',
        ];

        return $tab;
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForAdd($input)
    {
        //      if (!$this->checkMandatoryFields($input)) {
        //         return false;
        //      }

        $input = $this->sanitizeColorInput($input);
        return $this->encodeSubtypes($input);
    }

    /**
     * Drop a color value that is not a valid hex code, so a forged value can never be
     * stored and later reflected into a style attribute (defense in depth for the color
     * display escaping in getSpecificValueToDisplay).
     *
     * @param array $input
     *
     * @return array
     */
    private function sanitizeColorInput($input)
    {
        if (isset($input['color'])
            && !preg_match('/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', (string) $input['color'])) {
            unset($input['color']);
        }
        return $input;
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        //      if (!$this->checkMandatoryFields($input)) {
        //         return false;
        //      }

        $input = $this->sanitizeColorInput($input);
        return $this->encodeSubtypes($input);
    }

    /**
     * Encode sub types
     *
     * @param array $input
     *
     * @return array
     */
    public function encodeSubtypes($input)
    {
        if (!empty($input['tasktypes'])) {
            // The value comes from a multiple select, but nothing guarantees the request keeps
            // that shape: a scalar reached array_values() and raised a fatal TypeError on PHP 8.
            // The identifiers are then confronted with the very criterion showForm() applies to
            // the list it offers, so a context belonging to another entity cannot be linked by
            // replaying the POST.
            $posted   = is_array($input['tasktypes']) ? $input['tasktypes'] : [$input['tasktypes']];
            $tasktype = new TaskType();
            $allowed  = [];
            foreach ($posted as $tasktypes_id) {
                if (!is_scalar($tasktypes_id)) {
                    continue;
                }
                $tasktypes_id = (int) $tasktypes_id;
                if ($tasktypes_id <= 0 || !$tasktype->getFromDB($tasktypes_id)) {
                    continue;
                }
                if (!Session::haveAccessToEntity(
                    $tasktype->fields['entities_id'],
                    $tasktype->fields['is_recursive'],
                )) {
                    continue;
                }
                $allowed[$tasktypes_id] = $tasktypes_id;
            }

            $input['tasktypes'] = json_encode(array_values($allowed));
        }

        return $input;
    }

    /**
     * @param $field
     * @param $name (default '')
     * @param $values (default '')
     * @param $options      array
     **@since 0.84
     *
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $dbu = new DbUtils();
        switch ($field) {
            case 'tasktypes':
                // Same unrestricted listing as showForm() carried, restricted for the same
                // reason: this dropdown feeds the search criteria form, which is reachable by
                // any profile holding the plugin READ right.
                $possible_values = [];
                $tasktype_table  = $dbu->getTableForItemType(TaskType::class);
                $datatypes       = $dbu->getAllDataFromTable(
                    $tasktype_table,
                    $dbu->getEntitiesRestrictCriteria($tasktype_table, '', $_SESSION['glpiactiveentities'], true),
                );
                if (!empty($datatypes)) {
                    foreach ($datatypes as $datatype) {
                        $possible_values[$datatype['id']] = $datatype['name'];
                    }
                }

                return Dropdown::showFromArray(
                    $name,
                    $possible_values,
                    ['display'  => false,
                        'value'    => $values[$field],
                        'multiple' => 'multiples'],
                );

                break;
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }


    /**
     * @param $field
     * @param $values
     * @param $options   array
     **
     *
     * @return string
     * @since 0.84
     *
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'tasktypes':
                $types = json_decode($values[$field]);
                if (!is_array($types)) {
                    return "&nbsp;";
                }
                $names    = [];
                $tasktype = new TaskType();
                foreach ($types as $type) {
                    if ($tasktype->getFromDB($type)) {
                        // Security (stored XSS): whatever this method returns is inserted as
                        // HTML into the search result cell by the core search engine - the
                        // neighbouring 'color' case escapes for exactly that reason. Context
                        // names are written by holders of plugin_tasklists_config, a right that
                        // is granted per entity, while task states are listed across entities:
                        // a payload stored in the name of a context in one entity executed in
                        // the session of anyone displaying the "Contexts" column elsewhere.
                        // Task::getSpecificValueToDisplay() was fixed the same way.
                        $names[] = htmlescape($tasktype->fields['name']);
                    }
                }
                $out = implode(", ", $names);
                return $out;
            case 'color':
                // Stored as raw data (GLPI 10+): escape before injecting into the style
                // attribute so a forged color value cannot break out of it (stored XSS).
                return sprintf(
                    "<div style='background-color: %s;'>&nbsp;</div>",
                    htmlescape((string) $values[$field]),
                );
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * @return mixed
     */
    public function getFinishedState()
    {
        return $this->fields['is_finished'];
    }

    /**
     * @return mixed
     */
    //   static function getAllKanbanColumns() {
    //
    //      $taskStates = new self();
    //      $columns    = ['plugin_tasklists_taskstates_id' => []];
    //      $restrict   = [];
    //      $allstates  = $taskStates->find($restrict, ['is_finished ASC', 'id']);
    //      foreach ($allstates as $state) {
    //         $columns['plugin_tasklists_taskstates_id'][$state['id']] = [
    //            'name'         => $state['name'],
    //            'header_color' => $state['color']
    //         ];
    //      }
    //
    //      return $columns['plugin_tasklists_taskstates_id'];
    //
    //   }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return boolean
     **/
}
