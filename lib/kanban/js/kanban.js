/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

(function () {
    window.GLPIKanban = function () {
        /**
         * Self-reference for property access in functions.
         */
        var self = this;

        /**
         * Selector for the parent Kanban element. This is specified in PHP and passed in the GLPIKanban constructor.
         * @since 9.5.0
         * @type {string}
         */
        this.element = "";

        /**
         * The original column state when the Kanban was built or refreshed.
         * It should not be considered up to date beyond the initial build/refresh.
         * @since 9.5.0
         * @type {Array}
         */
        this.columns = {};


        this.max_priority = 5;

        /**
         * The AJAX directory.
         * @since 9.5.0
         * @type {string}
         */
        this.tasklists_root = CFG_GLPI.root_doc + "/plugins/tasklists/ajax/";
        this.root_doc = CFG_GLPI.root_doc;

        /**
         * The maximum number of badges able to be shown before an overflow badge is added.
         * @since 9.5.0
         * @type {number}
         */
        this.max_team_images = 3;

        /**
         * The size in pixels for the team badges.
         * @since 9.5.0
         * @type {number}
         */
        this.team_image_size = 24;

        /**
         * The parent item for this Kanban. In the future, this may be null for personal/unrelated Kanbans. For now, it is expected to be defined.
         * @since 9.5.0
         * @type {Object|{itemtype: string, items_id: number}}
         */
        this.item = null;

        /**
         * Object of itemtypes that can be used as items in the Kanban. They should be in the format:
         * itemtype => [
         *    'name' => Localized itemtype name
         *    'fields' => [
         *       field_name   => [
         *          'placeholder' => placeholder text (optional) = blank,
         *          'type' => input type (optional) default = text,
         *          'value' => value (optional) default = blank
         *       ]
         *    ]
         * ]
         * @since 9.5.0
         * @type {Object}
         */
        this.supported_itemtypes = {};

        /**
         * If true, then a button will be added to each column to allow new items to be added.
         * When an item is added, a request is made via AJAX to create the item in the DB.
         * Permissions are re-checked server-side during this request.
         * Users will still be limited by {@link limit_addcard_columns} both client-side and server-side.
         * @since 9.5.0
         * @type {boolean}
         */
        this.allow_add_item = false;

        /**
         * If true, then a button will be added to the add column form that lets the user create a new column.
         * For Projects as an example, it would create a new project state.
         * Permissions are re-checked server-side during this request.
         * @since 9.5.0
         * @type {boolean}
         */
        this.allow_create_column = false;

        /**
         * Global permission for being able to modify the Kanban state/view.
         * This includes the order of cards in the columns.
         * @since 9.5.0
         * @type {boolean}
         */
        this.allow_modify_view = false;

        /**
         * Limits the columns that the user can add cards to.
         * By default, it is empty which allows cards to be added to all columns.
         * If you don't want the user to add cards to any column, {@link allow_add_item} should be false.
         * @since 9.5.0
         * @type {Array}
         */
        this.limit_addcard_columns = [];

        /**
         * Global right for ordering cards.
         * @since 9.5.0
         * @type {boolean}
         */
        this.allow_order_card = false;

        /**
         * Specifies if the user's current palette is a dark theme (darker for example).
         * This will help determine the colors of the generated badges.
         * @since 9.5.0
         * @type {boolean}
         */
        this.dark_theme = false;

        /**
         * Name of the DB field used to specify columns and any extra fields needed to create the column (Ex: color).
         * For example, Projects organize items by the state of the sub-Projects and sub-Tasks.
         * Therefore, the column_field id is 'projectstates_id' with any additional fields needed being specified in extra_fields..
         * @since 9.5.0
         * @type {{id: string, extra_fields: Object}}
         */
        this.column_field = {id: '', extra_fields: {}};

        /**
         * Specifies if the Kanban's toolbar (switcher, filters, etc) should be shown.
         * This is true by default, but may be set to false if used on a fullscreen display for example.
         * @since 9.5.0
         * @type {boolean}
         */
        this.show_toolbar = true;

        /**
         * Filters being applied to the Kanban view.
         * For now, only a simple/regex text filter is supported.
         * This can be extended in the future to support more specific filters specified per itemtype.
         * The name of internal filters like the text filter begin with an underscore.
         * @since 9.5.0
         * @type {{_text: string}}
         */
        this.filters = {
            _text: ''
        };

        /**
         * The ID of the add column form.
         * @since 9.5.0
         * @type {string}
         */
        this.add_column_form = '';

        /**
         * The ID of the create column form.
         * @since 9.5.0
         * @type {string}
         */
        this.create_column_form = '';

        /**
         * Cache for images to reduce network requests and keep the same generated image between cards.
         * @since 9.5.0
         * @type {{Group: {}, User: {}, Supplier: {}, Contact: {}}}
         */
        this.team_badge_cache = {
            User: {},
            Group: {},
            Supplier: {},
            Contact: {}
        };

        /**
         * If greater than zero, this specifies the amount of time in minutes between background refreshes,
         * During a background refresh, items are added/moved/removed based on the data in the DB.
         * It does not affect items in the process of being created.
         * When sorting an item or column, the background refresh is paused to avoid a disruption or incorrect data.
         * @since 9.5.0
         * @type {number} Time in minutes between background refreshes.
         */
        this.background_refresh_interval = 0;

        /**
         * Internal refresh function
         * @since 9.5.0
         * @type {function}
         * @private
         */
        var _backgroundRefresh = null;

        /**
         * The user's state object.
         * This contains an up to date list of columns that should be shown, the order they are in, and if they are folded.
         * @since 9.5.0
         * @type {{
         *    is_dirty: {boolean},
         *    state: {(order_index:{column: {number}, folded:{boolean}, cards:{array}}
         * }}
         * The is_dirty flag indicates if the state was changed and needs saved.
         */
        this.user_state = {is_dirty: false, state: {}};

        /**
         * String localizations.
         * Each object key is the unlocalized string, and the value is the localized string (or un-localized if it could not be translated).
         * @type {{}}
         */
        this.translations = {};

        /**
         * The last time the Kanban was refreshed. This is used by the server to determine if the state needs sent to the client again.
         * The state will only be sent if there was a change since this time.
         * @type {?string}
         */
        this.last_refresh = null;

        /**
         * Global sorting active state.
         * @since 9.5.0
         * @type {boolean}
         */
        this.is_sorting_active = false;

        /**
         * Parse arguments and assign them to the object's properties
         * @since 9.5.0
         * @param {Object} args Object arguments
         */
        var initParams = function (args) {
            var overridableParams = [
                'element', 'max_team_images', 'team_image_size', 'item',
                'supported_itemtypes', 'allow_add_item', 'allow_add_column', 'dark_theme', 'background_refresh_interval',
                'column_field', 'allow_modify_view', 'limit_addcard_columns', 'allow_order_card', 'allow_create_column','background_refresh_interval'
            ];
            if (args.length === 1) {
                for (var i = 0; i < overridableParams.length; i++) {
                    var param = overridableParams[i];
                    if (args[0][param] !== undefined) {
                        self[param] = args[0][param];
                    }
                }
            }
            if (self.filters._text === undefined) {
                self.filters._text = '';
            }
            self.filter();
        };

        /**
         * Build DOM elements and defer registering event listeners for when the document is ready.
         * @since 9.5.0
         **/
        var build = function () {
            if (self.show_toolbar) {
                buildToolbar();
            }
            var kanban_container = $("<div class='kanban-container'><div class='kanban-columns'></div></div>").appendTo($(self.element));

            var add_itemtype_dropdown = "<ul id='kanban-add-dropdown' style='display: none'>";
            Object.keys(self.supported_itemtypes).forEach(function (itemtype) {
                add_itemtype_dropdown += "<li id='kanban-add-" + itemtype + "'>" + self.supported_itemtypes[itemtype]['name'] + '</li>';
            });
            add_itemtype_dropdown += '</ul>';
            kanban_container.append(add_itemtype_dropdown);


            self.refresh(null, null, null, true);

            if (self.allow_modify_view) {
                buildAddColumnForm();
                if (self.allow_create_column) {
                    buildCreateColumnForm();
                }
            }
        };

        var buildToolbar = function () {
            var toolbar = $("<div class='kanban-toolbar'></div>").appendTo(self.element);
            $("<div id='switcher'><select name='kanban-board-switcher'></select></div>").appendTo(toolbar);
            var filter_input = $("<input name='filter' type='text' placeholder='" + self.translations['Search or filter results'] + "'/>").appendTo(toolbar);
            var option = $("<div id='opt-kanban' class='kanban'></div>").appendTo(toolbar);
            var archive = $("<div id='opt-archive'><div>" + self.translations["status"] + "&nbsp;</div></div>").appendTo(option);
            var users = $("<div id='opt-users'><div>" + self.translations["users"] + "&nbsp;</div></div>").appendTo(option);

            $.ajax({
                method: 'GET',
                url: (self.tasklists_root + "addOptions.php"),

                data: {
                    action: "addArchived",
                },

            }).done(function (data) {

                $(data).appendTo(archive);


            }).fail(function (jqXHR, textStatus, errorThrown) {
                window.console.log(textStatus);
                window.console.log(errorThrown);
            });

            $.ajax({
                method: 'GET',
                url: (self.tasklists_root + "addOptions.php"),

                data: {
                    action: "addUsers",
                    context: self.item.items_id
                },

            }).done(function (data) {
                $(data).appendTo(users);


            }).fail(function (jqXHR, textStatus, errorThrown) {
                window.console.log(textStatus);
                window.console.log(errorThrown);
                window.console.log(jqXHR);
            });
            if (self.allow_modify_view) {
                var add_column = "<input type='button' class='kanban-add-column submit' value='" + self.translations['Add column'] + "'/><div id=\"adddialogContext\"></div>";


                toolbar.append(add_column);
            }
            filter_input.on('input', function () {
                var text = $(this).val();
                if (text === null) {
                    text = '';
                }
                self.filters._text = text;
                self.filter();
            });
        };


        var getColumnIDFromElement = function (column_el) {
            var element_id = [column_el];
            if (typeof column_el !== 'string') {
                element_id = $(column_el).prop('id').split('-');
            } else {
                element_id = column_el.split('-');
            }
            return element_id[element_id.length - 1];
        };


        var preserveScrolls = function () {
            self.temp_kanban_scroll = {
                left: $(self.element + ' .kanban-container').scrollLeft(),
                top: $(self.element + ' .kanban-container').scrollTop()
            };
            self.temp_column_scrolls = {};
            var columns = $(self.element + " .kanban-column");
            $.each(columns, function (i, column) {
                var column_body = $(column).find('.kanban-body');
                if (column_body.scrollTop() !== 0) {
                    self.temp_column_scrolls[column.id] = column_body.scrollTop();
                }
            });
        };

        var restoreScrolls = function () {
            if (self.temp_kanban_scroll !== null) {
                $(self.element + ' .kanban-container').scrollLeft(self.temp_kanban_scroll.left);
                $(self.element + ' .kanban-container').scrollTop(self.temp_kanban_scroll.top);
            }
            if (self.temp_column_scrolls !== null) {
                $.each(self.temp_column_scrolls, function (column_id, scroll) {
                    $('#' + column_id + ' .kanban-body').scrollTop(scroll);
                });
            }
            self.temp_kanban_scroll = {};
            self.temp_column_scrolls = {};
        };

        /**
         * Clear all columns from the Kanban.
         * Should be used in conjunction with {@link fillColumns()} to refresh the Kanban.
         * @since 9.5.0
         */
        var clearColumns = function () {
            preserveScrolls();
            // preserveNewItemForms();
            $(self.element + " .kanban-column").remove();
        };

        /**
         * Add all columns to the kanban. This does not clear the existing columns first.
         *    If you are refreshing the Kanban, you should call {@link clearColumns()} first.
         * @since 9.5.0
         * @param {Object} columns_container JQuery Object of columns container. Not required.
         *    If not specfied, a new object will be created to reference this Kanban's columns container.
         */
        var fillColumns = function (columns_container) {
            if (columns_container === undefined) {
                columns_container = $(self.element + " .kanban-container .kanban-columns").first();
            }

            var already_processed = [];


            $.each(self.columns, function (column_id, column) {
                if (!already_processed.includes(column_id)) {

                    appendColumn(column.id, column, columns_container);

                }
            });

            restoreScrolls();
        };

        /**
         * Add all event listeners. At this point, all elements should have been added to the DOM.
         * @since 9.5.0
         */
        var registerEventListeners = function () {
            var dropdown = $('#kanban-add-dropdown');

            refreshSortables();

            $(window).on('click', function (e) {
                if (!$(e.target).hasClass('kanban-add')) {
                    dropdown.css({
                        display: 'none'
                    });
                }
                if (self.allow_modify_view) {
                    if (!$.contains($(self.add_column_form)[0], e.target)) {
                        $(self.add_column_form).css({
                            display: 'none'
                        });
                    }
                    if (self.allow_create_column) {
                        if (!$.contains($(self.create_column_form)[0], e.target) && !$.contains($(self.add_column_form)[0], e.target)) {
                            $(self.create_column_form).css({
                                display: 'none'
                            });
                        }
                    }
                }
            });
            $(self.element + ' .kanban-container').on('click', '.kanban-delete', function (e) {
                archiveColumnTask(getColumnIDFromElement(e.target.closest('.kanban-column')));
            });
            $(self.element + ' .kanban-container').on('click', '.kanban-collapse-column', function (e) {
                self.toggleCollapseColumn(e.target.closest('.kanban-column'));
            });
            $(self.element).on('click', '.kanban-add-column', function () {
                refreshAddColumnForm();
            });
            $(self.add_column_form).on('input', "input[name='column-name-filter']", function () {
                var filter_input = $(this);
                $(self.add_column_form + ' li').hide();
                $(self.add_column_form + ' li').filter(function () {
                    return $(this).text().toLowerCase().includes(filter_input.val().toLowerCase());
                }).show();
            });
            $(self.add_column_form).on('change', "input[type='checkbox']", function () {
                var column_id = $(this).parent().data('list-id');
                if (column_id !== undefined) {
                    if ($(this).is(':checked')) {
                        addStateInContext(column_id);
                    } else {
                        removeStateInContext(column_id);
                    }
                }
            });

            $(self.create_column_form).on('click', '.kanban-create-column', function () {

                var toolbar = $(self.element + ' .kanban-toolbar');
                $(self.create_column_form).css({
                    display: 'none'
                });
                var name = $(self.create_column_form + " input[name='name']").val();
                $(self.create_column_form + " input[name='name']").val("");
                var color = $(self.create_column_form + " input[name='color']").spectrum('get').toHexString();
                createColumn(name, {color: color}, function () {
                    // Refresh add column list
                    refreshAddColumnForm();
                    $(self.add_column_form).css({
                        display: 'block',
                        position: 'fixed',
                        left: toolbar.offset().left + toolbar.outerWidth(true) - $(self.add_column_form).outerWidth(true),
                        top: toolbar.offset().top + toolbar.outerHeight(true)
                    });
                });
            });

            var switcher = $("select[name='archive[]']").first();
            $('#opt-archive').on('change', switcher, function (e) {

                var values = [];

                $.each($("select[name='archive[]'] option:selected"), function () {
                    values.push($(this).val());
                });
                $.ajax({
                    type: "GET",
                    url: (self.tasklists_root + "addOptions.php"),
                    data: {
                        action: "changeArchive",
                        vals: values
                    },
                    contentType: 'application/json',
                    success: function (data) {
                        self.refresh();
                    }
                });


            });
            var switcher = $("select[name='usersKanban[]']").first();
            $('#opt-users').on('change', switcher, function (e) {

                var values = [];

                $.each($("select[name='usersKanban[]'] option:selected"), function () {
                    values.push($(this).val());
                });
                $.ajax({
                    type: "GET",
                    url: (self.tasklists_root + "addOptions.php"),
                    data: {
                        action: "changeUsers",
                        vals: values
                    },
                    contentType: 'application/json',
                    success: function (data) {
                        self.refresh();
                    }
                });


            });


            var switcher = $("select[name='kanban-board-switcher']").first();
            $("#switcher").on('select2:select', switcher, function (e) {

                var items_id = e.params.data.id;
                $.ajax({
                    type: "GET",
                    url: (self.tasklists_root + "kanban.php"),
                    data: {
                        action: "get_url",
                        itemtype: self.item.itemtype,
                        items_id: items_id
                    },
                    contentType: 'application/json',
                    success: function (url) {
                        window.location = url;
                    }
                });
            });

            $(self.element).on('input', '.kanban-add-form input, .kanban-add-form textarea', function () {
                delayRefresh();
            });

            if (!self.allow_order_card) {
                $(self.element).on(
                    'mouseenter',
                    '.kanban-column',
                    function () {
                        if (self.is_sorting_active) {
                            return; // Do not change readonly states if user is sorting elements
                        }
                        // If user cannot order cards, make items temporarily readonly except for current column.
                        $(this).find('.kanban-body > li').removeClass('temporarily-readonly');
                        $(this).siblings().find('.kanban-body > li').addClass('temporarily-readonly');
                    }
                );
                $(self.element).on(
                    'mouseleave',
                    '.kanban-column',
                    function () {
                        if (self.is_sorting_active) {
                            return; // Do not change readonly states if user is sorting elements
                        }
                        $(self.element).find('.kanban-body > li').removeClass('temporarily-readonly');
                    }
                );
            }
        };

        /**
         * (Re-)Create the list of columns that can be shown/hidden.
         * This involves fetching the list of valid columns from the server.
         * @since 9.5.0
         */
        var refreshAddColumnForm = function () {
            var columns_used = [];
            $(self.element + ' .kanban-columns .kanban-column').each(function () {
                var column_id = this.id.split('-');
                columns_used.push(column_id[column_id.length - 1]);
            });
            var column_dialog = $(self.add_column_form);
            var toolbar = $(self.element + ' .kanban-toolbar');
            $.ajax({
                method: 'GET',
                url: (self.tasklists_root + "kanban.php"),
                data: {
                    action: "list_columns",
                    itemtype: self.item.itemtype,
                    column_field: self.column_field.id
                }
            }).done(function (data) {
                var form_content = $(self.add_column_form + " .kanban-item-content");
                form_content.empty();
                form_content.append("<input type='text' name='column-name-filter' placeholder='" + self.translations['Search'] + "'/>");
                var list = "<ul class='kanban-columns-list'>";
                $.each(data, function (column_id, column) {
                    var list_item = "<li data-list-id='" + column_id + "'>";
                    if (columns_used.includes(column_id)) {
                        list_item += "<input type='checkbox' checked='true'/>";
                    } else {
                        list_item += "<input type='checkbox'/>";
                    }
                    list_item += "<span class='kanban-color-preview' style='background-color: " + column['header_color'] + "'></span>";
                    list_item += column['name'] + "</li>";
                    list += list_item;
                });
                list += "</ul>";
                form_content.append(list);
                form_content.append();

                column_dialog.css({
                    display: 'block',
                    position: 'fixed',
                    left: toolbar.offset().left + toolbar.outerWidth(true) - column_dialog.outerWidth(true),
                    top: toolbar.offset().top + toolbar.outerHeight(true)
                });
            });
        };

        /**
         * (Re-)Initialize JQuery sortable for all items and columns.
         * This should be called every time a new column or item is added to the board.
         * @since 9.5.0
         */
        var refreshSortables = function () {
            // Make sure all items in the columns can be sorted
            var bodies = $(self.element + ' .kanban-body');
            $.each(bodies, function (b) {
                var body = $(b);
                if (body.data('sortable')) {
                    body.sortable('destroy');
                }
            });

            bodies.sortable({
                connectWith: '.kanban-body',
                containment: '.kanban-container',
                appendTo: '.kanban-container',
                items: '.kanban-item:not(.readonly):not(.temporarily-readonly)',
                placeholder: "sortable-placeholder",
                start: function (event, ui) {
                    self.is_sorting_active = true;
                    var card = ui.item;
                    // Track the column and position the card was picked up from
                    var current_column = card.closest('.kanban-column').attr('id');
                    card.data('source-col', current_column);
                    card.data('source-pos', card.index());
                },
                update: function (event, ui) {
                    if (this === ui.item.parent()[0]) {
                        return self.onKanbanCardSort(ui, this);
                    }
                },
                change: function (event, ui) {
                    var card = ui.item;
                    var source_column = card.data('source-col');
                    var source_position = card.data('source-pos');
                    var current_column = ui.placeholder.closest('.kanban-column').attr('id');

                    // Compute current position based on list of sortable elements without current card.
                    // Indeed, current card is still in DOM (but invisible), making placeholder index in DOM
                    // not always corresponding to its position inside list of visible ements.
                    var sortable_elements = $('#' + current_column + ' ul.ui-sortable > li:not([id="' + card.attr('id') + '"])');
                    var current_position = sortable_elements.index(ui.placeholder);
                    card.data('current-pos', current_position);

                    if (!self.allow_order_card) {
                        if (current_column === source_column) {
                            if (current_position !== source_position) {
                                ui.placeholder.addClass('invalid-position');
                            } else {
                                ui.placeholder.removeClass('invalid-position');
                            }
                        } else {
                            if (!$(ui.placeholder).is(':last-child')) {
                                ui.placeholder.addClass('invalid-position');
                            } else {
                                ui.placeholder.removeClass('invalid-position');
                            }
                        }
                    }
                },
                stop: function (event, ui) {
                    self.is_sorting_active = false;
                    ui.item.closest('.kanban-column').trigger('mouseenter'); // force readonly states refresh
                }
            });

            if (self.allow_modify_view) {
                // Enable column sorting
                $(self.element + ' .kanban-columns').sortable({
                    connectWith: self.element + ' .kanban-columns',
                    appendTo: '.kanban-container',
                    items: '.kanban-column:not(.kanban-protected)',
                    placeholder: "sortable-placeholder",
                    handle: '.kanban-column-header',
                    tolerance: 'pointer',
                    stop: function (event, ui) {
                        var column = $(ui.item[0]);
                        updateColumnPosition(getColumnIDFromElement(ui.item[0]), column.index());
                    }
                });
                $(self.element + ' .kanban-columns .kanban-column:not(.kanban-protected) .kanban-column-header').addClass('grab');
            }
        };

        /**
         * Construct and return the toolbar HTML for a specified column.
         * @since 9.5.0
         * @param {Object} column Column object that this toolbar will be made for.
         * @returns {string} HTML coded for the toolbar.
         */
        var getColumnToolbarElement = function (column) {
            var toolbar_el = "<span class='kanban-column-toolbar'>";

            var finishState = parseInt(column['finished']);
            if (self.allow_add_item) {
                if (finishState === 1) {
                    toolbar_el += "<i id='kanban_delete_" + column['id'] + "' class='kanban-delete pointer fas fa-archive' title='" + self.translations['archive_all_tasks'] + "'></i>";

                }
            }
            var column_id = parseInt(getColumnIDFromElement(column['id']));
            if (self.allow_add_item && (self.limit_addcard_columns.length === 0 || self.limit_addcard_columns.includes(column_id))) {
                toolbar_el += '<div id="adddialog"></div><a id="showadddialog' + column.id + '" href="#" title="' + self.translations['Add'] + '"><i class="kanban-add pointer fas fa-plus"></i></a>&nbsp;'
            }

            toolbar_el += "</span>";

            return toolbar_el;
        };


        /**
         * Callback function for when a kanban item is moved.
         * @since 9.5.0
         * @param {Object}  ui       ui value directly from JQuery sortable function.
         * @param {Element} sortable Sortable object
         * @returns {Boolean}       Returns false if the sort was cancelled.
         **/
        this.onKanbanCardSort = function (ui, sortable) {
            var target = sortable.parentElement;
            var source = $(ui.sender);
            var card = $(ui.item[0]);
            var el_params = card.attr('id').split('-');
            var target_params = $(target).attr('id').split('-');
            var column_id = target_params[target_params.length - 1];
            if (el_params.length === 1 && source !== null && !(!self.allow_order_card && source.length === 0)) {
                $.ajax({
                    type: "POST",
                    url: (self.tasklists_root + "kanban.php"),
                    data: {
                        action: "update",
                        itemtype: "PluginTasklistsTask",
                        items_id: el_params[1],
                        column_field: self.column_field.id,
                        column_value: column_id
                    },
                    contentType: 'application/json',
                    error: function () {
                        $(sortable).sortable('cancel');
                        return false;
                    },
                    success: function () {
                        var pos = card.data('current-pos');
                        if (!self.allow_order_card) {
                            card.appendTo($(target).find('.kanban-body').first());
                            pos = card.index();
                        }

                        card.removeData('source-col');
                        updateCardPosition(card.attr('id'), target.id, pos);
                        self.updateColumnCount(source);
                        self.updateColumnCount(target);
                        return true;
                    }
                });
            } else {
                $(sortable).sortable('cancel');
                return false;
            }
        };

        /**
         * Send the new card position to the server.
         * @since 9.5.0
         * @param {string} card The ID of the card being moved.
         * @param {string|number} column The ID or element of the column the card resides in.
         * @param {number} position The position in the column that the card is at.
         * @param {function} error Callback function called when the server reports an error.
         * @param {function} success Callback function called when the server processes the request successfully.
         */
        var updateCardPosition = function (card, column, position, error, success) {
            if (typeof column === 'string' && column.lastIndexOf('column', 0) === 0) {
                column = getColumnIDFromElement(column);
            }
            $.ajax({
                type: "POST",
                url: (self.tasklists_root + "kanban.php"),
                data: {
                    action: "move_item",
                    card: card,
                    column: column,
                    position: position,
                    kanban: self.item
                },
                contentType: 'application/json',
                error: function () {
                    if (error) {
                        error();
                    }
                },
                success: function () {
                    if (success) {
                        success();
                    }
                }
            }).done(function (data) {
                self.refresh();
            });
        };


        var addStateInContext = function (column) {
            $.ajax({
                type: "POST",
                url: (self.tasklists_root + "kanban.php"),
                data: {
                    action: "add_status_context",
                    column: column,
                    context_id: self.item.items_id
                },
                contentType: 'application/json',
                complete: function () {
                    self.refresh();
                    $(self.element + " .kanban-add-column-form li[data-list-id='" + column + "']").prop('checked', true);
                }
            });
        };

        var removeStateInContext = function (column) {
            $.ajax({
                type: "POST",
                url: (self.tasklists_root + "kanban.php"),
                data: {
                    action: "remove_status_context",
                    column: column,
                    context_id: self.item.items_id
                },
                contentType: 'application/json',
                complete: function () {
                    self.refresh();
                    $(self.element + " .kanban-add-column-form li[data-list-id='" + column + "']").prop('checked', false);
                }
            });
        };

        var archiveColumnTask = function (column) {
            var archivealltasks = 1;
            var state_id = column;
            var context_id = self.item.items_id;
            var data = {archivealltasks, state_id, context_id};
            if (confirm(self.translations['alert_archive_all_tasks'])) {
                $.ajax({
                    data: data,
                    type: 'POST',
                    url: '../ajax/updatetask.php',
                    success: function (data) {
                        self.refresh();
                    }
                });
            }
        };


        /**
         * Notify the server that the column's position has changed.
         * @since 9.5.0
         * @param {number} column The ID of the column.
         * @param {number} position The position of the column.
         */
        var updateColumnPosition = function (column, position) {
            $.ajax({
                type: "POST",
                url: (self.tasklists_root + "kanban.php"),
                data: {
                    action: "move_column",
                    column: column,
                    position: position,
                    kanban: self.item
                },
                contentType: 'application/json'
            });
        };


        /**
         * Check if the provided color is more light or dark.
         * This function converts the given hex value into HSL and checks the L value.
         * @since 9.5.0
         * @param hex Hex code of the color. It may or may not contain the beginning '#'.
         * @returns {boolean} True if the color is more light.
         */
        var isLightColor = function (hex) {
            var c = hex.substring(1);
            var rgb = parseInt(c, 16);
            var r = (rgb >> 16) & 0xff;
            var g = (rgb >> 8) & 0xff;
            var b = (rgb >> 0) & 0xff;
            var lightness = 0.2126 * r + 0.7152 * g + 0.0722 * b;
            return lightness > 110;
        };

        /**
         * Update the counter for the specified column.
         * @since 9.5.0
         * @param {string|Element|jQuery} column_el The column
         */
        this.updateColumnCount = function (column_el) {
            // window.console.log(column_el);
            if (!(column_el instanceof jQuery)) {
                column_el = $(column_el);
            }
            var column_body = $(column_el).find('.kanban-body:first');
            var counter = $(column_el).find('.kanban_nb:first');
            // Get all visible kanban items. This ensures the count is correct when items are filtered out.
            var items = column_body.find('li:not(.filtered-out)');
            counter.text(items.length);

        };

        /**
         * Remove all add item forms from the specified column.
         * @since 9.5.0
         * @param {string|Element|jQuery} column_el The column
         */
        this.clearAddItemForms = function (column_el) {
            if (!(column_el instanceof jQuery)) {
                column_el = $(column_el);
            }
            column_el.find('form').remove();
        };

        /**
         * Add a new form to the Kanban column to add a new item of the specified itemtype.
         * @since 9.5.0
         * @param {string|Element|jQuery} column_el The column
         * @param {string} itemtype The itemtype that is being added
         */
        this.showAddItemForm = function (column_el, itemtype) {
            if (!(column_el instanceof jQuery)) {
                column_el = $(column_el);
            }

            var uniqueID = Math.floor(Math.random() * 999999);
            var formID = "form_add_" + itemtype + "_" + uniqueID;
            var add_form = "<form id='" + formID + "' class='kanban-add-form kanban-form no-track'>";
            var form_header = "<div class='kanban-item-header'>";
            form_header += "<span class='kanban-item-title'>" + self.supported_itemtypes[itemtype]['name'] + "</span>";
            form_header += "<i class='fas fa-times' title='Close' onclick='$(this).parent().parent().remove()'/></div>";
            add_form += form_header;
            add_form += "</div>";
            add_form += "<div class='kanban-item-content'>";
            $.each(self.supported_itemtypes[itemtype]['fields'], function (name, options) {
                var input_type = options['type'] !== undefined ? options['type'] : 'text';
                var value = options['value'] !== undefined ? options['value'] : '';

                if (input_type.toLowerCase() === 'textarea') {
                    add_form += "<textarea name='" + name + "'";
                    if (options['placeholder'] !== undefined) {
                        add_form += " placeholder='" + options['placeholder'] + "'";
                    }
                    if (value !== undefined) {
                        add_form += " value='" + value + "'";
                    }
                    add_form += "/>";
                } else if (input_type.toLowerCase() === 'raw') {
                    add_form += value;
                } else {
                    add_form += "<input type='" + input_type + "' name='" + name + "'";
                    if (options['placeholder'] !== undefined) {
                        add_form += " placeholder='" + options['placeholder'] + "'";
                    }
                    if (value !== undefined) {
                        add_form += " value='" + value + "'";
                    }
                    add_form += "/>";
                }
            });
            add_form += "</div>";

            var column_id_elements = column_el.prop('id').split('-');
            var column_value = column_id_elements[column_id_elements.length - 1];
            add_form += "<input type='hidden' name='" + self.column_field.id + "' value='" + column_value + "'/>";
            add_form += "<input type='submit' value='" + self.translations['Add'] + "' name='add' class='submit'/>";
            add_form += "</form>";
            $(column_el.find('.kanban-body')[0]).append(add_form);
            $('#' + formID).get(0).scrollIntoView(false);
            $("#" + formID).on('submit', function (e) {
                e.preventDefault();
                var form = $(e.target);
                var data = {};
                data['inputs'] = form.serialize();
                data['itemtype'] = form.prop('id').split('_')[2];
                data['action'] = 'add_item';

                $.ajax({
                    method: 'POST',
                    //async: false,
                    url: (self.tasklists_root + "kanban.php"),
                    data: data
                }).done(function () {
                    self.refresh();
                });
            });
        };

        /**
         * Create the add column form and add it to the DOM.
         * @since 9.5.0
         */
        var buildAddColumnForm = function () {
            var uniqueID = Math.floor(Math.random() * 999999);
            var formID = "form_add_column_" + uniqueID;
            self.add_column_form = '#' + formID;
            var add_form = "<div id='" + formID + "' class='kanban-form kanban-add-column-form' style='display: none'>";
            add_form += "<form class='no-track'>";
            var form_header = "<div class='kanban-item-header'>";
            form_header += "<span class='kanban-item-title'>" + self.translations['Add a column from existing status'] + "</span></div>";
            add_form += form_header;
            add_form += "<div class='kanban-item-content'></div>";
            if (self.allow_create_column) {
                add_form += "<hr>" + self.translations['Or add a new status'];
                add_form += "<input id='showadddialogContext' type='button' class='submit kanban-create-column' value='" + self.translations['Create status'] + "'/>";
            }
            add_form += "</form></div>";
            add_form += '<script>'
                + '$("#adddialogContext").dialog({'
                + '   autoOpen: false,'
                + '  modal: true,'
                + '   resizable: true,'
                + '   draggable: true,'
                + '    height: 400,'
                + '    width: 800'
                + ' });'


                + ' $("#showadddialogContext").click(function () {'


                + '     var href ="' + self.root_doc + '/plugins/tasklists/ajax/contextForm.php?newContext=1";'
                + '    $("#adddialogContext").load(href).dialog("open");'
                + '   });'
                + '</script>';
            $(self.element).prepend(add_form);
        };

        /**
         * Create the create column form and add it to the DOM.
         * @since 9.5.0
         */
        var buildCreateColumnForm = function () {
            var uniqueID = Math.floor(Math.random() * 999999);
            var formID = "form_create_column_" + uniqueID;
            self.create_column_form = '#' + formID;
            var create_form = "<div id='" + formID + "' class='kanban-form kanban-create-column-form' style='display: none'>";
            create_form += "<form class='no-track'>";
            var form_header = "<div class='kanban-item-header'>";
            form_header += "<span class='kanban-item-title'>" + self.translations['Create status'] + "</span></div>";
            create_form += form_header;
            create_form += "<div class='kanban-item-content'>";
            create_form += "<input name='name'/>";
            $.each(self.column_field.extra_fields, function (name, field) {
                if (name === undefined) {
                    return true;
                }
                var value = (field.value !== undefined) ? field.value : '';
                if (field.type === undefined || field.type === 'text') {
                    create_form += "<input name='" + name + "' value='" + value + "'/>";
                } else if (field.type === 'color') {
                    if (value.length === 0) {
                        value = '#000000';
                    }
                    create_form += "<input type='color' name='" + name + "' value='" + value + "'/>";
                }
            });
            create_form += "</div>";
            create_form += "<input type='button' class='submit kanban-create-column' value='" + self.translations['Create status'] + "'/>";
            create_form += "</form></div>";
            $(self.element).prepend(create_form);
            //$(self.create_column_form + " input[type='color']").spectrum();
        };

        /**
         * Delay the background refresh for a short amount of time.
         * This should be called any time the user is in the middle of an action so that the refresh is not disruptive.
         * @since 9.5.0
         */
        var delayRefresh = function () {
            window.setTimeout(_backgroundRefresh, 10000);
        };

        /**
         * Refresh the Kanban with the new set of columns.
         *    This will clear all existing columns from the Kanban, and replace them with what is provided by the server.
         * @since 9.5.0
         * @param {function} success Callback for when the Kanban is successfully refreshed.
         * @param {function} fail Callback for when the Kanban fails to be refreshed.
         * @param {function} always Callback that is called regardless of the success of the refresh.
         * @param {boolean} initial_load True if this is the first load. On the first load, the user state is not saved.
         */
        this.refresh = function (success, fail, always) {
            var _refresh = function () {
                $.ajax({
                    method: 'GET',
                    //async: false,
                    url: (self.tasklists_root + "kanban.php"),
                    data: {
                        action: "refresh",
                        itemtype: self.item.itemtype,
                        items_id: self.item.items_id,
                        column_field: self.column_field.id
                    },
                    contentType: 'application/json',
                    dataType: 'json'
                }).done(function (columns, textStatus, jqXHR) {

                    clearColumns();
                    self.columns = columns;
                    fillColumns();
                    // Re-filter kanban
                    self.filter();

                }).fail(function (jqXHR, textStatus, errorThrown) {
                    if (fail) {
                        fail(jqXHR, textStatus, errorThrown);
                    }
                }).always(function () {

                    if (always) {
                        always();
                    }
                });
            };
            _refresh();


        };

        /**
         * Append a column to the Kanban
         * @param {number} column_id The ID of the column being added.
         * @param {array} column The column data array.
         * @param {string|Element|jQuery} columns_container The container that the columns are in.
         *    If left null, a new JQueryobject is created with the selector "self.element + ' .kanban-container .kanban-columns'".
         * @param {boolean} revalidate If true, all other columns are checked to see if they have an item in this new column.
         *    If they do, the item is removed from that other column and the counter is updated.
         *    This is useful if an item is changed in another tab or by another user to be in the new column after the original column was added.
         */
        var appendColumn = function (column_id, column, columns_container, revalidate) {
            if (columns_container == null) {
                columns_container = $(self.element + " .kanban-container .kanban-columns").first();
            }
            revalidate = revalidate !== undefined ? revalidate : false;

            column['id'] = "column-" + self.column_field.id + '-' + column_id;
            var collapse = '';
            var position = -1;

            if (column.folded == 1) {
                collapse = 'collapsed';
            }

            var _protected = column['_protected'] ? 'kanban-protected' : '';
            var column_classes = "kanban-column " + collapse + " " + _protected;

            var column_html = "<div id='" + column['id'] + "' style='border-top: 5px solid " + column['color'] + "' class='" + column_classes + "'></div>";
            var column_el = null;
            if (position < 0) {
                column_el = $(column_html).appendTo(columns_container);
            } else {
                var prev_column = $(columns_container).find('.kanban-column:nth-child(' + (position) + ')');
                if (prev_column.length === 1) {
                    column_el = $(column_html).insertAfter(prev_column);
                } else {
                    column_el = $(column_html).appendTo(columns_container);
                }
            }
            var cards = column['items'] !== undefined ? column['items'] : [];

            var header_color = column['color'];
            var is_header_light = header_color ? isLightColor(header_color) : !self.dark_theme;
            var header_text_class = is_header_light ? 'kanban-text-dark' : 'kanban-text-light';

            var column_header = $("<header class='kanban-column-header'></header>");
            var column_content = $("<div class='kanban-column-header-content'></div>").appendTo(column_header);
            var count = column['items'] !== undefined ? column['items'].length : 0;
            var column_left = $("<span class=''></span>").appendTo(column_content);
            var column_right = $("<span class=''></span>").appendTo(column_content);
            $(column_left).append("<i class='fas fa-caret-right fa-lg kanban-collapse-column pointer' title='" + self.translations['Toggle collapse'] + "'/>");

            $(column_left).append("<span class='kanban-column-title " + header_text_class + "' style='background-color: " + column['color'] + ";'>" + column['name'] + "</span></span>");
            $(column_right).append("<span class='kanban_nb'>" + count + "</span>");
            $(column_right).append(getColumnToolbarElement(column));
            if (self.allow_add_item) {
                var id = column.id.split('-')[2];
                $(column_right).append('<script>'
                    + '$("#adddialog").dialog({'
                    + '   autoOpen: false,'
                    + '  modal: true,'
                    + '   resizable: true,'
                    + '   draggable: true,'
                    + '    height: 700,'
                    + '    width: 800'
                    + ' });'


                    + ' $("#showadddialog' + column.id + '").click(function () {'

                    + '     var href ="' + self.root_doc + '/plugins/tasklists/ajax/seetask.php?plugin_tasklists_taskstates_id='
                    + id + '&plugin_tasklists_tasktypes_id=' + self.item.items_id + '";'
                    + '    $("#adddialog").load(href).dialog("open");'
                    + '   });'
                    + '</script>');


            }
            $(column_el).prepend(column_header);

            $("<ul class='kanban-body'></ul>").appendTo(column_el);

            var added = [];
            $.each(self.user_state.state, function (i, c) {
                if (c['column'] === column_id) {
                    $.each(c['cards'], function (i2, card) {
                        $.each(cards, function (i3, card2) {
                            if (card2['id'] === card) {
                                appendCard(column_el, card2);
                                added.push(card2['id']);
                                return false;
                            }
                        });
                    });
                }
            });
            var c = [];
            $.each(cards, function (card_id, card) {
                c.push(card);
                if (added.indexOf(card['id']) < 0) {
                    appendCard(column_el, card, revalidate);
                }
            });


            refreshSortables();
        };

        /**
         * Append the card in the specified column, handle duplicate cards in case the card moved, generate badges, and update column counts.
         * @since 9.5.0
         * @param {Element|string} column_el The column to add the card to.
         * @param {Object} card The card to append.
         * @param {boolean} revalidate Check for duplicate cards.
         */
        var appendCard = function (column_el, card, revalidate) {
            if (revalidate) {
                var existing = $('#' + card['id']);
                if (existing !== undefined) {
                    var existing_column = existing.closest('.kanban-column');
                    existing.remove();
                    self.updateColumnCount(existing_column);
                }
            }

            var col_body = $(column_el).find('.kanban-body').first();
            var readonly = card['_readonly'] !== undefined && (card['_readonly'] === true || card['_readonly'] === 1);
            var card_el = "<li id='" + card['id'] + "' class='kanban-item " + (readonly ? 'readonly' : '') + "'>";
            var link = "";
            card_el += "<div class='prioContent' > <div class='priority' style=\"background: " + card.bgcolor + "\"></div><div class='content_card'>"
            if (card.finished == 1 && card.archived == 0) {
                link += '<a id="archivetask' + card.id + '" href="#" title="' + self.translations['archive_task'] + '"><i class="fa fa-archive"></i></a>&nbsp;';
            }
            if (card.finished == 0 && card.priority_id < self.max_priority) {
                link += '<a id="updatepriority' + card.id + '" href="#" title="' + self.translations['update_priority'] + '"><i class="fa fa-arrow-up"></i></a>&nbsp;';
            }
            card_el += "<div class='kanban-item-header' >" + "<div id=\"dialog\"></div><a id=\"showdialog" + card.id + "\" href=\"#\">" + card['title'] + "</a>" + card.user + " " + link + "</div>";
            card_el += "<div class='kanban-item-client'>" + card['client'] + "</div>";

            card_el += "<div class='kanban-item-content tooltip'>" + (card['description'] || '') + "<span class='tooltiptext qtip-shadow qtip-bootstrap'>"+ (card['descriptionfull'] || '') +"</span></div>";
            card_el += "</div></div>";
            card_el += '<div align="right" class="endfooter">' + card.actiontime + '</div>';
            if (card.duedate) {
                card_el += '<hr><div style="background-color:'
                    + card.bgcolor + '" class="cd_kanban_board_block_item_priority">' + card.duedate + '</div><hr>';
            }
            if (card.right == 1) {
                card_el += '<div class=" cd_kanban_board_block_item_footer' + '">' +
                    '<br><div align="center"><div class="kanban_slider" id="slider' + card.id + '"></div></div>';
                card_el += '<div align="right" class="endfooter"><input type="text" id="percent' + card.id + '" readonly class="inputpercent" size="3"></div>';
            } else {
                card_el += '<div align="right" class="endfooter">' + card.percent + '%</div>';
            }

            card_el += '<div align="right" class="endfooter"><a id="clonedialog' + card.id + '" href="#" title="' + self.translations['clone_task'] + '"><i class="fa fa-clone"></i></a>';

            card_el += "</div>";
            card_el += "</li>";
            $(card_el).appendTo(col_body);


            self.updateColumnCount(column_el);
            $(document).ready(function () {
                $("a#archivetask" + card.id).click(function () {
                    var archivetask = 1;
                    var data_id = card.id;
                    var data = {archivetask, data_id};
                    if (confirm(self.translations['alert_archive_task'])) {
                        $.ajax({
                            data: data,
                            type: 'POST',
                            url: '../ajax/updatetask.php',
                            success: function (data) {
                                self.refresh();
                            }
                        });
                    }
                });
                $("a#updatepriority" + card.id).click(function () {
                    var updatepriority = 1;
                    var data_id = card.id;
                    var data = {updatepriority, data_id};
                    $.ajax({
                        data: data,
                        type: 'POST',
                        url: '../ajax/updatetask.php',
                        success: function (data) {
                            self.refresh();

                        }
                    });
                });
                $("a#clonedialog" + card.id).click(function () {
                    // Use this to get href
                    var href = self.tasklists_root + "seetask.php?clone_id=" +
                        card.id;
                    $("#adddialog").load(href).dialog("open");
                });
                $("#dialog").dialog({
                    autoOpen: false,
                    modal: true,
                    resizable: true,
                    draggable: true,
                    height: 800,
                    width: 1000
                });
                $("a#showdialog" + card.id).click(function () {
                    // Use this to get href
                    var href = self.tasklists_root + "seetask.php?id=" + card.id;
                    $("#dialog").load(href).dialog("open");
                });
                $("#slider" + card.id).slider({
                    min: 0,
                    max: 100,
                    step: 10,
                    range: "min",
                    animate: "slow",
                    value: card.percent,
                    slide: function (event, ui) {
                        $("#percent" + card.id).val(ui.value + "%");
                    },
                    change: function (event, ui) {
                        var percent_done = ui.value;
                        var data_id = card.id;
                        var data = {percent_done, data_id};
                        $.ajax({
                            data: data,
                            type: 'POST',
                            url: '../ajax/updatetask.php',
                            success: function (data) {
                                /*  location.reload();
                                  window.location.href = "kanban.php";

                                 */
                            }
                        });
                    }
                });
                $("#percent" + card.id).val($("#slider" + card.id).slider("value") + "%");
            });
        };

        /**
         * Un-hide all filtered items.
         * This does not reset the filters as it is called whenever the items are being re-filtered.
         * To clear the filter, set self.filters to {_text: '*'} and call self.filter().
         * @since 9.5.0
         */
        this.clearFiltered = function () {
            $(self.element + ' .kanban-item').each(function (i, item) {
                $(item).removeClass('filtered-out');
            });
        };

        /**
         * Applies the current filters.
         * @since 9.5.0
         */
        this.filter = function () {
            // Unhide all items in case they are no longer filtered
            self.clearFiltered();
            // Filter using built-in text filter (Check title)
            $(self.element + ' .kanban-item').each(function (i, item) {
                var title = $(item).find(".kanban-item-header a").text();
                var client = $(item).find(".kanban-item-client ").text();
                var content = $(item).find(".kanban-item-content ").text();
                try {
                    if (!title.match(new RegExp(self.filters._text, 'i')) && !client.match(new RegExp(self.filters._text, 'i')) && !content.match(new RegExp(self.filters._text, 'i'))) {
                        $(item).addClass('filtered-out');
                    }
                } catch (err) {
                    // Probably not a valid regular expression. Use simple contains matching.
                    if (!title.toLowerCase().includes(self.filters._text.toLowerCase()) && !client.toLowerCase().includes(self.filters._text.toLowerCase()) && !content.toLowerCase().includes(self.filters._text.toLowerCase())) {
                        $(item).addClass('filtered-out');
                    }
                }
            });
            // Check specialized filters (By column item property). Not currently supported.

            // Update column counters
            $(self.element + ' .kanban-column').each(function (i, column) {
                self.updateColumnCount(column);
            });
        };

        /**
         * Toggle the collapsed state of the specified column.
         * After toggling the collapse state, the server is notified of the change.
         * @since 9.5.0
         * @param {string|Element|JQuery} column_el The column element or object.
         */
        this.toggleCollapseColumn = function (column_el) {

            if (!(column_el instanceof jQuery)) {
                column_el = $(column_el);
            }
            column_el.toggleClass('collapsed');
            var action = column_el.hasClass('collapsed') ? 'collapse_column' : 'expand_column';
            $.ajax({
                type: "POST",
                url: (self.tasklists_root + "kanban.php"),
                data: {
                    action: action,
                    column: getColumnIDFromElement(column_el),
                    kanban: self.item
                },
                contentType: 'application/json'
            });
        };


        /**
         * Create a new column and send it to the server.
         * This will create a new item in the DB based on the item type used for columns.
         * It does not automatically add it to the Kanban.
         * @since 9.5.0
         * @param {string} name The name of the new column.
         * @param {Object} params Extra fields needed to create the column.
         * @param {function} callback Function to call after the column is created (or fails to be created).
         */
        var createColumn = function (name, params, callback) {
            if (name === undefined || name.length === 0) {
                if (callback) {
                    callback();
                }
                return;
            }
            $.ajax({
                method: 'POST',
                url: (self.tasklists_root + "kanban.php"),
                contentType: 'application/json',
                dataType: 'json',
                data: {
                    action: "create_column",
                    itemtype: self.item.itemtype,
                    items_id: self.item.items_id,
                    column_field: self.column_field.id,
                    column_name: name,
                    params: params
                }
            }).always(function () {
                if (callback) {
                    callback();
                }
            });
        };


        /**
         * Initialize the background refresh mechanism.
         * @sicne 9.5.0
         */
        var backgroundRefresh = function () {
            if (self.background_refresh_interval <= 0) {
                return;
            }
            _backgroundRefresh = function () {
                var sorting = $('.ui-sortable-helper');
                // Check if the user is current sorting items
                if (sorting.length > 0) {
                    // Wait 10 seconds and try the background refresh again
                    delayRefresh();
                    return;
                }
                // Refresh and then schedule the next refresh (minutes)
                self.refresh(null, null, function () {
                    window.setTimeout(_backgroundRefresh, self.background_refresh_interval * 60 * 1000);
                }, false);
            };
            // Schedule initial background refresh (minutes)
            window.setTimeout(_backgroundRefresh, self.background_refresh_interval * 60 * 1000);
        };

        /**
         * Initialize the Kanban by loading the user's column state, adding the needed elements to the DOM, and starting the background save and refresh.
         * @since 9.5.0
         */
        this.init = function () {
            $.ajax({
                type: 'GET',
                url: (self.tasklists_root + 'kanban.php'),
                data: {
                    action: 'get_translated_strings'
                },
                success: function (strings) {
                    self.translations = strings;

                }
            }).always(function () {
                build();
                $(document).ready(function () {

                    $.ajax({
                        type: 'GET',
                        url: (self.tasklists_root + 'kanban.php'),
                        data: {
                            action: 'get_switcher_dropdown',
                            itemtype: self.item.itemtype,
                            items_id: self.item.items_id
                        },
                        contentType: 'application/json',
                        success: function (data) {

                            var switcher = $(self.element + " .kanban-toolbar select[name='kanban-board-switcher']");
                            switcher.replaceWith(data);
                            var ele = switcher.first();


                        }
                    });
                    registerEventListeners();
                    backgroundRefresh();
                });

            });
        };
        initParams(arguments);
    };
})();
