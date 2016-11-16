<?php

/*
 * order_provisioner - LockFile.php
 *
 * Utility library to support locking of a file
 * for the purpose of only allowing one instance
 * of a script to run at a time.
 */

class LockFile 
{
    const DEFAULT_LOCKFILENAME_FORMAT = '/tmp/%s.lock';

    static $_fp = null;

    /**
     * Try to get an advisory lock
     *
     * @param string $lockFilename Full filename to use as lock file
     * @return boolean True if the lock was successful, false if not
     *
     */
    static public function lock($lockFilename)
    {
        // Open the lock file, or create it if it doesn't exist
        self::$_fp = fopen($lockFilename, 'c');

        // Try to get an advisory lock
        if (flock(self::$_fp, LOCK_EX|LOCK_NB)) {
            // It's ours. Truncate the file so the modification time is
            // changed, then write our PID for others to see
            ftruncate(self::$_fp, 0);

            return (fwrite(self::$_fp, getmypid() . "\n") !== false);
        } else {
            // Someone else has it
            self::$_fp = null;
            return false;
        }
    }

    /**
     * Release the lock
     *
     * If the lock is to be released when the script terminates, then
     * there is no need to call this function.
     *
     */
    static public function unlock()
    {
        if (self::$_fp !== null) {
            ftruncate(self::$_fp, 0);  // remove pid contents
            flock(self::$_fp, LOCK_UN);
            fclose(self::$_fp);
            self::$_fp = null;
        }
        return;
    }

    /**
     * Return the age of the lock
     *
     * If the lock file doesn't exist, then return PHP_INT_MAX
     *
     * @param string $format Time measurement to use (seconds or minutes).
     * @return integer Age of lock file.
     *
     */
    static public function getAge($format='seconds')
    {
        $stat = fstat(self::$_fp);
        if ($stat === false) {
            return PHP_INT_MAX;
        } else {
            if ($format == 'minutes') {
                $seconds = time() - $stat['mtime'];
                return ($seconds < 1) ? 0 : (($seconds < 60) ? 1 : floor($seconds / 60));
            } else {
                return time() - $stat['mtime'];
            }
        }
    }


    /**
     * Get the process ID of an instance of the script that is already running,
     * presuming another instance is running.
     *
     * @param string $lockFilename Full filename to use as lock file
     * @return mixed  The process ID if if another instance of the script is
     *                running; false otherwise.
     */
    static public function getRunningPid($lockFilename) {
        $pid = false;

        if (file_exists($lockFilename)) {
            $old_pid = trim(file_get_contents($lockFilename));

            // Generate a list of running PIDs.
            $pids = explode("\n", trim(shell_exec('ps -e | awk \'{print $1}\'')));

            // If the old PID is in the running PID array, the script is running.
            if (in_array($old_pid, $pids)) {
                $pid = $old_pid;
            }
        }

        return $pid;
    }
}
