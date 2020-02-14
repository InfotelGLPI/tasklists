(function ($) {

    $.fn.kanban = function (options) {

        // defaults

        var $this = $(this);

        var settings = $.extend({
            titles: ['Block 1', 'Block 2', 'Block 3', 'Block 4'],
            colours: [],
            items: [],
            context: 0,
            rootdoc: "",
            lang: [],
            allright: 0,
            max_priority: 0,
            users_id: [],
            seemytasks: 0,
            seearchivedtasks: 0,
            seeprogresstasks: 0,
        }, options)

        var minclasses = {};

        if (settings.colours.length <= 4) {
            var minclasses = {
                kanban_board_minclass: "cd_kanban_minboard"
            };
        }
        var otherclasses = {
            kanban_board_class: "cd_kanban_board",
            kanban_board_titles_class: "cd_kanban_board_titles",
            kanban_board_title_class: "cd_kanban_board_title",
            kanban_board_blocks_class: "cd_kanban_board_blocks",
            kanban_board_block_class: "cd_kanban_board_block",
            kanban_board_item_class: "cd_kanban_board_block_item",
            kanban_board_item_placeholder_class: "cd_kanban_board_block_item_placeholder",
            kanban_board_item_title_class: "cd_kanban_board_block_item_title",
            kanban_board_item_priority_class: "cd_kanban_board_block_item_priority",
            kanban_board_item_footer_class: "cd_kanban_board_block_item_footer"
        };

        var classes = Object.assign(minclasses, otherclasses);

        function build_kanban() {

            $this.addClass(classes.kanban_board_class);
            $this.addClass(classes.kanban_board_minclass);

            var action = '';
            var kid = settings.id;

            action += '<div class="filter_right">';
            action += '<i class="fa fa-search-plus" onclick="zoomin('+ kid +')"></i>/<i class="fa fa-search-minus" onclick="zoomout('+ kid +')"></i>&nbsp;';
            //if (settings.allright == 1) {
            if (settings.seemytasks != 0) {
                action += '<div class="filter"><a id="seealltasks" href="#">';
                action += '<span>' + settings.lang['see_all_tasks'] + '</span>';
                action += '</a></div>';
            } else {
                Object.keys(settings.users_id).forEach(function (k) {
                    if(settings.users_id[k] != ""){
                        action += '<div class="filter"><a id="seemytasks' + k + '" href="#">';
                        action += '<span>' + settings.lang['see_my_tasks'] + ' ' + settings.users_id[k] + '</span>';
                        action += '</a></div>';
                    }
                });
            }
            //}

            action += '<div class="filter"><a id="seeprogresstasks" href="#">';
            if (settings.seeprogresstasks == 1) {
                action += '<span>' + settings.lang['see_all_tasks'] + '</span>';
            } else {
                action += '<span>' + settings.lang['see_progress_tasks'] + '</span>';
            }
            action += '</a></div>';

            action += '<div class="filter"><a id="seearchivedtasks" href="#">';
            if (settings.seearchivedtasks == 1) {
                action += '<span>' + settings.lang['hide_archived_tasks'] + '</span>';
            } else {
                action += '<span>' + settings.lang['see_archived_tasks'] + '</span>';
            }
            action += '</a></div>';

            // $this.append('<div class="filter"><a id="seearchivedtasks" href="#"><span>'
            //     + settings.lang['see_archived_tasks'] + ' / ' + settings.lang['hide_archived_tasks'] + '</span></a></div>');
            action += '</div>';

            $this.append(action);

            $this.append('<div class="' + classes.kanban_board_titles_class + '"></div>');
            $this.append('<div class="' + classes.kanban_board_blocks_class + '"></div>');

            build_titles();
            build_blocks();

            $(function () {
                Object.keys(settings.users_id).forEach(function (k) {
                    $("a#seemytasks" + k).click(function () {
                        var seemytasks = settings.seemytasks;
                        var data = {seemytasks, k};
                        $.ajax({
                            data: data,
                            type: 'POST',
                            url: '../ajax/seemytasks.php',
                            success: function (data) {
                                build_items(settings.seemytasks, settings.seearchivedtasks, settings.seeprogresstasks, true);
                            }
                        });
                    });
                });
                $("a#seealltasks").click(function () {
                    var seemytasks = settings.seemytasks;
                    var k = 0;
                    var data = {seemytasks, k};
                    $.ajax({
                        data: data,
                        type: 'POST',
                        url: '../ajax/seemytasks.php',
                        success: function (data) {
                            build_items(settings.seemytasks, settings.seearchivedtasks, settings.seeprogresstasks, true);
                        }
                    });
                });
                $("a#seearchivedtasks").click(function () {
                    $.ajax({
                        data: settings.seearchivedtasks,
                        type: 'POST',
                        url: '../ajax/seearchivedtasks.php',
                        success: function (data) {
                            build_items(settings.seemytasks, settings.seearchivedtasks, settings.seeprogresstasks, true);
                        }
                    });
                });
                $("a#seeprogresstasks").click(function () {
                    $.ajax({
                        data: settings.seeprogresstasks,
                        type: 'POST',
                        url: '../ajax/seeprogresstasks.php',
                        success: function (data) {
                            build_items(settings.seemytasks, settings.seearchivedtasks, settings.seeprogresstasks, true);
                        }
                    });
                });
            });

            build_items(settings.seemytasks, settings.seearchivedtasks, settings.seeprogresstasks, false);
        }

        function build_titles() {

            settings.titles.forEach(function (item, index, array) {

                var action = '';
                if (item.finished == 1 && settings.allright == 1) {
                    // action = '<li><a id="archivealltasks' + item.id + '" href="#">' + settings.lang['archive_all_tasks'] + '</a></li>';
                    action = '<a id="archivealltasks' + item.id + '" href="#" title="' + settings.lang['archive_all_tasks'] + '"><i class="fa fa-archive fa-white"></i></a>&nbsp;';
                    // action += '<a id="seearchivedtasks' + item.id + '" href="#" title="' + settings.lang['see_archived_tasks'] + '"><i class="fa fa-eye fa-white"></i></a>&nbsp;';
                }
                var title = '<div id="adddialog"></div><div id="title' + item.id + '" style="background: ' + settings.colours[item.id] + '" class="'
                    + classes.kanban_board_title_class + '"><div class="menu">'
                    // + '<a href="#" id="nav' + settings.context + item.id + '">'
                    // + '<i class="fa fa-bars fa-white"></i></a>&nbsp;'
                    + '<a id="showadddialog' + item.id + '" href="#" title="' + settings.lang['add_tasks'] + '"><i class="fa fa-plus-circle fa-white"></i></a>&nbsp;'
                    + action
                    + item.title + '&nbsp;(' + item.count + ')'
                    // + '<ul class="categories clearfix" id="category' + settings.context + item.id + '" style="display:none">'
                    // + '<li><a id="showadddialog' + item.id + '" href="#">' + settings.lang['add_tasks'] + '</a></li>'
                    // + action
                    // + '</ul>'
                    + '</div>'
                    + '</div>';
                $this.find('.' + classes.kanban_board_titles_class).append(title);
                $(function () {
                    $("#adddialog").dialog({
                        autoOpen: false,
                        modal: true,
                        resizable: true,
                        draggable: true,
                        height: 700,
                        width: 800
                    });

                    $("a#showadddialog" + item.id).click(function () {
                        // Use this to get href
                        var href = settings.rootdoc + "/plugins/tasklists/ajax/seetask.php?plugin_tasklists_taskstates_id=" +
                            item.id + "&plugin_tasklists_tasktypes_id=" + settings.context;
                        $("#adddialog").load(href).dialog("open");
                    });

                    $("a#nav" + settings.context + item.id).on("click", function (e) {
                        e.preventDefault();
                        $("#category" + settings.context + item.id).slideToggle();
                    });

                    $("a#archivealltasks" + item.id).click(function (event) {
                        var archivealltasks = 1;
                        var state_id = item.id;
                        var context_id = settings.context;
                        var data = {archivealltasks, state_id, context_id};
                        if (confirm(settings.lang['alert_archive_all_tasks'])) {
                            $.ajax({
                                data: data,
                                type: 'POST',
                                url: '../ajax/updatetask.php',
                                success: function (data) {
                                    location.reload();
                                    window.location.href = "kanban.php";
                                }
                            });
                        }
                    });
                });
            });

        }

        function build_blocks() {
            settings.titles.forEach(function (item, index, array) {
                var item = '<div class="' + classes.kanban_board_block_class + '" data-block="' + item.id + '"></div>';
                $this.find('.' + classes.kanban_board_blocks_class).append(item);
            });
            $("." + classes.kanban_board_block_class).sortable({
                connectWith: "." + classes.kanban_board_block_class,
                // containment: "." + classes.kanban_board_blocks_class,
                placeholder: classes.kanban_board_item_placeholder_class,
                scroll: true,
                cursor: "move",
                receive: function (event, ui) {
                    var data_destblock = ui.item[0].parentElement.dataset.block;
                    var data_id = ui.item[0].dataset.id;
                    var data = {data_destblock, data_id};
                    $.ajax({
                        data: data,
                        type: 'POST',
                        url: '../ajax/movetask.php',
                        success: function (data) {
                            location.reload();
                            window.location.href = "kanban.php";
                        }
                    });
                }
            }).disableSelection();

        }

        function build_items(seemytasks, seearchivedtasks, seeprogresstasks, reload) {

            settings.items.forEach(function (item, index, array) {

                if (item.finished == 0) {
                    faimg = 'fa-caret-up';
                } else {
                    faimg = 'fa-caret-down';
                }

                var block = $this.find('.' + classes.kanban_board_block_class + '[data-block="' + item.block + '"]');

                if (reload == 1) {
                    var append = '';
                } else {
                    if ((seemytasks != 0 && item.users_id == settings.seemytasks) || seemytasks == 0) {

                        if ((seearchivedtasks == 0 && item.archived == 0) || seearchivedtasks == 1) {

                            if ((seeprogresstasks == 1 && item.percent != 100) || seeprogresstasks == 0) {
                                var append = '<div id="div' + item.id + '" class="' + classes.kanban_board_item_class + '" data-id="' + item.id +
                                    '" style="background: ' + item.bgcolor + '">';
                                append += '<div class="title"><div class="' + classes.kanban_board_item_title_class + '">';
                                if (item.finished == 1 && item.archived == 0 && item.right == 1) {
                                    append += '<a id="archivetask' + item.id + '" href="#" title="' + settings.lang['archive_task'] + '"><i class="fa fa-archive"></i></a>&nbsp;';
                                }
                                if (item.finished == 0 && item.right == 1 && item.priority_id < settings.max_priority) {
                                    append += '<a id="updatepriority' + item.id + '" href="#" title="' + settings.lang['update_priority'] + '"><i class="fa fa-arrow-up"></i></a>&nbsp;';
                                }
                                if (item.right == 1) {
                                    append += '<div id="dialog"></div><a id="showdialog' + item.id + '" href="#">';
                                }
                                append += item.title;
                                if (item.right == 1) {
                                    append += '</a>';
                                }
                                append += '</div>' + item.user + '</div>';
                                if (item.client) {
                                    append += '<div class="client">' + item.client + '</div>';
                                }
                                append += '<div align="right" class="endfooter">' + item.actiontime + '</div>';
                                //Details
                                append += '<div id="hideopt' + item.id + '" class="hideopt"><i id="hide' + item.id + '" class="fa faopt' + item.id + ' ' + faimg + '" title="' + settings.lang['see_details']
                                    + '"></i></div>'
                                    + '<div class="panelopt' + item.id + '" ' + item.finished_style + '>';

                                if (item.description) {
                                    append += '<div class="kanbancomment">' + item.description.replace(/<[^>]*>/g,"") + '</div>';
                                }

                                if (item.duedate) {
                                    append += '<div style="background-color:'
                                        + item.bgcolor + '" class="' + classes.kanban_board_item_priority_class + '">' + item.duedate + '</div>';
                                }

                                if (item.right == 1) {
                                    append += '<div class="' + classes.kanban_board_item_footer_class + '">' +
                                        '<br><div align="center"><div class="kanban_slider" id="slider' + item.id + '"></div></div>';
                                    append += '<div align="right" class="endfooter"><input type="text" id="percent' + item.id + '" readonly class="inputpercent" size="3"></div>';
                                } else {
                                    append += '<div align="right" class="endfooter">' + item.percent + '%</div>';
                                }
                                append += '<div align="right" class="endfooter"><a id="clonedialog' + item.id + '" href="#" title="' + settings.lang['clone_task'] + '"><i class="fa fa-clone"></i></a>';
                                // append += '&nbsp;<a id="ticketdialog' + item.id + '" href="#" title="' + settings.lang['create_ticket'] + '"><i class="fa fa-plus-circle"></i></a>';
                                append += '</div></div></div>';
                            }
                        }
                    }
                }
                block.append(append);

                $(document).ready(function () {

                    if ($('.panelopt' + item.id).size() == 0) {
                        $('#hideopt' + item.id).hide();
                    }

                    $('#hideopt' + item.id).click(function () {
                        $('.panelopt' + item.id).toggle();
                        if (item.finished == 0) {
                            $('.faopt' + item.id).toggleClass("fa-caret-up fa-caret-down");
                        } else {
                            $('.faopt' + item.id).toggleClass("fa-caret-down fa-caret-up ");
                        }
                    });

                    $("#dialog").dialog({
                        autoOpen: false,
                        modal: true,
                        resizable: true,
                        draggable: true,
                        height: 800,
                        width: 1000
                    });

                    $("a#showdialog" + item.id).click(function () {
                        // Use this to get href
                        var href = settings.rootdoc + "/plugins/tasklists/ajax/seetask.php?id=" + item.id;
                        $("#dialog").load(href).dialog("open");
                    });

                    $("a#updatepriority" + item.id).click(function () {
                        var updatepriority = 1;
                        var data_id = item.id;
                        var data = {updatepriority, data_id};
                        $.ajax({
                            data: data,
                            type: 'POST',
                            url: '../ajax/updatetask.php',
                            success: function (data) {
                                location.reload();
                                window.location.href = "kanban.php";
                            }
                        });
                    });

                    $("a#clonedialog" + item.id).click(function () {
                        // Use this to get href
                        var href = settings.rootdoc + "/plugins/tasklists/ajax/seetask.php?clone_id=" +
                            item.id;
                        $("#adddialog").load(href).dialog("open");
                    });

                    $("a#ticketdialog" + item.id).click(function () {
                        // Use this to get href
                        var href = settings.rootdoc + "/plugins/tasklists/ajax/seetask.php?addticket=1&task_id=" +
                            item.id;
                        $("#adddialog").load(href).dialog("open");
                    });

                    // $("a#clonetask" + item.id).click(function () {
                    //     var clonetask = 1;
                    //     var data_id = item.id;
                    //     var data = {clonetask, data_id};
                    //     $.ajax({
                    //         data: data,
                    //         type: 'POST',
                    //         url: '../ajax/updatetask.php',
                    //         success: function (data) {
                    //             location.reload();
                    //             window.location.href = "kanban.php";
                    //         }
                    //     });
                    // });

                    $("a#archivetask" + item.id).click(function () {
                        var archivetask = 1;
                        var data_id = item.id;
                        var data = {archivetask, data_id};
                        if (confirm(settings.lang['alert_archive_task'])) {
                            $.ajax({
                                data: data,
                                type: 'POST',
                                url: '../ajax/updatetask.php',
                                success: function (data) {
                                    location.reload();
                                    window.location.href = "kanban.php";
                                }
                            });
                        }
                    });
                });

                $("#slider" + item.id).slider({
                    min: 0,
                    max: 100,
                    step: 10,
                    range: "min",
                    animate: "slow",
                    value: item.percent,
                    slide: function (event, ui) {
                        $("#percent" + item.id).val(ui.value + "%");
                    },
                    change: function (event, ui) {
                        var percent_done = ui.value;
                        var data_id = item.id;
                        var data = {percent_done, data_id};
                        $.ajax({
                            data: data,
                            type: 'POST',
                            url: '../ajax/updatetask.php',
                            success: function (data) {
                                location.reload();
                                window.location.href = "kanban.php";
                            }
                        });
                    }
                });
                $("#percent" + item.id).val($("#slider" + item.id).slider("value") + "%");


            });
        }

        build_kanban();

    }

}(jQuery));

function zoomin(kid){
    var currWidth = kid.clientWidth;
    kid.style.width = (currWidth + 200) + "px";
}

function zoomout(kid){
    var currWidth = kid.clientWidth;
    if(currWidth <= 1400) return false;
    else{
        kid.style.width = (currWidth - 200) + "px";
    }
}


(function(){
window.GLPIKanban2 = function() {
    /**
     * Self-reference for property access in functions.
     */
    var self = this;

    /**
     * Selector for the parent Kanban element. This is specified in PHP and passed in the GLPIKanban constructor.
     * @since 9.5.0
     * @type {string}
     */
    this.element = "#kanban";

    this.supported_itemtypes = {'name':'PluginTasklistsTasks'};

    this.add_column_form = "";

    var build = function() {
        buildToolbar();

        var kanban_container = $("<div class='kanban-container'><div class='kanban-columns'></div></div>").appendTo($(self.element));

        var add_itemtype_dropdown = "<ul id='kanban-add-dropdown' style='display: none'>";
        Object.keys(self.supported_itemtypes).forEach(function(itemtype) {
            add_itemtype_dropdown += "<li id='kanban-add-" + itemtype + "'>" + self.supported_itemtypes[itemtype]['name'] + '</li>';
        });
        add_itemtype_dropdown += '</ul>';
        kanban_container.append(add_itemtype_dropdown);
/*
        var on_refresh = function() {
            if (Object.keys(self.user_state.state).length === 0) {
                // Save new state since none was stored for the user
                saveState(true, true);
            }
        };
*/

       // self.refresh(on_refresh, null, null, true);

        // if (self.allow_modify_view) {
            buildAddColumnForm();
           // if (self.allow_create_column) {
                buildCreateColumnForm();
            // }
        // }






    };

    var buildToolbar = function() {
        var toolbar = $("<div class='kanban-toolbar'></div>").appendTo(self.element);
        $("<select name='kanban-board-switcher'></select>").appendTo(toolbar);
        var filter_input = $("<input name='filter' type='text' placeholder='" + 'Search or filter results' + "'/>").appendTo(toolbar);

            var add_column = "<input type='button' class='kanban-add-column submit' value='" + 'Add column' + "'/>";
            toolbar.append(add_column);

        filter_input.on('input', function() {
            var text = $(this).val();
            if (text === null) {
                text = '';
            }
            self.filters._text = text;
            self.filter();
        });
    };
    /**
     * Create the add column form and add it to the DOM.
     * @since 9.5.0
     */
    var buildAddColumnForm = function() {
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
            add_form += "<input type='button' class='submit kanban-create-column' value='"+self.translations['Create status']+"'/>";
        }
        add_form += "</form></div>";
        $(self.element).prepend(add_form);
    };

    /**
     * Create the create column form and add it to the DOM.
     * @since 9.5.0
     */
    var buildCreateColumnForm = function() {
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
       /* $.each(self.column_field.extra_fields, function(name, field) {
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
        */
        create_form += "</div>";
        create_form += "<input type='button' class='submit kanban-create-column' value='"+self.translations['Create status']+"'/>";
        create_form += "</form></div>";
        $(self.element).prepend(create_form);
       $(self.create_column_form + " input[type='color']").spectrum();
    };

    /**
     * Add all event listeners. At this point, all elements should have been added to the DOM.
     * @since 9.5.0
     */
    var registerEventListeners = function() {
        var dropdown = $('#kanban-add-dropdown');

      //  refreshSortables();

        if (Object.keys(self.supported_itemtypes).length > 0) {
            $(self.element + ' .kanban-container').on('click', '.kanban-add', function(e) {
                var button = $(e.target);
                //Keep menu open if clicking on another add button
                var force_stay_visible = $(dropdown.data('trigger-button')).prop('id') !== button.prop('id');
                dropdown.css({
                    position: 'fixed',
                    left: button.offset().left,
                    top: button.offset().top + button.outerHeight(true),
                    display: (dropdown.css('display') === 'none' || force_stay_visible) ? 'inline' : 'none'
                });
                dropdown.data('trigger-button', button);
            });
        }
        $(window).on('click', function(e) {
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
        $(self.element + ' .kanban-container').on('click', '.kanban-delete', function(e) {
            hideColumn(getColumnIDFromElement(e.target.closest('.kanban-column')));
        });
        $(self.element + ' .kanban-container').on('click', '.kanban-collapse-column', function(e) {
            self.toggleCollapseColumn(e.target.closest('.kanban-column'));
        });
        /*
        $(self.element).on('click', '.kanban-add-column', function() {
            refreshAddColumnForm();
        });

         */
        $(self.add_column_form).on('input', "input[name='column-name-filter']", function() {
            var filter_input = $(this);
            $(self.add_column_form + ' li').hide();
            $(self.add_column_form + ' li').filter(function() {
                return $(this).text().toLowerCase().includes(filter_input.val().toLowerCase());
            }).show();
        });
        $(self.add_column_form).on('change', "input[type='checkbox']", function() {
            var column_id = $(this).parent().data('list-id');
            if (column_id !== undefined) {
                if ($(this).is(':checked')) {
                    showColumn(column_id);
                } else {
                    hideColumn(column_id);
                }
            }
        });
        $(self.add_column_form).on('click', '.kanban-create-column', function() {
            var toolbar = $(self.element + ' .kanban-toolbar');
            $(self.add_column_form).css({
                display: 'none'
            });
            $(self.create_column_form).css({
                display: 'block',
                position: 'fixed',
                left: toolbar.offset().left + toolbar.outerWidth(true) - $(self.create_column_form).outerWidth(true),
                top: toolbar.offset().top + toolbar.outerHeight(true)
            });
        });
        $(self.create_column_form).on('click', '.kanban-create-column', function() {
            var toolbar = $(self.element + ' .kanban-toolbar');
            $(self.create_column_form).css({
                display: 'none'
            });
            var name = $(self.create_column_form + " input[name='name']").val();
            $(self.create_column_form + " input[name='name']").val("");
            var color = $(self.create_column_form + " input[name='color']").spectrum('get').toHexString();
            createColumn(name, {color: color}, function() {
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
        $('#kanban-add-dropdown li').on('click', function(e) {
            e.preventDefault();
            var selection = $(e.target);
            var dropdown = selection.parent();
            var column = $($(dropdown.data('trigger-button')).closest('.kanban-column'));
            var itemtype = selection.prop('id').split('-')[2];
            self.clearAddItemForms(column);
            self.showAddItemForm(column, itemtype);
            delayRefresh();
        });
        var switcher = $("select[name='kanban-board-switcher']").first();
        $(self.element + ' .kanban-toolbar').on('select2:select', switcher, function(e) {
            var items_id = e.params.data.id;
            $.ajax({
                type: "GET",
                url: (self.ajax_root + "kanban.php"),
                data: {
                    action: "get_url",
                    itemtype: self.item.itemtype,
                    items_id: items_id
                },
                contentType: 'application/json',
                success: function(url) {
                    window.location = url;
                }
            });
        });

        $(self.element).on('input', '.kanban-add-form input, .kanban-add-form textarea', function() {
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
     * Refresh the Kanban with the new set of columns.
     *    This will clear all existing columns from the Kanban, and replace them with what is provided by the server.
     * @since 9.5.0
     * @param {function} success Callback for when the Kanban is successfully refreshed.
     * @param {function} fail Callback for when the Kanban fails to be refreshed.
     * @param {function} always Callback that is called regardless of the success of the refresh.
     * @param {boolean} initial_load True if this is the first load. On the first load, the user state is not saved.
     */
    this.refresh = function(success, fail, always, initial_load) {
        var _refresh = function() {
            $.ajax({
                method: 'GET',
                //async: false,
                url: (self.ajax_root + "kanban.php"),
                data: {
                    action: "refresh",
                    itemtype: self.item.itemtype,
                    items_id: self.item.items_id,
                    column_field: self.column_field.id
                },
                contentType: 'application/json',
                dataType: 'json'
            }).done(function(columns, textStatus, jqXHR) {
                preloadBadgeCache({
                    trim_cache: true
                });
                clearColumns();
                self.columns = columns;
                fillColumns();
                // Re-filter kanban
                self.filter();
                if (success) {
                    success(columns, textStatus, jqXHR);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                if (fail) {
                    fail(jqXHR, textStatus, errorThrown);
                }
            }).always(function() {
                if (always) {
                    always();
                }
            });
        };
        if (initial_load === undefined || initial_load === true) {
            _refresh();
        } else {
            saveState(false, false, null, null, function() {
                loadState(_refresh);
            });
        }

    };

    this.init = function () {
        $.ajax({
            type: 'GET',
            url: (CFG_GLPI.root_doc + '/plugins/tasklists/ajax/kanban.php'),
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
                        url: (CFG_GLPI.root_doc + '/plugins/tasklists/ajax/kanban.php'),
                        data: {
                            action: 'get_switcher_dropdown',
                            itemtype: 'PluginTasklistsTaskType',
                            items_id: -1
                        },
                        contentType: 'application/json',
                        success: function ($data) {
                            var switcher = $(self.element + " .kanban-toolbar select[name='kanban-board-switcher']");
                            switcher.replaceWith($data);
                        }
                    });
                    registerEventListeners();
                });

        });
    };



}})();