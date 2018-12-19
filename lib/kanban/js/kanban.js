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
            action += '<div class="filter_right">';
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