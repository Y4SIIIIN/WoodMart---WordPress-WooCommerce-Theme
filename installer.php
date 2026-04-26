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
    
