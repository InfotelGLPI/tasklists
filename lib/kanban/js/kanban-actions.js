$(document.body).on("kanban:post_build_toolbar", "#kanban", () => {

   var datas = $("#kanban").data('js_class');
   var kcolumns = datas.columns;
   // console.log(datas);

   //Infotel add link on toolbar for archive all tasks on this column
   $.each(kcolumns, function( index, value ) {
      var finishState = value.finished;
      if (datas.rights.canCreateItem) {
         if (finishState === 1) {
            var archive_all_tasks = __('Archive all tasks of this state', 'tasklists');
            // datas.toolbar_el = "<i id='kanban_delete_" + value.id + "' class='kanban-delete pointer ti ti-archive' title='" + archive_all_tasks + "'></i>";
         }
      }
   });

   // this.root_taslists = PLUGIN_TASKLISTS_WEBDIR + "/ajax/";
   // self.tasklist_root = self.root_taslists + "/ajax/";

   // var archiveColumnTask = function (column) {
   //    var archivealltasks = 1;
   //    var state_id = column;
   //    var context_id = self.item.items_id;
   //    var data = {archivealltasks, state_id, context_id};
   //    var alert_archive_all_tasks = __('Are you sure you want to archive all tasks ?', 'tasklists');
   //    if (confirm(alert_archive_all_tasks)) {
   //       $.ajax({
   //          data: data,
   //          type: 'POST',
   //          url: (self.tasklist_root + "updatetask.php"),
   //          success: function (data) {
   //             self.refresh();
   //          }
   //       });
   //    }
   // };
});

$(document.body).on("kanban:post_build", "#kanban", () => {

   //Infotel add card background
   // <li id="${card['id']}"
   //     className="kanban-item card ${readonly ? 'readonly' : ''} ${card['is_deleted'] ? 'deleted' : ''}"
   //     style="background-color:${card['bgcolor']}">


   //Infotel add link for update priority & archive task
   // let link = "";
   // if (card.finished == 1 && card.archived == 0) {
   //    let title_archive = __('Archive this task', 'tasklists');
   //    link += '<a id="archivetask' + items_id + '" href="#" title="' + title_archive + '"><i class="ti ti-archive"></i></a>&nbsp;';
   // }
   // if (card.finished == 0 && card.priority_id < self.max_priority) {
   //    let title_priority = __('Update priority of task', 'tasklists');
   //    link += '<a id="updatepriority' + items_id + '" href="#" title="' + title_priority + '"><i class="ti ti-arrow-up"></i></a>';
   // }
   // card_el += link;

   // $("a#archivetask" + items_id).click(function () {
   //    var archivetask = 1;
   //    var data_id = items_id;
   //    var data = {archivetask, data_id};
   //    let title_archive = __('Are you sure you want to archive this task ?', 'tasklists');
   //    if (confirm(title_archive)) {
   //       $.ajax({
   //          data: data,
   //          type: 'POST',
   //          url: (self.tasklist_root + "updatetask.php"),
   //          success: function (data) {
   //             self.refresh();
   //          }
   //       });
   //    }
   // });
   // $("a#updatepriority" + items_id).click(function () {
   //    var updatepriority = 1;
   //    var data_id = items_id;
   //    var data = {updatepriority, data_id};
   //    $.ajax({
   //       data: data,
   //       type: 'POST',
   //       url: (self.tasklist_root + "updatetask.php"),
   //       success: function (data) {
   //          self.refresh();
   //       }
   //    });
   // });
});
