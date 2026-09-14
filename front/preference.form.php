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

use GlpiPlugin\Tasklists\Preference;

//Save user preferences
if (isset($_POST['update'])) {
    // The preference primary key is the user id. Force it to the current user so a forged
    // POST id cannot target (and overwrite) another user's preferences: update() loads the
    // row via getFromDB($input['id']) before prepareInputForUpdate() runs, so the identity
    // must be pinned here, in the controller, and not only in the model.
    $_POST['id'] = Session::getLoginUserID();
    $pref = new Preference();
    // The two ends of this one form disagreed on which bit they required: Preference has
    // $rightname = plugin_tasklists, getTabNameForItem() shows the tab on READ and
    // displayTabContentForItem() renders the whole form including its save button, while this
    // controller demanded UPDATE. A consultative profile could fill the form in and lose its
    // input to an AccessDeniedHttpException, with nothing in the interface having said the
    // preferences were out of reach. READ is the bit that matches what these rows are: a default
    // context and a refresh delay belonging to the caller alone, and the identity is pinned
    // twice - here, above, before the row is even loaded, and again in
    // Preference::prepareInputForUpdate() - so read-only access to the plugin cannot be turned
    // into a write on anyone else.
    $pref->check(-1, READ, $_POST);
    $pref->update($_POST);
    Html::back();
}
