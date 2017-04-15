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

class e11CodeBrowser {
  private static $initialized = false;

  /**
   * Initialize plugin.
   */
  public static function init() {

    // Ensure function is called only once.

    if (self::$initialized) {
      return;
    }

    self::$initialized = true;


    // Load stylesheet for plugin.

    wp_register_style('e11-code-browser.css',
      plugin_dir_url(__FILE__) . 'css/e11-code-browser.css',
      array(),
      E11_CODE_BROWSER_VERSION);

    wp_enqueue_style('e11-code-browser.css');
  }
}
