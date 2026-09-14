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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\HttpException;
use Glpi\Features\KanbanInterface;
use Glpi\Features\TeamworkInterface;
use GlpiPlugin\Tasklists\Task;
use GlpiPlugin\Tasklists\TaskState;
use GlpiPlugin\Tasklists\TaskType;
use GlpiPlugin\Tasklists\TypeVisibility;

use function Safe\json_encode;
use function Safe\preg_split;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_tasklists', READ);

if (!isset($_REQUEST['action'])) {
    throw new BadRequestHttpException("Missing action parameter");
}
$action = $_REQUEST['action'];

$nonkanban_actions = ['update', 'bulk_add_item', 'add_item', 'move_item', 'delete_item', 'load_item_panel',
    'add_teammember', 'delete_teammember', 'restore_item', 'load_teammember_form',
];
/** @var ?CommonDBTM $item */
$itemtype = null;
$item = null;
if (isset($_REQUEST['itemtype'])) {
    if (!in_array($_REQUEST['action'], $nonkanban_actions) && !is_a($_REQUEST['itemtype'], KanbanInterface::class, true)) {
        // Bad request
        // For all actions, except those in $nonkanban_actions, we expect to be manipulating the Kanban itself.
        throw new BadRequestHttpException("Invalid itemtype parameter");
    }
    // The test above lets any KanbanInterface implementation through, and for the actions
    // listed in $nonkanban_actions it does not even run, so getItemForItemtype() below was
    // instantiating a class named by the client. This endpoint serves one Kanban and one only,
    // the plugin's: the board is a TaskType and the cards are Tasks. Anything else is a request
    // driving a foreign class - core or plugin - through the plugin_tasklists right, which is
    // not the right that class is protected by. The whitelist is applied to every action, not
    // just to the Kanban ones, precisely because the exemption was the hole.
    if (!in_array($_REQUEST['itemtype'], [Task::class, TaskType::class], true)) {
        throw new BadRequestHttpException("Invalid itemtype parameter");
    }
    $itemtype = $_REQUEST['itemtype'];
    $item = getItemForItemtype($itemtype);
}

if (isset($_REQUEST['items_id'])) {
    $_REQUEST['items_id'] = (int) $_REQUEST['items_id'];
}

// Rights Checks
if ($item !== null) {
    if (in_array($action, ['refresh', 'get_switcher_dropdown', 'get_column', 'load_item_panel', 'get_kanbans', 'list_columns'])) {
        if (!$item->canView()) {
            // Missing rights
            throw new AccessDeniedHttpException();
        }
    }
    if (in_array($action, ['update', 'load_item_panel', 'delete_teammember'])) {
        if (!$item->can($_REQUEST['items_id'], UPDATE)) {
            // Missing rights
            throw new AccessDeniedHttpException();
        }
        // can(UPDATE) enforces the global right + entity access but NOT the
        // plugin's own visibility model: a same-entity user could otherwise read
        // or mutate another user's private (visibility=1) or group (visibility=2)
        // task. Replay the visibility guard here for every UPDATE-gated Task
        // action at once (load_item_panel was the single path that omitted it).
        if ($item instanceof Task && !$item->checkVisibility((int) $_REQUEST['items_id'])) {
            throw new AccessDeniedHttpException();
        }
    }
    if (in_array($action, ['load_teammember_form', 'add_teammember'])) {
        $item->getFromDB($_REQUEST['items_id']);
        $can_assign = method_exists($item, 'canAssign') ? $item->canAssign() : $item->can($_REQUEST['items_id'], UPDATE);
        if (!$can_assign) {
            // Missing rights
            throw new AccessDeniedHttpException();
        }
        // Same blind spot as the block above, and the last two UPDATE-gated Task actions that
        // did not close it: GlpiPlugin\Tasklists\Task declares no canAssign(), so the ternary
        // always falls back on can($id, UPDATE), which knows the global right and the entity but
        // nothing of the plugin's visibility model. add_teammember is a write - it rewrites the
        // users_id or the groups_id of the task - and both actions are listed in
        // $nonkanban_actions, so $checkKanbanContext never covers them either: a same-entity
        // user could take over a private (visibility 1) or group (visibility 2) task belonging
        // to somebody else, or read its assignment form.
        if ($item instanceof Task && !$item->checkVisibility((int) $_REQUEST['items_id'])) {
            throw new AccessDeniedHttpException();
        }
    }
    if (in_array($action, ['bulk_add_item', 'add_item'])) {
        if (!$item->canCreate()) {
            // Missing rights
            throw new AccessDeniedHttpException();
        }
    }
    if (in_array($action, ['delete_item'])) {
        $maybe_deleted = $item->maybeDeleted();
        // Mirror the GLPI core guard: a non-trashable itemtype requires PURGE, so the
        // pre-check must deny when the user CANNOT purge (!canPurge()). Dropping the
        // negation flipped the branch (denying purgers, allowing non-purgers); the
        // terminal check below still refused the operation, but the pre-check must
        // express the same intent as the final delete guard.
        if (($maybe_deleted && !$item::canDelete()) || (!$maybe_deleted && !$item::canPurge())) {
            // Missing rights
            throw new AccessDeniedHttpException();
        }
    }
    if ($action === 'restore_item') {
        $maybe_deleted = $item->maybeDeleted();
        if (($maybe_deleted && !$item::canDelete())) {
            // Missing rights
            throw new AccessDeniedHttpException();
        }
    }
}

// Helper to check required parameters
$checkParams = static function ($required) {
    foreach ($required as $param) {
        if (!isset($_REQUEST[$param])) {
            throw new BadRequestHttpException("Missing $param parameter");
        }
    }
};

/**
 * Helper to reject the arrays PHP lets through any parameter name.
 *
 * $checkParams above only answers "is it there": a value posted as card[]=1 passes it and reaches
 * the core helpers, which are written for scalars - array_splice() takes an int-typed offset, the
 * comparisons are made with ===, and the request died on an uncaught TypeError, a 500 where a 400
 * was meant.
 */
$checkScalarParams = static function (array $required): void {
    foreach ($required as $param) {
        if (!is_scalar($_REQUEST[$param] ?? null)) {
            throw new BadRequestHttpException("Invalid $param parameter");
        }
    }
};

/**
 * Helper to validate the board a column action addresses.
 *
 * The \Item_Kanban rows are keyed on (itemtype, items_id) alone and the core writes them with
 * no check of its own, so the column actions below - saving, loading, clearing, showing,
 * hiding, collapsing, expanding and reordering columns, and moving a card - reached the board
 * of any task type whose id the caller typed, in any entity, visible to them or not, with
 * nothing but the plugin READ right the page opens with. The two tests are the ones
 * Kanban::showKanban() applies before rendering the very same board and the ones
 * TaskType::getKanbanColumns() applies before returning its columns; the ajax endpoint is a
 * parallel path to both and has to replay them.
 */
$checkKanbanContext = static function ($itemtype, $items_id): void {
    if ($itemtype !== TaskType::class) {
        throw new BadRequestHttpException("Invalid itemtype parameter");
    }
    $context = new TaskType();
    if (!$context->getFromDB((int) $items_id)
        || !Session::haveAccessToEntity($context->fields['entities_id'], $context->fields['is_recursive'])
        || !TypeVisibility::isUserHaveRight((int) $items_id)) {
        throw new AccessDeniedHttpException();
    }
};

// Action Processing
if (($_POST['action'] ?? null) === 'update') {
    $checkParams(['column_field', 'column_value']);
    // can(UPDATE) is enforced above but ignores the plugin visibility model; a same-entity
    // user must not mutate another user's private task via the Kanban update path.
    if ($item instanceof Task && !$item->checkVisibility((int) $_POST['items_id'])) {
        throw new AccessDeniedHttpException();
    }
    // The key of the update array used to be $_POST['column_field'], so the client named the
    // column it wrote: any field of glpi_plugin_tasklists_tasks - users_id, entities_id,
    // plugin_tasklists_tasktypes_id, is_archived - could be set through the Kanban with
    // nothing but the UPDATE right on the task. The Kanban of this plugin has exactly one
    // column field, the one Kanban.php hands to the template, so it is settled here instead
    // of being taken from the request, and the parameter is only honoured as the assertion it
    // was meant to be.
    if (!$item instanceof Task || $_POST['column_field'] !== 'plugin_tasklists_taskstates_id') {
        throw new BadRequestHttpException("Invalid column_field parameter");
    }
    // The value is a state identifier, and not any state: the drop target must be one of the
    // columns the task type actually declares, which is the same rule Task::displayState()
    // builds its dropdown on. check(UPDATE) settles the row, never the posted value.
    $taskstates_id = (int) $_POST['column_value'];
    if (!in_array($taskstates_id, Task::getAllowedStates($item->fields['plugin_tasklists_tasktypes_id']), true)) {
        throw new AccessDeniedHttpException();
    }

    // Update project or task based on changes made in the Kanban
    $item->update([
        'id'                             => (int) $_POST['items_id'],
        'plugin_tasklists_taskstates_id' => $taskstates_id,
    ]);
} elseif (($_POST['action'] ?? null) === 'add_item') {
    $checkParams(['inputs']);

    $item = getItemForItemtype($itemtype);
    if (!$item) {
        throw new BadRequestHttpException();
    }

    // Mass assignment: $_POST['inputs'] is the serialised card form and went whole into add().
    // CommonDBTM writes every key of the input matching a column of the table, so the client
    // could also set is_template, is_deleted, is_archived, is_recursive, visibility,
    // users_id_requester or the date columns - creating from the Kanban a task template that
    // then appeared in the template list, a task born deleted or archived, or one made public
    // (visibility 3) regardless of what the form offered. The payload is reduced to the fields
    // the board actually posts; rights, entity and context are still settled by
    // can(-1, CREATE, ...) and by Task::validatePostedContext().
    $inputs = is_array($_POST['inputs'])
        ? array_intersect_key($_POST['inputs'], array_flip(Task::getKanbanCreationFields()))
        : [];

    if (!$item->can(-1, CREATE, $inputs)) {
        throw new AccessDeniedHttpException();
    }

    $result = $item->add($inputs);
    if (!$result) {
        throw new BadRequestHttpException();
    }
} elseif (($_POST['action'] ?? null) === 'bulk_add_item') {
    $checkParams(['inputs']);

    $item = getItemForItemtype($itemtype);
    if (!$item) {
        throw new BadRequestHttpException();
    }

    // Same whitelist as add_item above. bulk_item_list is not a column of the table and is
    // consumed here, so it is read before the payload is reduced - which also replaces the
    // unset() that used to do half the job.
    $inputs = is_array($_POST['inputs']) ? $_POST['inputs'] : [];
    $bulk_item_list = preg_split('/\r\n|[\r\n]/', (string) ($inputs['bulk_item_list'] ?? ''));
    $inputs = array_intersect_key($inputs, array_flip(Task::getKanbanCreationFields()));
    if ($bulk_item_list !== []) {
        foreach ($bulk_item_list as $item_entry) {
            $item_entry = trim($item_entry);
            if (!empty($item_entry)) {
                $item_input = $inputs + ['name' => $item_entry, 'content' => ''];
                if ($item->can(-1, CREATE, $item_input)) {
                    $item->add($item_input);
                }
            }
        }
    }
} elseif (($_POST['action'] ?? null) === 'move_item') {
    $checkParams(['card', 'column', 'position', 'kanban']);
    $checkKanbanContext($_POST['kanban']['itemtype'] ?? null, $_POST['kanban']['items_id'] ?? 0);
    $kanban = getItemForItemtype($_POST['kanban']['itemtype']);
    $can_move = false;
    if (method_exists($kanban, 'canOrderKanbanCard')) {
        $can_move = $kanban->canOrderKanbanCard($_POST['kanban']['items_id']);
    }
    // card, column and position reached \Item_Kanban::moveCard() untouched: position ends up as
    // the offset of array_splice(), which is typed int, so a value posted as position[]=1 raised
    // an uncaught TypeError - a 500 where a 400 was meant. Only position is cast: moveCard()
    // compares card and column with === against the values read back from the stored state,
    // which json_decode() returns as the strings the form encoding produced, so pinning a type
    // on them would stop every comparison from matching and silently break the drag and drop.
    $checkScalarParams(['card', 'column', 'position']);
    if ($can_move) {
        \Item_Kanban::moveCard(
            $_POST['kanban']['itemtype'],
            $_POST['kanban']['items_id'],
            $_POST['card'],
            $_POST['column'],
            (int) $_POST['position'],
        );
    }
} elseif (($_POST['action'] ?? null) === 'show_column') {
    $checkParams(['column', 'kanban']);
    $checkKanbanContext($_POST['kanban']['itemtype'] ?? null, $_POST['kanban']['items_id'] ?? 0);
    \Item_Kanban::showColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} elseif (($_POST['action'] ?? null) === 'hide_column') {
    $checkParams(['column', 'kanban']);
    $checkKanbanContext($_POST['kanban']['itemtype'] ?? null, $_POST['kanban']['items_id'] ?? 0);
    \Item_Kanban::hideColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} elseif (($_POST['action'] ?? null) === 'collapse_column') {
    $checkParams(['column', 'kanban']);
    $checkKanbanContext($_POST['kanban']['itemtype'] ?? null, $_POST['kanban']['items_id'] ?? 0);
    \Item_Kanban::collapseColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} elseif (($_POST['action'] ?? null) === 'expand_column') {
    $checkParams(['column', 'kanban']);
    $checkKanbanContext($_POST['kanban']['itemtype'] ?? null, $_POST['kanban']['items_id'] ?? 0);
    \Item_Kanban::expandColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column']);
} elseif (($_POST['action'] ?? null) === 'move_column') {
    $checkParams(['column', 'kanban', 'position']);
    $checkKanbanContext($_POST['kanban']['itemtype'] ?? null, $_POST['kanban']['items_id'] ?? 0);
    \Item_Kanban::moveColumn($_POST['kanban']['itemtype'], $_POST['kanban']['items_id'], $_POST['column'], $_POST['position']);
} elseif ($_REQUEST['action'] === 'refresh') {
    $checkParams(['column_field']);
    // The only board action whose access control lived in another class: refresh relied on the
    // generic check at the top of this file, and that one calls $item->canView(), which
    // TaskType::canView() deliberately answers with the global plugin_tasklists READ right - not
    // with the entity of the context nor with its group visibility. Nothing was exposed in
    // practice because TaskType::getKanbanColumns() re-checks on its own, but every other branch
    // of this dispatch states its own precondition rather than borrowing one, and a refresh is
    // exactly as much a read of the board as get_column is.
    $checkKanbanContext($_REQUEST['itemtype'] ?? null, $_REQUEST['items_id'] ?? 0);
    // Get all columns to refresh the kanban
    header("Content-Type: application/json; charset=UTF-8", true);
    $force_columns = \Item_Kanban::getAllShownColumns($itemtype, $_REQUEST['items_id']);
    $columns = $itemtype::getKanbanColumns($_REQUEST['items_id'], $_REQUEST['column_field'], $force_columns, true);
    echo json_encode($columns, JSON_FORCE_OBJECT);
} elseif ($_REQUEST['action'] === 'get_switcher_dropdown') {
    $values = $itemtype::getAllForKanban();
    Dropdown::showFromArray('kanban-board-switcher', $values, [
        'value' => $_REQUEST['items_id'] ?? '',
    ]);
} elseif ($_REQUEST['action'] === 'get_kanbans') {
    header("Content-Type: application/json; charset=UTF-8", true);
    echo json_encode($itemtype::getAllForKanban(true, (int) ($_REQUEST['items_id'] ?? -1)));
} elseif ($_REQUEST['action'] === 'get_url') {
    if (!($item instanceof KanbanInterface)) {
        throw new BadRequestHttpException();
    }

    $checkParams(['items_id']);
    if ($_REQUEST['items_id'] == -1) {
        echo $itemtype::getGlobalKanbanUrl(true);
        return;
    }
    $item->getFromDB($_REQUEST['items_id']);
    echo $item->getKanbanUrlWithID($_REQUEST['items_id'], true);
} elseif (($_POST['action'] ?? null) === 'create_column') {
    $checkParams(['column_field', 'items_id', 'column_name']);
    // column_field used to be taken from the request and handed to getItemForForeignKeyField(),
    // so any foreign key name of the instance designated a class the caller could instantiate
    // and then populate. Kanban::showKanban() only ever emits this one value - the columns of
    // the board are the task states - so it is the only one the endpoint has to accept, and
    // resolving the class here rather than from the input also removes the null return of
    // getItemForForeignKeyField() (a fatal on the static calls below) from the picture.
    if (($_POST['column_field'] ?? '') !== 'plugin_tasklists_taskstates_id') {
        throw new BadRequestHttpException("Unsupported column field");
    }
    $column_item = new TaskState();
    if (!$column_item::canCreate() || !$column_item::canView()) {
        // Missing rights
        throw new AccessDeniedHttpException();
    }
    // $_POST['params'] was merged into the input as-is, so the caller decided the value of any
    // column of the table - entities_id first of all, which took the new status out of the
    // entity of the board it was created from. Only the name is under his control.
    $column_item->add([
        'name'        => $_POST['column_name'],
        'entities_id' => $_SESSION['glpiactive_entity'],
    ]);
} elseif (($_POST['action'] ?? null) === 'save_column_state') {
    $checkParams(['itemtype', 'items_id']);
    $checkKanbanContext($_POST['itemtype'], $_POST['items_id']);
    if (!isset($_POST['state'])) {
        // Do nothing with the state unless it isn't saved yet. Could be that no columns are shown or an error occurred.
        // If the state is supposed to be cleared, it should come through as a clear_column_state request.
        if (\Item_Kanban::hasStateForItem($_POST['itemtype'], $_POST['items_id'])) {
            return;
        }
        \Item_Kanban::saveStateForItem($_POST['itemtype'], $_POST['items_id'], []);
        return;
    }
    $checkParams(['items_id', 'state']);
    // The posted state went straight into json_encode() and into glpi_items_kanbans: its shape
    // and its size were the client's, per user and per board, and the Kanban reads the blob
    // back on every load. The interface produces exactly four keys per column (see
    // getUpdatedColumnState() in js/src/vue/Kanban/Kanban.vue), so the payload is rebuilt from
    // them instead of being trusted - extra keys, nested structures or an object in place of a
    // card identifier were persisted verbatim. The scalars are kept as they arrive rather than
    // coerced: the flags travel as the strings "true"/"false" and the column identifiers as
    // strings, and Item_Kanban compares them with === against what was stored, so changing
    // their type here would break showColumn()/hideColumn()/moveCard().
    $posted_state = $_POST['state'];
    if (!is_array($posted_state)) {
        throw new BadRequestHttpException("Invalid state parameter");
    }
    $state = [];
    foreach ($posted_state as $column_index => $posted_column) {
        if (!is_array($posted_column) || !isset($posted_column['column']) || !is_scalar($posted_column['column'])) {
            continue;
        }
        $cards = [];
        foreach ((array) ($posted_column['cards'] ?? []) as $card_id) {
            if (is_scalar($card_id)) {
                $cards[] = (string) $card_id;
            }
        }
        $state[(int) $column_index] = [
            'column'  => (string) $posted_column['column'],
            'folded'  => is_scalar($posted_column['folded'] ?? null) ? (string) $posted_column['folded'] : 'false',
            'visible' => is_scalar($posted_column['visible'] ?? null) ? (string) $posted_column['visible'] : 'true',
            'cards'   => $cards,
        ];
    }
    \Item_Kanban::saveStateForItem($_POST['itemtype'], $_POST['items_id'], $state);
} elseif ($_REQUEST['action'] === 'load_column_state') {
    $checkParams(['itemtype', 'items_id', 'last_load']);
    $checkKanbanContext($_REQUEST['itemtype'], $_REQUEST['items_id']);
    header("Content-Type: application/json; charset=UTF-8", true);
    $response = [
        'state'     => \Item_Kanban::loadStateForItem($_REQUEST['itemtype'], $_REQUEST['items_id'], $_REQUEST['last_load']),
        'timestamp' => $_SESSION['glpi_currenttime'],
    ];
    echo json_encode($response, JSON_FORCE_OBJECT);
} elseif ($_REQUEST['action'] === 'clear_column_state') {
    $checkParams(['itemtype', 'items_id']);
    $checkKanbanContext($_REQUEST['itemtype'], $_REQUEST['items_id']);
    $result = \Item_Kanban::clearStateForItem($_REQUEST['itemtype'], $_REQUEST['items_id']);
    if (!$result) {
        throw new HttpException(500);
    }

    return;
} elseif ($_REQUEST['action'] === 'list_columns') {
    $checkParams(['column_field']);
    // These two were the only column actions not routed through the helper. list_columns does
    // not always name a board, so the check applies when it does; get_column always does.
    if (isset($_REQUEST['items_id'])) {
        $checkKanbanContext($_REQUEST['itemtype'] ?? null, $_REQUEST['items_id']);
    }
    header("Content-Type: application/json; charset=UTF-8", true);
    echo json_encode($itemtype::getAllKanbanColumns($_REQUEST['column_field']));
} elseif ($_REQUEST['action'] === 'get_column') {
    $checkParams(['column_id', 'column_field', 'items_id']);
    $checkKanbanContext($_REQUEST['itemtype'] ?? null, $_REQUEST['items_id']);
    // Closed after the checks: they read the session, and the response is built from it too.
    Session::writeClose();
    header("Content-Type: application/json; charset=UTF-8", true);
    $column = $itemtype::getKanbanColumns($_REQUEST['items_id'], $_REQUEST['column_field'], [$_REQUEST['column_id']]);
    echo json_encode($column, JSON_FORCE_OBJECT);
} elseif (($_POST['action'] ?? null) === 'delete_item') {
    $checkParams(['items_id']);
    $item->getFromDB($_POST['items_id']);
    if ($item instanceof Task && !$item->checkVisibility((int) $_POST['items_id'])) {
        throw new AccessDeniedHttpException();
    }
    // Check if the item can be trashed and if the request isn't forcing deletion (purge)
    $maybe_deleted = $item->maybeDeleted() && !($_REQUEST['force'] ?? false);
    if (($maybe_deleted && $item->can($_POST['items_id'], DELETE)) || (!$maybe_deleted && $item->can($_POST['items_id'], PURGE))) {
        $item->delete(['id' => $_POST['items_id']], !$maybe_deleted);
        // Check if the item was deleted or purged
        header("Content-Type: application/json; charset=UTF-8", true);
        echo json_encode([
            'purged' => $item->getFromDB($_POST['items_id']) === false,
        ]);
    } else {
        throw new AccessDeniedHttpException();
    }
} elseif (($_POST['action'] ?? null) === 'restore_item') {
    $checkParams(['items_id']);
    $item->getFromDB($_POST['items_id']);
    if ($item instanceof Task && !$item->checkVisibility((int) $_POST['items_id'])) {
        throw new AccessDeniedHttpException();
    }
    // Check if the item can be restored
    $maybe_deleted = $item->maybeDeleted();
    if (($maybe_deleted && $item->can($_POST['items_id'], DELETE))) {
        $item->restore(['id' => $_POST['items_id']]);
    } else {
        throw new AccessDeniedHttpException();
    }
} elseif (($_POST['action'] ?? null) === 'add_teammember') {
    if (!($item instanceof TeamworkInterface)) {
        throw new BadRequestHttpException();
    }
    $checkParams(['itemtype_teammember', 'items_id_teammember']);
    // role is neither required by $checkParams nor guaranteed to be scalar, and reading it raw
    // emitted an "Undefined array key" warning in the middle of an AJAX response. Cast like the
    // delete_teammember branch does; Task::addTeamMember() ignores the key and pins the role to
    // CommonITILActor::ASSIGN anyway.
    $item->addTeamMember($_POST['itemtype_teammember'], (int) $_POST['items_id_teammember'], [
        'role' => (int) ($_POST['role'] ?? 0),
    ]);
} elseif (($_POST['action'] ?? null) === 'delete_teammember') {
    if (!($item instanceof TeamworkInterface)) {
        throw new BadRequestHttpException();
    }
    $checkParams(['itemtype_teammember', 'items_id_teammember']);
    $item->deleteTeamMember($_POST['itemtype_teammember'], (int) $_POST['items_id_teammember'], [
        'role'   => (int) $_POST['role'],
    ]);
} elseif (($_REQUEST['action'] ?? null) === 'load_item_panel') {
    if (isset($itemtype, $item)) {
        TemplateRenderer::getInstance()->display('components/kanban/item_panels/default_panel.html.twig', [
            'itemtype' => $itemtype,
            'item_fields' => $item->fields,
            'team' => $item instanceof TeamworkInterface ? $item->getTeam() : [],
        ]);
    } else {
        throw new BadRequestHttpException();
    }
} elseif (($_REQUEST['action'] ?? null) === 'load_teammember_form') {
    if (isset($itemtype, $item) && $item instanceof TeamworkInterface) {
        echo $item::getTeamMemberForm($item);
    } else {
        throw new BadRequestHttpException();
    }
} else {
    throw new BadRequestHttpException();
}
