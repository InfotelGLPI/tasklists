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
 * Class PluginTasklistsTask
 */
class PluginTasklistsTask extends CommonDBTM {

   public    $dohistory  = true;
   static    $rightname  = 'plugin_tasklists';
   protected $usenotepad = true;
   static    $types      = [];

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return _n('Task', 'Tasks', $nb);
   }


   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => self::getTypeName(2)
      ];

      $tab[] = [
         'id'            => '1',
         'table'         => $this->getTable(),
         'field'         => 'name',
         'name'          => __('Name'),
         'datatype'      => 'itemlink',
         'itemlink_type' => $this->getType()
      ];

      $tab[] = [
         'id'       => '2',
         'table'    => 'glpi_plugin_tasklists_tasktypes',
         'field'    => 'name',
         'name'     => _n('Context', 'Contexts', 1, 'tasklists'),
         'datatype' => 'dropdown'
      ];

      $tab[] = [
         'id'        => '3',
         'table'     => 'glpi_users',
         'field'     => 'name',
         'linkfield' => 'users_id',
         'name'      => __('User'),
         'datatype'  => 'dropdown'
      ];

      $tab[] = [
         'id'            => '4',
         'table'         => $this->getTable(),
         'field'         => 'actiontime',
         'name'          => __('Planned duration'),
         'datatype'      => 'timestamp',
         'massiveaction' => false
      ];

      $tab[] = [
         'id'       => '5',
         'table'    => $this->getTable(),
         'field'    => 'percent_done',
         'name'     => __('Percent done'),
         'datatype' => 'number',
         'unit'     => '%',
         'min'      => 0,
         'max'      => 100,
         'step'     => 5
      ];

      $tab[] = [
         'id'       => '6',
         'table'    => $this->getTable(),
         'field'    => 'due_date',
         'name'     => __('Due date', 'tasklists'),
         'datatype' => 'date'
      ];

      $tab[] = [
         'id'       => '7',
         'table'    => $this->getTable(),
         'field'    => 'comment',
         'name'     => __('Description'),
         'datatype' => 'text'
      ];

      $tab[] = [
         'id'         => '8',
         'table'      => $this->getTable(),
         'field'      => 'priority',
         'name'       => __('Priority'),
         'searchtype' => 'equals',
         'datatype'   => 'specific'
      ];

      $tab[] = [
         'id'            => '9',
         'table'         => $this->getTable(),
         'field'         => 'visibility',
         'name'          => __('Visibility'),
         'searchtype'    => 'equals',
         'datatype'      => 'specific',
         'massiveaction' => false
      ];

      $tab[] = [
         'id'        => '10',
         'table'     => 'glpi_groups',
         'field'     => 'name',
         'linkfield' => 'groups_id',
         'name'      => __('Group'),
         'condition' => '`is_assign`',
         'datatype'  => 'dropdown'
      ];

      $tab[] = [
         'id'         => '11',
         'table'      => $this->getTable(),
         'field'      => 'state',
         'name'       => __('Status'),
         'searchtype' => 'equals',
         'datatype'   => 'specific'
      ];

      $tab[] = [
         'id'            => '12',
         'table'         => $this->getTable(),
         'field'         => 'date_mod',
         'massiveaction' => false,
         'name'          => __('Last update'),
         'datatype'      => 'datetime'
      ];

      $tab[] = [
         'id'       => '18',
         'table'    => $this->getTable(),
         'field'    => 'is_recursive',
         'name'     => __('Child entities'),
         'datatype' => 'bool'
      ];

      $tab[] = [
         'id'       => '30',
         'table'    => $this->getTable(),
         'field'    => 'id',
         'name'     => __('ID'),
         'datatype' => 'number'
      ];

      $tab[] = [
         'id'       => '80',
         'table'    => 'glpi_entities',
         'field'    => 'completename',
         'name'     => __('Entity'),
         'datatype' => 'dropdown'
      ];
      return $tab;
   }

   /**
    * @param array $options
    *
    * @return array
    */
   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('Notepad', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   /**
    *
    */
   function post_getEmpty() {

      $this->fields['priority']     = 3;
      $this->fields['percent_done'] = 0;
      $this->fields['users_id']     = Session::getLoginUserID();
      $this->fields['state']        = Planning::TODO;
   }

   /**
    * @param datas $input
    *
    * @return datas
    */
   function prepareInputForAdd($input) {

      if (isset($input['due_date']) && empty($input['due_date'])) {
         $input['due_date'] = 'NULL';
      }

      return $input;
   }

   /**
    * @param datas $input
    *
    * @return datas
    */
   function prepareInputForUpdate($input) {

      if (isset($input['due_date']) && empty($input['due_date'])) {
         $input['due_date'] = 'NULL';
      }

      return $input;
   }


   /**
    * @param       $ID
    * @param array $options
    *
    * @return bool
    */
   function showForm($ID, $options = []) {

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name");
      echo "</td>";

      echo "<td>" . _n('Context', 'Contexts', 1, 'tasklists') . "</td><td>";
      Dropdown::show('PluginTasklistsTaskType', ['name'   => "plugin_tasklists_tasktypes_id",
                                                 'value'  => $this->fields["plugin_tasklists_tasktypes_id"],
                                                 'entity' => $this->fields["entities_id"]]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Priority') . "</td>";
      echo "<td>";
      CommonITILObject::dropdownPriority(['value'     => $this->fields['priority'],
                                          'withmajor' => 1]);
      echo "</td>";

      echo "<td>" . __('Planned duration') . "</td>";
      echo "<td>";
      $toadd = [];
      //for ($i=9 ; $i<=100 ; $i++) {
      //   $toadd[] = $i*HOUR_TIMESTAMP;
      //}

      Dropdown::showTimeStamp("actiontime", ['min'   => 0,
                                             'max'   => 50 * DAY_TIMESTAMP,
                                             'step'  => DAY_TIMESTAMP,
                                             'value' => $this->fields["actiontime"],
                                             //'addfirstminutes' => true,
                                             //'inhours'         => true,
                                             'toadd' => $toadd]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Visibility') . "</td>";
      echo "<td>";
      self::dropdownVisibility(['value' => $this->fields['visibility']]);
      echo "</td>";

      echo "<td>" . __('Due date', 'tasklists');
      echo "&nbsp;";
      Html::showToolTip(nl2br(__('Empty for infinite', 'tasklists')));
      echo "</td>";
      echo "<td>";
      Html::showDateField("due_date", ['value' => $this->fields["due_date"]]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('User') . "</td><td>";
      User::dropdown(['name'   => "users_id",
                      'value'  => $this->fields["users_id"],
                      'entity' => $this->fields["entities_id"],
                      'right'  => 'all']);
      echo "</td>";

      echo "<td>" . __('Percent done') . "</td>";
      echo "<td>";
      Dropdown::showNumber("percent_done", ['value' => $this->fields['percent_done'],
                                            'min'   => 0,
                                            'max'   => 100,
                                            'step'  => 20,
                                            'unit'  => '%']);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Group') . "</td>";
      echo "<td>";
      Dropdown::show('Group', ['name'      => "groups_id",
                               'value'     => $this->fields["groups_id"],
                               'entity'    => $this->fields["entities_id"],
                               'condition' => '`is_assign`']);
      echo "</td>";

      echo "<td>" . __('Status') . "</td><td>";
      Planning::dropdownState("state", $this->fields["state"]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>";
      echo __('Description') . "</td>";
      echo "<td colspan = '3' class='center'>";
      echo "<textarea cols='100' rows='15' name='comment' >" . $this->fields["comment"] . "</textarea>";
      echo "</td>";

      echo "</tr>";

      $this->showFormButtons($options);

      return true;
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
    * */
   static function dropdownTasklists($options = []) {

      global $DB, $CFG_GLPI;

      $p['name']    = 'plugin_tasklists_tasklists_id';
      $p['entity']  = '';
      $p['used']    = [];
      $p['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $rand = mt_rand();
      $dbu  = new DbUtils();
      $where = " WHERE `glpi_plugin_tasklists_tasklists`.`is_deleted` = '0' ";
      $where .= $dbu->getEntitiesRestrictRequest("AND", 'glpi_plugin_tasklists_tasklists', '', $p['entity'], true);

      if (count($p['used'])) {
         $where .= " AND `id` NOT IN (0, " . implode(",", $p['used']) . ")";
      }

      $query  = "SELECT *
        FROM `glpi_plugin_tasklists_tasktypes`
        WHERE `id` IN (SELECT DISTINCT `plugin_tasklists_tasktypes_id`
                       FROM `glpi_plugin_tasklists_tasks`
                       $where)
        ORDER BY `name`";
      $result = $DB->query($query);

      $values = [0 => Dropdown::EMPTY_VALUE];

      while ($data = $DB->fetch_assoc($result)) {
         $values[$data['id']] = $data['name'];
      }

      $out      = Dropdown::showFromArray('_tasktype', $values, ['width'   => '30%',
                                                                 'rand'    => $rand,
                                                                 'display' => false]);
      $field_id = Html::cleanId("dropdown__tasktype$rand");

      $params = ['tasktypes' => '__VALUE__',
                 'entity'    => $p['entity'],
                 'rand'      => $rand,
                 'myname'    => $p['name'],
                 'used'      => $p['used']
      ];

      $out .= Ajax::updateItemOnSelectEvent($field_id, "show_" . $p['name'] . $rand, $CFG_GLPI["root_doc"] . "/plugins/tasklists/ajax/dropdownTypeTasks.php", $params, false);

      $out .= "<span id='show_" . $p['name'] . "$rand'>";
      $out .= "</span>\n";

      $params['tasktype'] = 0;
      $out                .= Ajax::updateItem("show_" . $p['name'] . $rand, $CFG_GLPI["root_doc"] . "/plugins/tasklists/ajax/dropdownTypeTasks.php", $params, false);
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
    * @return an
    */
   function getSpecificMassiveActions($checkitem = null) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if ($isadmin) {

            if (Session::haveRight('transfer', READ) && Session::isMultiEntitiesMode()
            ) {
               $actions['PluginTasklistsTask' . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __('Transfer');
            }
         }
      }
      return $actions;
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
    *
    * @param MassiveAction $ma
    *
    * @return bool|false
    */
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case "transfer" :
            Dropdown::show('Entity');
            echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
            return true;
            break;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    *
    * @param MassiveAction $ma
    * @param CommonDBTM    $item
    * @param array         $ids
    *
    * @return nothing|void
    */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

      switch ($ma->getAction()) {
         case "transfer" :
            $input = $ma->getInput();
            if ($item->getType() == 'PluginTasklistsTask') {
               foreach ($ids as $key) {
                  $item->getFromDB($key);
                  $type = PluginTasklistsTaskType::transfer($item->fields["plugin_tasklists_tasktypes_id"], $input['entities_id']);
                  if ($type > 0) {
                     $values["id"]                            = $key;
                     $values["plugin_tasklists_tasktypes_id"] = $type;
                     $item->update($values);
                  }
                  unset($values);
                  $values["id"]          = $key;
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
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }

   /**
    * For other plugins, add a type to the linkable types
    *
    * @since version 1.3.0
    *
    * @param $type string class name
    * */
   static function registerType($type) {
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
   static function getTypes($all = false) {

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
    * @since version 0.83
    *
    * @param $field     String         name of the field
    * @param $values    String / Array with the value to display
    * @param $options   Array          of option
    *
    * @return a string
    **/
   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'priority':
            return CommonITILObject::getPriorityName($values[$field]);
         case 'visibility':
            return self::getVisibilityName($values[$field]);
         case 'state' :
            return Planning::getState($values[$field]);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   /**
    * @since version 0.84
    *
    * @param $field
    * @param $name (default '')
    * @param $values (default '')
    * @param $options   array
    *
    * @return string
    **/
   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'priority':
            $options['name']      = $name;
            $options['value']     = $values[$field];
            $options['withmajor'] = 1;
            return CommonITILObject::dropdownPriority($options);

         case 'visibility':
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownVisibility($options);

         case 'state':
            return Planning::dropdownState($name, $values[$field], false);
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
   static function dropdownVisibility(array $options = []) {

      $p['name']      = 'visibility';
      $p['value']     = 0;
      $p['showtype']  = 'normal';
      $p['display']   = true;
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
   static function getVisibilityName($value) {

      switch ($value) {

         case 1 :
            return _x('visibility', 'This user', 'tasklists');

         case 2 :
            return _x('visibility', 'This user and this group', 'tasklists');

         case 3 :
            return _x('visibility', 'All', 'tasklists');

         default :
            // Return $value if not define
            return $value;

      }
   }

   /**
    * @see Rule::getActions()
    * */
   function getActions() {

      $actions = [];

      $actions['tasklists']['name']          = __('Affect entity for create task', 'tasklists');
      $actions['tasklists']['type']          = 'dropdown';
      $actions['tasklists']['table']         = 'glpi_entities';
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
   function executeActions($action, $output, $params) {

      switch ($params['rule_itemtype']) {
         case 'RuleMailCollector':
            switch ($action->fields["field"]) {
               case "tasklists" :

                  if (isset($params['headers']['subject'])) {
                     $input['name'] = $params['headers']['subject'];
                  }
                  if (isset($params['ticket'])) {
                     $input['comment'] = addslashes(strip_tags($params['ticket']['content']));
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

}
