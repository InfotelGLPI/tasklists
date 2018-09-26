(function ($) {

    $.fn.kanban = function (options) {

        // defaults

        var $this = $(this);

        var settings = $.extend({
            titles: ['Block 1', 'Block 2', 'Block 3', 'Block 4'],
            colours: [],
            items: [],
            rand: 0
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
                var item = '<div id="title'+item.id+'" style="background: ' + settings.colours[item.id] + '" class="' + classes.kanban_board_title_class + '">' + '<p>' + item.title + '</p>' + '</div>';
                $this.find('.' + classes.kanban_board_titles_class).append(item);
            });

        }

        function build_blocks() {
            settings.titles.forEach(function (item, index, array) {
                var item = '<div class="' + classes.kanban_board_block_class + '" data-block="' + settings.rand + item.id + '"></div>';
                $this.find('.' + classes.kanban_board_blocks_class).append(item);
            });

            $("." + classes.kanban_board_block_class).sortable({
                connectWith: "." + classes.kanban_board_block_class,
                // containment: "." + classes.kanban_board_blocks_class,
                // placeholder: classes.kanban_board_item_placeholder_class,
                scroll: true,
                cursor: "move",
                receive: function (event, ui) {
                    var data_destblock = $("#div"+ settings.rand+ui.item[0].dataset.id).parent().index();
                    var data_id = ui.item[0].dataset.id;
                    var data = {data_destblock, data_id};
                    $.ajax({
                        data: data,
                        type: 'POST',
                        url: '../ajax/movetask.php'
                    });
                }
            }).disableSelection();

        }

        function build_items() {
            settings.items.forEach(function (item, index, array) {
                var block = $this.find('.' + classes.kanban_board_block_class + '[data-block="' + settings.rand + item.block + '"]');
                var append = '<div id="div'+ settings.rand+item.id+'" class="' + classes.kanban_board_item_class + '" data-id="' + item.id + '">';
                append += '<div class="' + classes.kanban_board_item_title_class + '">' + '<a target="_blank" href="' + item.link + '">' + item.title + '</a>' + '</div>';

                if (item.description) {
                    append += '<div class="kanbancomment">' + item.description + '</div>';
                }
                // if (item.link) {
                //     append += '<a target="_blank" href="' + item.link + '">' + item.link_text + '</a>';
                // }

                if (item.priority) {
                    append += '<div style=background-color:' + item.bgcolor + ' class="' + classes.kanban_board_item_priority_class + '">' + item.priority + '</div>';
                }

                if (item.footer) {
                    append += '<div class="' + classes.kanban_board_item_footer_class + '">' + item.footer + '</div>';
                }

                append += '</div>';


                block.append(append);
            });
        }

        build_kanban();

    }

}(jQuery));