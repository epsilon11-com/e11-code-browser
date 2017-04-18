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
  private static $cacheTableName;
  private static $errors = array();

  /**
   * Initialize plugin (admin side).
   */
  public static function init() {
    global $wpdb;

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

    // Set table name for code browser cache.

    self::$cacheTableName = $wpdb->prefix . 'e11_code_browser_cache';

    // Trigger update procedure on version change.

    if (get_option('e11_code_browser_version')
                                    != E11_CODE_BROWSER_VERSION) {
      self::perform_update();
    }
  }

  public static function perform_update() {

    // Create recommended links table.

    $sql = 'CREATE TABLE ' . self::$cacheTableName . ' (
            `id` integer NOT NULL AUTO_INCREMENT,
            `post_id` bigint(20) NOT NULL,
            `hash` char(64) NOT NULL,
            `content` text NOT NULL DEFAULT "",
            
            PRIMARY KEY (`id`),
            UNIQUE (`post_id`, `hash`)
      );';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Update plugin version.

    update_option('e11_code_browser_version',
                                      E11_CODE_BROWSER_VERSION);
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


  private static function _exec($cmd, &$output, &$stderr) {
    $process = proc_open($cmd, array(
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w'),
    ), $pipes);

    $output = explode("\n", stream_get_contents($pipes[1]));
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return proc_close($process);
  }

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
    global $wpdb;

    // Track each GitHub download so that multiple references to the same
    // repo won't trigger multiple downloads.

    $githubDownloaded = array();

    // Remove cache table entries for post ID.

    $wpdb->delete(self::$cacheTableName,
                          array('post_id' => $postarr['ID']), array('%d'));

    // Read 'ecode' shortcode tags from $data['post_content'] that contain
    // a 'local' or 'github' parameter.

    if (preg_match_all('/\[\s*ecode\s[^\]]*(?:local\s*=|github\s*=)[^\]]+\]/i',
                                                wp_unslash($data['post_content']), $tags)) {

      foreach ($tags[0] as $tag) {
        $error = array();

        preg_match('/local\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $local);
        preg_match('/github\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $github);
        preg_match('/ref\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $ref);
        preg_match('/file\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $file);
        preg_match('/lines\s*=\s*[\'"]([^\'"]*)[\'"]/', $tag, $lines);

        // If 'local' or 'github' attributes are supplied, they cannot be
        // empty.

        if (!empty($local) && !empty($github)) {
          // 'local' or 'github' sttributes cannot be supplied at the same time.

          $error[] = __('"local" and "github" attributes cannot be specified
                                                together', 'e11-code-browser');
        } elseif (!empty($local)) {
          $local = trim(rawurldecode($local[1]));
          
          if (empty($local)) {
            $error[] = __('"local" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        } elseif (!empty($github)) {
          $github = trim($github[1]);

          if (empty($github)) {
            $error[] = __('"github" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        }

        // 'ref' and 'file' attributes cannot be empty.

        if (!empty($ref)) {
          $ref = trim($ref[1]);
          
          if (empty($ref)) {
            $error[] = __('"ref" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        } else {
          $error[] = __('"ref" attribute is required', 'e11-code-browser');
        }

        if (!empty($file)) {
          $file = trim($file[1]);

          if (empty($file)) {
            $error[] = __('"file" attribute cannot be empty',
                                                  'e11-code-browser');
          }
        } else {
          $error[] = __('"file" attribute is required', 'e11-code-browser');
        }

        // Validate 'lines' attribute if provided.  It will be converted into
        // a two element array with a starting and ending line number if these
        // are present, or an empty array if not.

        if (!empty($lines)) {
          $lines = trim($lines[1]);

          if (!empty($lines)) {
            if (preg_match('/^(\d+)-(\d+)$/', $lines, $res)) {
              $lines = array_splice($res, 1, 2);
            } else {
              $error[] = __('"lines" attribute is invalid', 'e11-code-browser');
            }
          }
        } else {
          $lines = array();
        }

        if (empty($error)) {
          // Process line if no errors found.

          if (!empty($github)) {
            // [TODO] Allow the content directory for downloaded repositories
            //        to be set by the user.  Currently using
            //        WP_CONTENT_DIR . '/cache/e11-code-browser/'.

            $contentDir = parse_url(content_url(), PHP_URL_PATH);

            // Get path to WordPress directory, ensuring that if a trailing
            // slash is present it's removed.

            $homePath = get_home_path();

            if (substr($homePath, -1, 1) == '/') {
              $homePath = substr($homePath, 0, -1);
            }

            // Build path to the directory to store downloaded GitHub repos,
            // then make the directory (and any parent directories in the
            // path that don't exist.)

            $gitDir = $homePath . $contentDir . '/cache/e11-code-browser/';

            if (!file_exists($gitDir)) {
              if (!mkdir($gitDir, 0755, true)) {
                $error[] = __('Unable to make GitHub cache directory',
                                                          'e11-code-browser');
              }
            }

            // Break here if the directory to download repos to cannot be
            // created.

            if (!empty($error)) {
              break;
            }

            // Build path to directory to download the specified repository to.

            $repoDir = $gitDir . $github;

            if (file_exists($repoDir)) {
              if (!isset($githubDownloaded[$repoDir])) {

                // Update repo.

                $curDir = getcwd();

                chdir($repoDir);

                $rv = self::_exec('git fetch --all', $output, $stderr);

                if ($rv !== 0) {
                  $error[] = __("\"git\" returned an error during fetch:\n",
                      'e11-code-browser') . $stderr;
                }

                $rv = self::_exec('git reset --hard origin/master',
                                                        $output, $stderr);

                if ($rv !== 0) {
                  $error[] = __("\"git\" returned an error during reset:\n",
                      'e11-code-browser') . $stderr;
                }

                chdir($curDir);

                $githubDownloaded[$repoDir] = true;
              }
            } else {
              // Fetch repo.

              $cmd = 'git clone '
                . escapeshellarg('https://github.com/' . $github) . ' '
                . escapeshellarg($repoDir);

              $rv = self::_exec($cmd, $output, $stderr);

              if ($rv !== 0) {
                $error[] = __("\"git\" returned an error:\n",
                                          'e11-code-browser') . $stderr;
              }

              // Note: not technically true if the download failed above, but
              // still want to prevent multiple download attempts to the same
              // repo in that scenario.

              $githubDownloaded[$repoDir] = true;
            }

            // Set $local to the directory of the downloaded repository.

            $local = $repoDir;
          }

          if (empty($error) && !empty($local)) {
            // Handle local repository

            // Run "git show" for the specified repo/ref/file, capturing
            // stdout (to $output), stderr, and the return value.

            $cmd = 'git --git-dir='
              . escapeshellarg($local) . '/.git show '
              . escapeshellarg($ref) . ':' . escapeshellarg($file);

            $rv = self::_exec($cmd, $output, $stderr);

            // If return value doesn't indicate success, add result to errors.
            // Otherwise, process output.

            if ($rv != 0) {
              $error[] = __("\"git\" returned an error:\n",
                                            'e11-code-browser') . $stderr;
            } else {
              if (!empty($lines)) {
                $output = join("\n",
                  array_splice($output, $lines[0] - 1,
                    $lines[1] - $lines[0] + 1));
              } else {
                $output = join("\n", $output);
              }
            }

            // Add output to cache table if Git ran successfully.

            if (empty($error)) {
              if (!empty($lines)) {
                $lines = $lines[0] . '-' . $lines[1];
              }

              if (!empty($github)) {
                $hashRepo = $github;
              } else {
                $hashRepo = $local;
              }

              $hash = hash('sha256', $hashRepo . $ref . $file . $lines);

              $wpdb->insert(
                self::$cacheTableName,
                array(
                  'post_id' => $postarr['ID'],
                  'hash' => $hash,
                  'content' => $output
                ),
                array(
                  '%d',
                  '%s',
                  '%s'
                )
              );
            }
          }
        }

        if (!empty($error)) {
          // Store error.

          self::$errors[] = array(
            'tag' => $tag,
            'errors' => $error
          );
        }
      }
    }

    if (!empty(self::$errors)) {
      add_filter('redirect_post_location',
                      array('e11CodeBrowserAdmin', 'add_admin_notices'), 99);
    }

    return $data;
  }

  public static function add_admin_notices($location) {
    remove_filter('redirect_post_location',
                      array('e11CodeBrowserAdmin', 'add_admin_notices'), 99);

    return add_query_arg(
          array('e11_code_browser_error' => base64_encode(json_encode(self::$errors))),
          $location);
  }

  public static function admin_notices() {
    if (!isset($_GET['e11_code_browser_error'])) {
      return;
    }

    $errors = json_decode(base64_decode($_GET['e11_code_browser_error']), true);

    $message = __('One or more errors occurred when resolving the following 
    Git [ecode] reference(s).  Please fix them and save the post/page again.  
    The post/page will not display all code correctly unless this is 
    resolved.', 'e11-code-browser');
?>
    <div class="notice notice-error is-dismissable">
      <p><?php echo esc_html($message) ?></p>
<?php
    foreach ($errors as $error) {
?>
      <p><strong><?php echo esc_html($error['tag']); ?></strong></p>
      <ul>
<?php
      foreach ($error['errors'] as $msg) {
?>
        <li><p><?php echo esc_html($msg); ?></p></li>
<?php
      }
?>
      </ul>
<?php
    }
?>
    </div>
<?php
  }
}

// [TODO] Hook 'wp_insert_post_data'.  Scan for shortcode referencing Git
//        repositories and perform Git queries to download the referenced code
//        into a database table to cache it.  When these shortcodes are seen
//        while reading a post, retrieve the relevant bits from the cache.

add_filter('wp_insert_post_data',
              array('e11CodeBrowserAdmin', 'cache_git_references'), '99', 2);

add_action('admin_notices', array('e11CodeBrowserAdmin', 'admin_notices'));
