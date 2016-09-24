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

class PluginTasklistsTask extends CommonDBTM {

   public $dohistory = true;
   static $rightname = 'plugin_tasklists';
   protected $usenotepad = true;
   static $types = array();
      
   static function getTypeName($nb = 0) {

      return _n('Task', 'Tasks', $nb);
   }


   function getSearchOptions() {

      $tab = array();

      $tab['common'] = self::getTypeName(2);

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();

      $tab[2]['table']           = 'glpi_plugin_tasklists_tasktypes';
      $tab[2]['field']           = 'name';
      $tab[2]['name']            = _n('Context', 'Contexts', 1, 'tasklists');
      $tab[2]['datatype']        = 'dropdown';

      $tab[3]['table']           = 'glpi_users';
      $tab[3]['field']           = 'name';
      $tab[3]['linkfield']       = 'users_id';
      $tab[3]['name']            = __('User');
      $tab[3]['datatype']        = 'dropdown';
      
      $tab[4]['table']           = $this->getTable();
      $tab[4]['field']           = 'actiontime';
      $tab[4]['name']            = __('Planned duration');
      $tab[4]['datatype']        = 'timestamp';
      $tab[4]['massiveaction']   = false;
      
      $tab[5]['table']           = $this->getTable();
      $tab[5]['field']           = 'percent_done';
      $tab[5]['name']            = __('Percent done');
      $tab[5]['datatype']        = 'number';
      $tab[5]['unit']            = '%';
      $tab[5]['min']             = 0;
      $tab[5]['max']             = 100;
      $tab[5]['step']            = 5;

      $tab[6]['table']           = $this->getTable();
      $tab[6]['field']           = 'due_date';
      $tab[6]['name']            = __('Due date');
      $tab[6]['datatype']        = 'date';

      $tab[7]['table']           = $this->getTable();
      $tab[7]['field']           = 'comment';
      $tab[7]['name']            = __('Description');
      $tab[7]['datatype']        = 'text';
      
      $tab[8]['table']           = $this->getTable();
      $tab[8]['field']           = 'priority';
      $tab[8]['name']            = __('Priority');
      $tab[8]['searchtype']      = 'equals';
      $tab[8]['datatype']        = 'specific';
      
      $tab[9]['table']           = $this->getTable();
      $tab[9]['field']           = 'visibility';
      $tab[9]['name']            = __('Visibility');
      $tab[9]['searchtype']      = 'equals';
      $tab[9]['datatype']        = 'specific';
      $tab[9]['massiveaction']   = false;
      
      $tab[10]['table']          = 'glpi_groups';
      $tab[10]['field']          = 'name';
      $tab[10]['linkfield']      = 'groups_id';
      $tab[10]['name']           = __('Group');
      $tab[10]['condition']      = '`is_assign`';
      $tab[10]['datatype']       = 'dropdown';
      
      $tab[11]['table']         = $this->getTable();
      $tab[11]['field']         = 'state';
      $tab[11]['name']          = __('Status');
      $tab[11]['searchtype']      = 'equals';
      $tab[11]['datatype']      = 'specific';
      
      $tab[12]['table']          = $this->getTable();
      $tab[12]['field']          = 'date_mod';
      $tab[12]['massiveaction']  = false;
      $tab[12]['name']           = __('Last update');
      $tab[12]['datatype']       = 'datetime';

      $tab[18]['table']          = $this->getTable();
      $tab[18]['field']          = 'is_recursive';
      $tab[18]['name']           = __('Child entities');
      $tab[18]['datatype']       = 'bool';

      $tab[30]['table']          = $this->getTable();
      $tab[30]['field']          = 'id';
      $tab[30]['name']           = __('ID');
      $tab[30]['datatype']       = 'number';

      $tab[80]['table']          = 'glpi_entities';
      $tab[80]['field']          = 'completename';
      $tab[80]['name']           = __('Entity');
      $tab[80]['datatype']       = 'dropdown';
      
      return $tab;
   }

   function defineTabs($options = array()) {

      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('Notepad', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }
   
   function post_getEmpty() {

      $this->fields['priority']     = 3;
      $this->fields['percent_done'] = 0;
      $this->fields['users_id'] = Session::getLoginUserID();
      $this->fields['state'] = Planning::TODO;
   }
   
   function prepareInputForAdd($input) {

      if (isset($input['due_date']) && empty($input['due_date']))
         $input['due_date'] = 'NULL';

      return $input;
   }

   function prepareInputForUpdate($input) {

      if (isset($input['due_date']) && empty($input['due_date']))
         $input['due_date'] = 'NULL';

      return $input;
   }


   function showForm($ID, $options = array()) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);
      
      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name");
      echo "</td>";

      
      echo "<td>" . _n('Context', 'Contexts', 1, 'tasklists') . "</td><td>";
      Dropdown::show('PluginTasklistsTaskType', array('name' => "plugin_tasklists_tasktypes_id",
         'value' => $this->fields["plugin_tasklists_tasktypes_id"],
         'entity' => $this->fields["entities_id"]));
      echo "</td>";
      

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>".__('Priority')."</td>";
      echo "<td>";
      CommonITILObject::dropdownPriority(array('value' => $this->fields['priority'],
                                               'withmajor' => 1));
      echo "</td>";

      echo "<td>".__('Planned duration')."</td>";
      echo "<td>";
      $toadd = array();
      //for ($i=9 ; $i<=100 ; $i++) {
      //   $toadd[] = $i*HOUR_TIMESTAMP;
      //}

      Dropdown::showTimeStamp("actiontime", array('min'             => 0,
                                                  'max'             => 50*DAY_TIMESTAMP,
                                                  'step'            =>DAY_TIMESTAMP,
                                                  'value'           => $this->fields["actiontime"],
                                                  //'addfirstminutes' => true,
                                                  //'inhours'         => true,
                                                  'toadd'           => $toadd));
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      
      echo "<td>".__('Visibility')."</td>";
      echo "<td>";
      self::dropdownVisibility(array('value' => $this->fields['visibility']));
      echo "</td>";

      echo "<td>" . __('Due date');
      echo "&nbsp;";
      Html::showToolTip(nl2br(__('Empty for infinite', 'tasklists')));
      echo "</td>";
      echo "<td>";
      Html::showDateFormItem("due_date", $this->fields["due_date"], true, true);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('User') . "</td><td>";
      User::dropdown(array('name' => "users_id",
         'value' => $this->fields["users_id"],
         'entity' => $this->fields["entities_id"],
         'right' => 'all'));
      echo "</td>";
      
      echo "<td>".__('Percent done')."</td>";
      echo "<td>";
      Dropdown::showNumber("percent_done", array('value' => $this->fields['percent_done'],
                                                 'min'   => 0,
                                                 'max'   => 100,
                                                 'step'  => 20,
                                                 'unit'  => '%'));
      echo "</td>";
      
      

      echo "</tr>";
      
      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Group') . "</td>";
      echo "<td>";
      Dropdown::show('Group', array('name' => "groups_id",
         'value' => $this->fields["groups_id"],
         'entity' => $this->fields["entities_id"],
         'condition' => '`is_assign`'));
      echo "</td>";

      echo "<td>".__('Status')."</td><td>";
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
   static function dropdownTasklists($options = array()) {

      global $DB, $CFG_GLPI;

      $p['name'] = 'plugin_tasklists_tasklists_id';
      $p['entity'] = '';
      $p['used'] = array();
      $p['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $rand = mt_rand();

      $where = " WHERE `glpi_plugin_tasklists_tasklists`.`is_deleted` = '0' ";
      $where.=getEntitiesRestrictRequest("AND", 'glpi_plugin_tasklists_tasklists', '', $p['entity'], true);

      if (count($p['used'])) {
         $where .= " AND `id` NOT IN (0, " . implode(",", $p['used']) . ")";
      }

      $query = "SELECT *
        FROM `glpi_plugin_tasklists_tasktypes`
        WHERE `id` IN (SELECT DISTINCT `plugin_tasklists_tasktypes_id`
                       FROM `glpi_plugin_tasklists_tasks`
                       $where)
        ORDER BY `name`";
      $result = $DB->query($query);

      $values = array(0 => Dropdown::EMPTY_VALUE);

      while ($data = $DB->fetch_assoc($result)) {
         $values[$data['id']] = $data['name'];
      }

      $out = Dropdown::showFromArray('_tasktype', $values, array('width' => '30%',
            'rand' => $rand,
            'display' => false));
      $field_id = Html::cleanId("dropdown__tasktype$rand");

      $params = array('tasktypes' => '__VALUE__',
         'entity' => $p['entity'],
         'rand' => $rand,
         'myname' => $p['name'],
         'used' => $p['used']
      );

      $out .= Ajax::updateItemOnSelectEvent($field_id, "show_" . $p['name'] . $rand, $CFG_GLPI["root_doc"] . "/plugins/tasklists/ajax/dropdownTypeTasks.php", $params, false);

      $out .= "<span id='show_" . $p['name'] . "$rand'>";
      $out .= "</span>\n";

      $params['tasktype'] = 0;
      $out .= Ajax::updateItem("show_" . $p['name'] . $rand, $CFG_GLPI["root_doc"] . "/plugins/tasklists/ajax/dropdownTypeTasks.php", $params, false);
      if ($p['display']) {
         echo $out;
         return $rand;
      }
      return $out;
   }

   //Massive action
   function getSpecificMassiveActions($checkitem = NULL) {
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
    * */
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case "transfer" :
            Dropdown::show('Entity');
            echo Html::submit(_x('button', 'Post'), array('name' => 'massiveaction'));
            return true;
            break;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case "transfer" :
            $input = $ma->getInput();
            if ($item->getType() == 'PluginTasklistsTask') {
               foreach ($ids as $key) {
                  $item->getFromDB($key);
                  $type = PluginTasklistsTaskType::transfer($item->fields["plugin_tasklists_tasktypes_id"], $input['entities_id']);
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
   static function getSpecificValueToDisplay($field, $values, array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
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
    * @param $name            (default '')
    * @param $values          (default '')
    * @param $options   array
    *
    * @return string
   **/
   static function getSpecificValueToSelect($field, $name='', $values='', array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      $options['display'] = false;

      switch ($field) {
         case 'priority':
            $options['name']  = $name;
            $options['value'] = $values[$field];
            $options['withmajor']  = 1;
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
   static function dropdownVisibility(array $options=array()) {

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

      $values = array();

      $values[1] = static::getVisibilityName(1);
      $values[2] = static::getVisibilityName(2);
      $values[3] = static::getVisibilityName(3);

      return Dropdown::showFromArray($p['name'],$values, $p);

   }
   
   /**
    * Get ITIL object priority Name
    *
    * @param $value priority ID
   **/
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

      $actions = array();

      $actions['tasklists']['name']        = __('Affect entity for create task', 'tasklists');
      $actions['tasklists']['type']        = 'dropdown';
      $actions['tasklists']['table']       = 'glpi_entities';
      $actions['tasklists']['force_actions'] = array('send');
      
      return $actions;
   }

   /**
    * Execute the actions as defined in the rule
    *
    * @param $output the fields to manipulate
    * @param $params parameters
    *
    * @return the $output array modified
    * */
   function executeActions($action, $output, $params) {
      global $DB, $CFG_GLPI;

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
                        && isset($input['entities_id'])) {
                     $this->add($input);
                  }
                  $output['_refuse_email_no_response'] = true;
                  break;
            }
      }
      return $output;
   }

}

?>