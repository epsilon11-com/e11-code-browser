<?php

// e11 Code Browser
// Copyright (C) 2017 Eric Adolfson
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along
// with this program; if not, write to the Free Software Foundation, Inc.,
// 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

// [TODO] What is involved with multisite support and delete_site_option()?

// Remove options.

delete_option('e11_code_browser_version');

// Remove links table.

global $wpdb;

$wpdb->query('DROP TABLE IF EXISTS '
                    . $wpdb->prefix . 'e11_code_browser');
