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

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Tasklists\TaskType;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();
Session::checkRight('plugin_tasklists', UPDATE);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
   // Get AJAX input and load it into $_REQUEST
   $input = file_get_contents('php://input');
   parse_str($input, $_REQUEST);
}

if (!isset($_REQUEST['action'])) {
    throw new BadRequestHttpException("Missing action parameter");
}
$action = $_REQUEST['action'];


if ($_REQUEST['action'] == 'addArchived') {

   header("Content-Type: application/json; charset=UTF-8", true);
   $states    = [];
   $states[0] = __("Not archived", 'tasklists');
   $states[1] = __("Archived", 'tasklists');

   if (!isset($_SESSION["archive"][Session::getLoginUserID()])) {
      $_SESSION["archive"][Session::getLoginUserID()] = json_encode([0]);
   }
   if ($_SESSION["archive"][Session::getLoginUserID()] != "" && $_SESSION["archive"][Session::getLoginUserID()] != "null") {
      $arch = Dropdown::showFromArray("archive", $states,
                                      ['id'       => 'archive',
                                       'multiple' => true,
                                       'values'   => json_decode($_SESSION["archive"][Session::getLoginUserID()], true),
                                       "display"  => false]);
   } else {
      $arch = Dropdown::showFromArray("archive", $states, ['id'       => 'archive',
                                                           'multiple' => true,
                                                           'value'    => 0,
                                                           "display"  => false]);

   }

   echo json_encode($arch, JSON_FORCE_OBJECT);

} else if ($_REQUEST['action'] == 'changeArchive') {
   if (!empty($_REQUEST['vals']))
      $_SESSION["archive"][Session::getLoginUserID()] = json_encode($_REQUEST['vals']);

}
if ($_REQUEST['action'] == 'addUsers') {

   header("Content-Type: application/json; charset=UTF-8", true);
   $users = TaskType::findUsers($_REQUEST['context']);

   if (!isset($_SESSION["usersKanban"][Session::getLoginUserID()])) {
      $_SESSION["usersKanban"][Session::getLoginUserID()] = json_encode([-1]);
   }
   if ($_SESSION["usersKanban"][Session::getLoginUserID()] != "" && isset($_SESSION["archive"]) && $_SESSION["archive"][Session::getLoginUserID()] != "null") {
      $arch = Dropdown::showFromArray("usersKanban", $users, ['id' => 'users',
                                                                   'multiple' => true,
                                                                   'values' => json_decode($_SESSION["usersKanban"][Session::getLoginUserID()], true),
                                                                   "display" => false]);
   } else {
      $arch = Dropdown::showFromArray("usersKanban", $users, ['id' => 'users',
                                                              'multiple' => true,
                                                              'value' => -1,
                                                              "display" => false]);
   }

   echo json_encode($arch, JSON_FORCE_OBJECT);

} else if ($_REQUEST['action'] == 'changeUsers') {
   if (!empty($_REQUEST['vals']))
      $_SESSION["usersKanban"][Session::getLoginUserID()] = json_encode($_REQUEST['vals']);

}
