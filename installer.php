<?php
/* ------------------------------ NOTICE ----------------------------------

If you're seeing this text when browsing to the installer, it means your
web server is not set up properly.

Please contact your host and ask them to enable "PHP" processing on your
account.
----------------------------- NOTICE --------------------------------- */
namespace {
    use Duplicator\Libs\DupArchive\DupArchiveExpandBasicEngine;
    $disabled_dirs = array(
        'backups-dup-lite',
        'wp-snapshots'
    );
    if (in_array(basename(dirname(__FILE__)), $disabled_dirs)) {
        die;
    }
    define('KB_IN_BYTES', 1024);
    define('MB_IN_BYTES', 1024 * KB_IN_BYTES);
    define('GB_IN_BYTES', 1024 * MB_IN_BYTES);
    define('DUPLICATOR_PHP_MAX_MEMORY', 4096 * MB_IN_BYTES);
    date_default_timezone_set('UTC'); // Some machines don’t have this set so just do it here.
    @ignore_user_abort(true);
    function isIniValChangeable($setting)
    {
        static $ini_all;
        if (!isset($ini_all)) {
            $ini_all = false;
            if (function_exists('ini_get_all')) {
                $ini_all = ini_get_all();
            }
        }
        if (isset($ini_all[$setting]['access']) && ( INI_ALL === ( $ini_all[$setting]['access'] & 7 ) || INI_USER === ( $ini_all[$setting]['access'] & 7 ) )) {
            return true;
        }
        if (!is_array($ini_all)) {
            return true;
        }
        return false;
    }

    
    
