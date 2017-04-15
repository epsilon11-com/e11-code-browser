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
 * Class e11CodeBrowserAdmin
 */
class e11CodeBrowserAdmin {
  private static $initialized = false;

  /**
   * Initialize plugin (admin side).
   */
  public static function init() {

    // Ensure function is called only once.

    if (self::$initialized) {
      return;
    }

    self::$initialized = true;


    // Load stylesheet for plugin.

    wp_register_style('e11-code-browser-admin.css',
      plugin_dir_url(__FILE__) . 'css/e11-code-browser-admin.css',
      array(),
      E11_CODE_BROWSER_VERSION);

    wp_enqueue_style('e11-code-browser-admin.css');
  }

  // When working with remote Git:
  //
  // * Create a directory for the repository within a working directory under
  //   wp-content.  (Allow the location of this working directory to be
  //   configured.)
  // * Clone the repository to the directory.
  // * Perform operations on repository.
  // * Remove repository(?)
  //
  // ** Use WP_CONTENT_DIR/cache/e11-code-browser/git to store git repos.
  //
  // It may need to have timeout protection.  Also warnings against doing this
  // if the Git repository is large -- suggest downloading it once and using
  // references to the local repository, or manually cutting/pasting lines.
  // Or have an option to retain the repository and update it instead of doing
  // a clone every time?

  // For git show, forbid any references starting with - and omit spaces.
  // The file reference should allow spaces if between unescaped single quotes.
  // Does proc_open() deal with spaces in parameters?

  /**
   * Scan a post for [ecode] shortcodes with attributes referencing a Git
   * repository.  Retrieve the sections of code from the repository into a
   * table.
   *
   * @param array $data Array of slashed post data
   * @param array $postarr Array of sanitized, but otherwise unmodified,
   *                       post data
   * @return array $data as modified by this function
   */
  public static function cache_git_references($data, $postarr) {
    // Read 'ecode' shortcode tags from $data['post_content'] that contain
    // a 'local' or 'github' parameter.

    if (preg_match_all('/\[\s*ecode\s[^\]]*(?:local\s*=|github\s*=)[^\]]+\]/i',
                                                $data['post_content'], $tags)) {
      foreach ($tags[0] as $tag) {
        $error = array();

        preg_match('/local\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $local);
        preg_match('/github\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $github);
        preg_match('/ref\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $ref);
        preg_match('/file\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $file);
        preg_match('/line\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $lines);

        if (isset($local)) {
          $local = trim(rawurldecode($local[1]));
          
          if (empty($local)) {
            $error[] = __('"local" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        } elseif (isset($github)) {
          $github = trim($github[1]);

          if (empty($github)) {
            $error[] = __('"github" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        }
        
        if (isset($ref)) {
          $ref = trim($ref);
          
          if (empty($ref)) {
            $error[] = __('"ref" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        } else {
          $error[] = __('"ref" attribute is required', 'e11-code-browser');
        }

        if (isset($file)) {
          $file = trim($file);

          if (empty($file)) {
            $error[] = __('"file" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        } else {
          $error[] = __('"file" attribute is required', 'e11-code-browser');
        }

        if (isset($lines)) {
          $lines = trim($lines);

          if (!empty($lines) && !preg_match('/^\d+-\d+$/', $lines)) {
            $error[] = __('"lines" attribute is invalid', 'e11-code-browser');
          }
        } else {
          $lines = '';
        }
      }
    }

    return $data;
  }
}

// [TODO] Hook 'wp_insert_post_data'.  Scan for shortcode referencing Git
//        repositories and perform Git queries to download the referenced code
//        into a database table to cache it.  When these shortcodes are seen
//        while reading a post, retrieve the relevant bits from the cache.

add_filter('wp_insert_post_data',
              array('e11CodeBrowserAdmin', 'cache_git_references'));
