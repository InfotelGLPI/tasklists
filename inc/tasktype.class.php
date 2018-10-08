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

// Class for a Dropdown

/**
 * Class PluginTasklistsTaskType
 */
class PluginTasklistsTaskType extends CommonTreeDropdown {

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {

      return _n('Context', 'Contexts', $nb, 'tasklists');
   }

   static $rightname = 'plugin_tasklists';

   /**
    * @see CommonGLPI::defineTabs()
    *
    * @param array $options
    *
    * @return array
    */
   function defineTabs($options = []) {

      $ong = parent::defineTabs($options);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('PluginTasklistsStateOrder', $ong, $options);
      $this->addStandardTab('PluginTasklistsTypeVisibility', $ong, $options);
      return $ong;
   }

   /**
    * @param $ID
    * @param $entity
    *
    * @return ID|int|the
    * @throws \GlpitestSQLError
    */
   static function transfer($ID, $entity) {
      global $DB;

      if ($ID > 0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_tasklists_tasktypes`
                   WHERE `id` = '$ID'";

         if ($result = $DB->query($query)) {
            if ($DB->numrows($result)) {
               $data                                   = $DB->fetch_assoc($result);
               $data                                   = Toolbox::addslashes_deep($data);
               $input['name']                          = $data['name'];
               $input['entities_id']                   = $entity;
               $input['is_recursive']                  = $data['is_recursive'];
               $input['plugin_tasklists_tasktypes_id'] = $data['plugin_tasklists_tasktypes_id'];
               $temp                                   = new self();
               $newID                                  = $temp->getID();

               if ($newID < 0) {
                  $newID = $temp->import($input);
               }

               return $newID;
            }
         }
      }
      return 0;
   }
}
