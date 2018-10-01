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
        }, options)

        var classes = {
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

        function build_kanban() {

            $this.addClass(classes.kanban_board_class);
            $this.append('<div class="' + classes.kanban_board_titles_class + '"></div>');
            $this.append('<div class="' + classes.kanban_board_blocks_class + '"></div>');

            build_titles();
            build_blocks();
            build_items();

        }

        function build_titles() {

            settings.titles.forEach(function (item, index, array) {

                var action = '';
                if (item.finished == 1) {
                    action = '<li><a id="archivealltasks' + item.id + '" href="#">' + settings.lang['archive_all_tasks'] + '</a></li>';
                    action = '<a id="archivealltasks' + item.id + '" href="#" title="' + settings.lang['archive_all_tasks'] + '"><i class="fa fa-archive fa-white"></i></a>&nbsp;';
                }
                var title = '<div id="adddialog"></div><div id="title' + item.id + '" style="background: ' + settings.colours[item.id] + '" class="'
                    + classes.kanban_board_title_class + '"><div class="menu">'
                    + '<a href="#" id="nav' + settings.context + item.id + '">'
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
                        height: 600,
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

        function build_items() {

            settings.items.forEach(function (item, index, array) {

                if (item.finished == 0) {
                    faimg = 'fa-caret-up';
                } else {
                    faimg = 'fa-caret-down';
                }
                var block = $this.find('.' + classes.kanban_board_block_class + '[data-block="' + item.block + '"]');
                var append = '<div id="div' + item.id + '" class="' + classes.kanban_board_item_class + '" data-id="' + item.id +
                    '" style="background: ' + item.bgcolor + '">';
                append += '<div class="title"><div class="' + classes.kanban_board_item_title_class + '">';
                if (item.finished == 1) {
                    append += '<a id="archivetask' + item.id + '" href="#" title="' + settings.lang['archive_task'] + '"><i class="fa fa-archive"></i></a>&nbsp;';
                }
                if (item.finished == 0) {
                    append += '<a id="updatepriority' + item.id + '" href="#" title="' + settings.lang['update_priority'] + '"><i class="fa fa-arrow-up"></i></a>&nbsp;';
                }
                append += '<div id="dialog"></div><a id="showdialog' + item.id + '" href="#">' + item.title + '</a></div>'+ item.user + '</div>';
                if (item.client) {
                    append += '<div class="client">' + item.client + '</div>';
                }
                append +=  '<div id="hideopt' + item.id + '" class="hideopt"><i id="hide'+ item.id + '" class="fa faopt' + item.id + ' ' + faimg + '" title="' + settings.lang['see_details']
                    + '"></i></div>'
                    + '<div class="panelopt' + item.id + '" ' + item.finished_style + '>';

                if (item.description) {
                    append += '<div class="kanbancomment">' + item.description + '</div>';
                }

                if (item.duedate) {
                    append += '<div style="background-color:'
                        + item.bgcolor + '" class="' + classes.kanban_board_item_priority_class + '">' + item.duedate + '</div>';
                }

                // if (item.footer) {
                append += '<div class="' + classes.kanban_board_item_footer_class + '">' +
                    '<br><div align="center"><div class="kanban_slider" id="slider' + item.id + '"></div></div>' +
                    '<div align="right"><input type="text" id="percent' + item.id + '" readonly class="inputpercent" size="3"></div>' +
                    '<div align="right" class="endfooter">' + item.actiontime + '</div>';

                // }

                append += '</div></div>';


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
                        height: 750,
                        width: 800
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