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
  private static $cacheTableName;

  /**
   * Initialize plugin.
   */
  public static function init() {
    global $wpdb;

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


    // Set table name for code browser cache.

    self::$cacheTableName = $wpdb->prefix . 'e11_code_browser_cache';
  }

  private static $sequenceNum = 1;

  /**
   * Shortcode handler for [ecode].
   *
   * @param array $attr Shortcode attributes
   * @param string $content Shortcode content, defaults to empty string if not
   *                        provided
   * @return string Output for shortcode
   */
  public static function shortcode_ecode($attr, $content = '') {
    global $wpdb;

    if (isset($attr['local'])) {

      // Require 'ref', 'file', and 'lines' to be specified.

      if (!isset($attr['ref']) || !isset($attr['file']) || !isset($attr['lines'])) {
        return '';
      }

      // Look up code in cache table.

      $hash = hash('sha256', $attr['local'] . $attr['ref']
        . $attr['file'] . $attr['lines']);

      $content = $wpdb->get_var($wpdb->prepare('
        SELECT content FROM ' . self::$cacheTableName . '
        WHERE hash = %s
        ', array($hash)
      ));

      $content = esc_html($content);
    } elseif (isset($attr['github'])) {
      // Require 'ref', 'file', and 'lines' to be specified.

      if (!isset($attr['ref']) || !isset($attr['file']) || !isset($attr['lines'])) {
        return '';
      }

      // Look up code in cache table.

      $hash = hash('sha256', $attr['github'] . $attr['ref']
        . $attr['file'] . $attr['lines']);

      $content = $wpdb->get_var($wpdb->prepare('
        SELECT content FROM ' . self::$cacheTableName . '
        WHERE hash = %s
        ', array($hash)
      ));

      $content = esc_html($content);
    }

    if (isset($content)) {
      // [TODO] Allow customization of footer format
      // [TODO] Allow footer to be displayed as header

      $footer = '';

      $firstLine = 1;
      $lastLine = -1;

      if (isset($attr['lines'])) {
        if (preg_match('/^(\d+)-(\d+)/', $attr['lines'], $result)) {
          $firstLine = $result[1];
          $lastLine = $result[2];
        }
      }

      if (isset($attr['file'])) {
        $footer .= '<span class="ecode_file">' . $attr['file'] . '</span>';

        if (isset($attr['lines'])) {
          $footer .= '<span class="ecode_lines">'
                                    . __('lines', 'e11-code-browser') . ' '
                                    . $attr['lines'] . '</span>';
        } else {
          $footer .= '<span class="ecode_lines">'
                        . __('entire file', 'e11-code-browser') . '</span>';
        }
      }

      if (isset($attr['github'])) {
        if ($firstLine > 1) {
          $lineAnchor = '#L' . $firstLine . '-L' . $lastLine;
        } else {
          $lineAnchor = '';
        }

        $footer .= '<span class="ecode_github"><a href="https://github.com/'
          . $attr['github']
          . '/blob/' . $attr['ref'] . '/' . $attr['file']
          . $lineAnchor . '">GitHub</a></span>';
      }

      if (!empty($footer)) {
        $footer = '<div class="ecode_footer">' . $footer . '</div>';
      }

      return '<div id="ecode_' .
        sprintf('%04d', self::$sequenceNum++)
        . '" class="ecode"><pre class="line-numbers" data-start="'
        . $firstLine . '"><code class="language-php">'
        . $content
        . '</code></pre>'
        . $footer
        . '</div>';
    }

//    } else {
//      return '<pre><code id="ecode_' .
//        sprintf('%04d', self::$sequenceNum++)
//        . '" class="language-php">' . $content . '</code></pre>';
//    }

    return '';
  }
}

// Add [ecode] shortcode handler.

add_shortcode('ecode', array('e11CodeBrowser', 'shortcode_ecode'));
