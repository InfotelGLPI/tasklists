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

use Glpi\Exception\Http\BadRequestHttpException;

use function Safe\json_encode;

header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_tasklists', READ);

if (!isset($_REQUEST['users_id'])) {
    throw new BadRequestHttpException("Missing users_id parameter");
} elseif (!is_array($_REQUEST['users_id'])) {
    $_REQUEST['users_id'] = [$_REQUEST['users_id']];
}

$_REQUEST['users_id'] = array_unique($_REQUEST['users_id']);

if (!isset($_REQUEST['size'])) {
    $_REQUEST['size'] = '100%';
}

if (!isset($_REQUEST['allow_blank'])) {
    $_REQUEST['allow_blank'] = false;
}

$user = new User();
$imgs = [];

// The plugin READ right alone must not turn this endpoint into a directory
// dump: restrict name/avatar resolution to users who actually share an active
// entity with the caller (via their profile assignments), so client-supplied
// ids cannot enumerate accounts across entities the session has no access to.
global $DB;
$requested_ids = array_map('intval', $_REQUEST['users_id']);
$allowed_ids   = [];
if (!empty($requested_ids)) {
    $iterator = $DB->request([
        'SELECT'   => 'users_id',
        'DISTINCT' => true,
        'FROM'     => 'glpi_profiles_users',
        'WHERE'    => [
            'users_id' => $requested_ids,
        ] + getEntitiesRestrictCriteria('glpi_profiles_users', 'entities_id', '', true),
    ]);
    foreach ($iterator as $row) {
        $allowed_ids[(int) $row['users_id']] = true;
    }
}

foreach ($_REQUEST['users_id'] as $user_id) {
    if (!isset($allowed_ids[(int) $user_id])) {
        // Not visible to this session: skip rather than leak name/picture.
        continue;
    }
    if ($user->getFromDB($user_id)) {
        if (!empty($user->fields['picture']) || $_REQUEST['allow_blank']) {
            if (isset($_REQUEST['type']) && $_REQUEST['type'] == 'thumbnail') {
                $path = User::getThumbnailURLForPicture($user->fields['picture']);
            } else {
                $path = User::getURLForPicture($user->fields['picture']);
            }
            $img = Html::image($path, [
                'title'          => getUserName($user_id),
                'data-bs-toggle' => 'tooltip',
                'width'          => $_REQUEST['size'],
                'height'         => $_REQUEST['size'],
                'class'          => $_REQUEST['class'] ?? '',
            ]);
            if (isset($_REQUEST['link']) && $_REQUEST['link']) {
                $imgs[$user_id] = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    htmlescape(User::getFormURLWithID($user_id)),
                    $img,
                );
            } else {
                $imgs[$user_id] = $img;
            }
        } else {
            // No picture and default image is not allowed.
            continue;
        }
    }
}

echo json_encode($imgs, JSON_FORCE_OBJECT);
