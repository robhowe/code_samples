<?php
/**
 * fdvegan_util.php
 *
 * Implementation of Util class for module fdvegan.
 * Miscellaneous utility and helper functions.
 *
 * PHP version 5.6
 *
 * @category   Util
 * @package    fdvegan
 * @author     Rob Howe <rob@robhowe.com>
 * @copyright  2015-2016 Rob Howe
 * @license    This file is proprietary and subject to the terms defined in file LICENSE.txt
 * @version    Bitbucket via git: $Id$
 * @link       http://fivedegreevegan.aprojects.org
 * @since      version 0.1
 */


class fdvegan_Util
{
    // Used by fdvegan_Content::syslog()
    public static $syslog_levels = array('LOG_DEBUG'   => 1,
                                         'LOG_INFO'    => 2,
                                         'LOG_NOTICE'  => 3,
                                         'LOG_WARNING' => 4,
                                         'LOG_ERR'     => 5,
                                         'LOG_CRIT'    => 6,
                                         'LOG_ALERT'   => 7,
                                         'LOG_EMERG'   => 8,
                                         'No Logging'  => 99,
                                        );

    private static $_default_fdvegan_syslog_output_file;


	/**
	 * Get the absolute filename of the Fdvegan Syslog Output File.
	 * Returns a filesystem dirname that is not URL-friendly.
	 */
    public static function getDefaultFdveganSyslogOutputFile()
    {
        if (!isset(self::$_default_fdvegan_syslog_output_file)) {
            $str = drupal_realpath(variable_get('file_private_path')) . '/fdvegan_syslog.txt';
            self::$_default_fdvegan_syslog_output_file = str_replace('/', DIRECTORY_SEPARATOR, $str);  // works for both Linux and Windows
        }
        return self::$_default_fdvegan_syslog_output_file;
    }


    /**
     * Get the standard URL for images hosted by this site.
     *
     * @param string $type    Any filename.
     * @return string  URL.
     */
    public static function getStandardImageUrl($filename = '')
    {
        return variable_get('file_public_path', conf_path()) . "/pictures/{$filename}";
    }


    public static function isValidDate($date, $format = 'Y-m-d', $strict = true)
    {
        $dateTime = DateTime::createFromFormat($format, $date);
        if (($dateTime !== FALSE) && $strict) {
            $errors = DateTime::getLastErrors();
            if (!empty($errors['warning_count'])) {
                return FALSE;
            }
        }
        return $dateTime && ($dateTime->format($format) == $date);
    }


	/**
     * Convert a number of minutes into a human-readable string.
     *   E.g.:  125 ==> 2 hours 5 minutes
     *
     * @param int $minutes    Any number of minutes.
     * @return string    A human-readable string ready to display.
     */
    public static function convertMinutesToHumanReadable($minutes)
    {
        $h = (int)($minutes / 60);
        $m = (int)($minutes - $h*60);
        return ($h ? ($h . (($h == 1) ? ' hour ' : ' hours ')) : '') . ($m ? ($m . (($m == 1) ? ' minute' : ' minutes')) : '');
    }


    /**
     * Run a process in the background without waiting for any output
     * or hanging a user's browser.
     * This should be called when executing particularly long-running
     * batch processes that would otherwise time-out a user's browser.
     *
     * Example usage:  fdvegan_Util::execInBackground('./myScript.php');
     */
    public static function execInBackground($cmd) {
        // if (substr(php_uname('s'), 0, 7) === "Windows") {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('where php.exe', $php_exec_path, $err);
            if ($err || empty($php_exec_path)) {
                fdvegan_Content::syslog('LOG_ERR', "Could not find path to php.exe on Windows.");
            }
            $cmd = $php_exec_path . ' ' . DRUPAL_ROOT . DIRECTORY_SEPARATOR . drupal_get_path('module', 'fdvegan') . DIRECTORY_SEPARATOR . 'fdvegan_script_init_load.php';

            fdvegan_Content::syslog('LOG_NOTICE', "Initiated execInBackground('{$cmd}') for Windows.");
            pclose(popen("start /B ". $cmd, "r"));
        } else {
            fdvegan_Content::syslog('LOG_NOTICE', "Initiated execInBackground('{$cmd}') for Linux.");
            exec($cmd . " > /dev/null &");
        }
    }


    /**
     * Called from fdvegan.install::hook_install()
     */
    public static function installVariables()
    {
        // Make sure these match what's in self::uninstallVariables()

        variable_set('fdvegan_media_files_dir', fdvegan_Media::getMediaAbsDir());  // includes the initial "/tmdb" part
        variable_set('fdvegan_tmdb_config', array());
    }

    /**
     * Called from fdvegan.install::hook_uninstall()
     */
    public static function uninstallVariables()
    {
        // Make sure these match what's in self::installVariables()

        variable_del('fdvegan_media_files_dir');
        variable_del('fdvegan_tmdb_config');
    }


    public static function pinfo() {
        ob_start();
        phpinfo();
        $data = ob_get_contents();
        ob_clean();
        return $data;
    }



    //////////////////////////////



}

