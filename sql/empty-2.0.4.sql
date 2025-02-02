DROP TABLE IF EXISTS `glpi_plugin_tasklists_tasks`;
CREATE TABLE `glpi_plugin_tasklists_tasks`
(
    `id`                             int unsigned NOT NULL auto_increment,
    `entities_id`                    int unsigned NOT NULL default '0',
    `is_recursive`                   tinyint                                 NOT NULL default '0',
    `name`                           varchar(255) collate utf8mb4_unicode_ci          default NULL,
    `plugin_tasklists_tasktypes_id`  int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_tasklists_tasktypes (id)',
    `plugin_tasklists_taskstates_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_tasklists_taskstates (id)',
    `priority`                       int unsigned NOT NULL DEFAULT '1',
    `visibility`                     int unsigned NOT NULL DEFAULT '1',
    `actiontime`                     int unsigned NOT NULL DEFAULT '0',
    `percent_done`                   int unsigned NOT NULL DEFAULT '0',
    `state`                          int unsigned NOT NULL DEFAULT '1',
    `due_date`                       timestamp NULL DEFAULT NULL,
    `users_id`                       int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
    `groups_id`                      int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_groups (id)',
    `client`                         varchar(255) COLLATE utf8mb4_unicode_ci          DEFAULT NULL,
    `content`                        text collate utf8mb4_unicode_ci,
    `notepad`                        longtext collate utf8mb4_unicode_ci,
    `date_mod`                       timestamp NULL DEFAULT NULL,
    `date_creation`                  timestamp NULL DEFAULT NULL,
    `is_template`                    smallint NOT NULL default '0',
    `template_name`                  varchar(200) collate utf8mb4_unicode_ci NOT NULL default '',
    `is_deleted`                     tinyint NOT NULL default '0',
    `is_archived`                    tinyint NOT NULL default '0',
    `users_id_requester`             int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
    PRIMARY KEY (`id`),
    KEY                              `name` (`name`),
    KEY                              `entities_id` (`entities_id`),
    KEY                              `plugin_tasklists_tasktypes_id` (`plugin_tasklists_tasktypes_id`),
    KEY                              `is_template` (`is_template`),
    KEY                              `users_id` (`users_id`),
    KEY                              `groups_id` (`groups_id`),
    KEY                              `date_mod` (`date_mod`),
    KEY                              `is_deleted` (`is_deleted`),
    KEY                              `is_archived` (`is_archived`),
    KEY                              `users_id_requester` (`users_id_requester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `glpi_plugin_tasklists_tasktypes`;
CREATE TABLE `glpi_plugin_tasklists_tasktypes`
(
    `id`                            int unsigned NOT NULL AUTO_INCREMENT,
    `name`                          varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `entities_id`                   int unsigned NOT NULL DEFAULT '0',
    `is_recursive`                  tinyint NOT NULL DEFAULT '0',
    `comment`                       text COLLATE utf8mb4_unicode_ci,
    `plugin_tasklists_tasktypes_id` int unsigned NOT NULL DEFAULT '0',
    `completename`                  text COLLATE utf8mb4_unicode_ci,
    `level`                         int unsigned NOT NULL DEFAULT '0',
    `ancestors_cache`               longtext COLLATE utf8mb4_unicode_ci,
    `sons_cache`                    longtext COLLATE utf8mb4_unicode_ci,
    PRIMARY KEY (`id`),
    KEY                             `name` (`name`),
    KEY                             `entities_id` (`entities_id`),
    KEY                             `unicity` (`plugin_tasklists_tasktypes_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `glpi_plugin_tasklists_taskstates`;
CREATE TABLE `glpi_plugin_tasklists_taskstates`
(
    `id`           int unsigned NOT NULL AUTO_INCREMENT,
    `name`         varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `entities_id`  int unsigned NOT NULL DEFAULT '0',
    `is_recursive` tinyint NOT NULL DEFAULT '0',
    `is_finished`  tinyint NOT NULL DEFAULT '0',
    `tasktypes`    varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `color`        varchar(200)                            DEFAULT '#CCC' NOT NULL,
    `comment`      text COLLATE utf8mb4_unicode_ci,
    PRIMARY KEY (`id`),
    KEY            `name` (`name`),
    KEY            `entities_id` (`entities_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


DROP TABLE IF EXISTS `glpi_plugin_tasklists_typevisibilities`;
CREATE TABLE `glpi_plugin_tasklists_typevisibilities`
(
    `id`                            int unsigned NOT NULL AUTO_INCREMENT,
    `groups_id`                     int unsigned NOT NULL default '0',
    `plugin_tasklists_tasktypes_id` int unsigned NOT NULL default '0',
    PRIMARY KEY (`id`),
    KEY                             `plugin_tasklists_tasktypes_id` (`plugin_tasklists_tasktypes_id`),
    KEY                             `groups_id` (`groups_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `glpi_plugin_tasklists_preferences`;
CREATE TABLE `glpi_plugin_tasklists_preferences`
(
    `id`                      int unsigned NOT NULL COMMENT 'RELATION to glpi_users(id)',
    `default_type`            int unsigned NOT NULL DEFAULT 0,
    `automatic_refresh`       tinyint NOT NULL DEFAULT '0',
    `automatic_refresh_delay` int unsigned NOT NULL DEFAULT '10',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `glpi_plugin_tasklists_tickets`;
CREATE TABLE `glpi_plugin_tasklists_tickets`
(
    `id`                        int unsigned NOT NULL AUTO_INCREMENT,
    `tickets_id`                int unsigned NOT NULL DEFAULT '0',
    `plugin_tasklists_tasks_id` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY                         `plugin_tasklists_tasks_id` (`plugin_tasklists_tasks_id`),
    KEY                         `tickets_id` (`tickets_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `glpi_plugin_tasklists_tasks_comments`;
CREATE TABLE `glpi_plugin_tasklists_tasks_comments`
(
    `id`                        int unsigned NOT NULL AUTO_INCREMENT,
    `plugin_tasklists_tasks_id` int unsigned NOT NULL,
    `users_id`                  int unsigned NOT NULL DEFAULT '0',
    `language`                  varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `comment`                   text COLLATE utf8mb4_unicode_ci NOT NULL,
    `parent_comment_id`         int unsigned DEFAULT NULL,
    `date_creation`             timestamp NULL DEFAULT NULL,
    `date_mod`                  timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

DROP TABLE IF EXISTS `glpi_plugin_tasklists_items_kanbans`;
CREATE TABLE `glpi_plugin_tasklists_items_kanbans`
(
    `id`                             int unsigned NOT NULL AUTO_INCREMENT, -- id
    `itemtype`                       varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `items_id`                       int unsigned DEFAULT NULL,
    `users_id`                       int unsigned NOT NULL,
    `plugin_tasklists_taskstates_id` int unsigned NOT NULL,
    `state`                          int unsigned NOT NULL DEFAULT 0,
    `date_mod`                       timestamp NULL DEFAULT NULL,
    `date_creation`                  timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`itemtype`, `items_id`, `users_id`, `plugin_tasklists_taskstates_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

