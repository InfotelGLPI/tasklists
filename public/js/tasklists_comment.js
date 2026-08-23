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

/* global $ */

$(function () {
    var _bindForm = function (form) {
        form.find('input[type=reset]').on('click', function (e) {
            e.preventDefault();
            form.remove();
            $('.displayed_content').show();
        });
    };

    // Resolve the AJAX endpoint from the container data attribute (no inline PHP).
    var _ajaxUrl = function (el) {
        return el.closest('.forcomments').data('ajax-url');
    };

    // Delegated handlers: the comment thread may be (re)injected via AJAX.
    $(document).on('click', '.add_answer', function () {
        var _this = $(this);
        var _url = _ajaxUrl(_this);
        if (!_url) {
            return;
        }
        var _data = {
            'plugin_tasklists_tasks_id': _this.data('plugin_tasklists_tasks_id'),
            'answer': _this.data('id')
        };

        if (_this.data('language') !== undefined) {
            _data.language = _this.data('language');
        }

        if (_this.parents('.comment').find('#newcomment' + _this.data('id')).length > 0) {
            return;
        }

        $.ajax({
            url: _url,
            method: 'post',
            cache: false,
            data: _data,
            success: function (data) {
                var _form = $('<div class="newcomment ms-3" id="newcomment' + _this.data('id') + '">' + data + '</div>');
                _bindForm(_form);
                _this.parents('.h_item').after(_form);
            },
            error: function () {
                glpi_alert({
                    title: __('Unable to load comment!'),
                    message: __('Contact your GLPI admin!')
                });
            }
        });
    });

    $(document).on('click', '.edit_item', function () {
        var _this = $(this);
        var _url = _ajaxUrl(_this);
        if (!_url) {
            return;
        }
        var _data = {
            'plugin_tasklists_tasks_id': _this.data('plugin_tasklists_tasks_id'),
            'edit': _this.data('id')
        };

        if (_this.data('language') !== undefined) {
            _data.language = _this.data('language');
        }

        if (_this.parents('.comment').find('#editcomment' + _this.data('id')).length > 0) {
            return;
        }

        $.ajax({
            url: _url,
            method: 'post',
            cache: false,
            data: _data,
            success: function (data) {
                var _form = $('<div class="editcomment" id="editcomment' + _this.data('id') + '">' + data + '</div>');
                _bindForm(_form);
                _this
                    .parents('.displayed_content').hide()
                    .parent()
                    .append(_form);
            },
            error: function () {
                glpi_alert({
                    title: __('Unable to load comment!'),
                    message: __('Contact your GLPI admin!')
                });
            }
        });
    });
});
