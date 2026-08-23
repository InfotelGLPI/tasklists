--
-- -------------------------------------------------------------------------
-- tasklists plugin for GLPI
-- Copyright (C) 2016-2026 by the tasklists Development Team.
--
-- https://github.com/InfotelGLPI/tasklists
-- -------------------------------------------------------------------------
--
-- LICENSE
--
-- This file is part of tasklists.
--
-- tasklists is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- tasklists is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with tasklists. If not, see <http://www.gnu.org/licenses/>.
-- --------------------------------------------------------------------------
--

CREATE TABLE `glpi_plugin_tasklists_items_kanbans`
(
    `id`                             INT(11)                              NOT NULL AUTO_INCREMENT, -- id
    `itemtype`                       varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `items_id`                       int(11)                                       DEFAULT NULL,
    `users_id`                       int(11)                              NOT NULL,
    `plugin_tasklists_taskstates_id` int(11)                              NOT NULL,
    `state`                          int(1)                               NOT NULL DEFAULT 0,
    `date_mod`                       timestamp                            NULL     DEFAULT NULL,
    `date_creation`                  timestamp                            NULL     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`itemtype`, `items_id`, `users_id`, `plugin_tasklists_taskstates_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

ALTER TABLE `glpi_plugin_tasklists_preferences`
    ADD `automatic_refresh`       TINYINT(1) NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_tasklists_preferences`
    ADD `automatic_refresh_delay` INT(11)    NOT NULL DEFAULT '10';

