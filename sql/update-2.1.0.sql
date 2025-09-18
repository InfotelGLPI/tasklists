UPDATE `glpi_plugin_tasklists_items_kanbans` SET `itemtype` = 'GlpiPlugin\\Tasklists\\TaskType' WHERE `itemtype` = 'PluginTasklistsTaskType';
UPDATE `glpi_items_kanbans` SET `itemtype` = 'GlpiPlugin\\Tasklists\\TaskType' WHERE `itemtype` = 'PluginTasklistsTaskType';
UPDATE `glpi_notifications` SET `itemtype` = 'GlpiPlugin\\Releases\\Task' WHERE `itemtype` = 'PluginTasklistsTask';
UPDATE `glpi_notificationtemplates` SET `itemtype` = 'GlpiPlugin\\Releases\\Task' WHERE `itemtype` = 'PluginTasklistsTask';
