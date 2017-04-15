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

/**
 * @package e11-code-browser
 */
/*
Plugin Name: e11 Code Browser
Plugin URI: https://epsilon11.com/e11-code-browser
Description: Add shortcode to create nicely formatted source code excerpts.
Version: 1.0
Author: er11
Author URI: https://epsilon11.com/wordpress-plugins/
License: GPLv2 or later
Text Domain: e11-code-browser
*/

define('E11_CODE_BROWSER_VERSION', '1.0');


// Don't run if called directly.

if (!function_exists('add_action')) {
  exit;
}


// Load and initialize class for plugin.

require_once(plugin_dir_path(__FILE__) . 'class.e11CodeBrowser.php');

add_action('init', array('e11CodeBrowser', 'init'));


// If in the admin dashboard, load and initialize class for dashboard code.

if (is_admin()) {
  require_once(plugin_dir_path(__FILE__) . 'class.e11CodeBrowserAdmin.php');

  add_action('init', array('e11CodeBrowserAdmin', 'init'));
}
