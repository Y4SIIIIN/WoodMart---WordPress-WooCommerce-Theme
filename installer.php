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
    @set_time_limit(3600);
    if (isIniValChangeable('memory_limit')) {
        @ini_set('memory_limit', DUPLICATOR_PHP_MAX_MEMORY);
    }
    if (isIniValChangeable('max_input_time')) {
        @ini_set('max_input_time', '-1');
    }
    if (isIniValChangeable('pcre.backtrack_limit')) {
        @ini_set('pcre.backtrack_limit', PHP_INT_MAX);
    }
    if (isIniValChangeable('default_socket_timeout')) {
        @ini_set('default_socket_timeout', 3600);
    }

    LogHandler::init_error_handler();
    class DUPX_Bootstrap
    {
        const ARCHIVE_FILENAME   = 'digikala.zip';
        const ARCHIVE_SIZE       = '194207259';
        const INSTALLER_DIR_NAME = 'dup-installer';
        const PACKAGE_HASH       = 'a8a67e3-26131339';
        const SECONDARY_PACKAGE_HASH = 'da1cf69-26131339';
        const VERSION            = '1.5.11.2';
        const MINIMUM_PHP_VERSION = '5.6.20';
        const ZIP_MODE_AUTO    = 0;
        const ZIP_MODE_ARCHIVE = 1;
        const ZIP_MODE_SHELL   = 2;
        public $targetRoot            = null;
        public $origDupInstFolder     = null;
        public $targetDupInstFolder   = null;
        public $targetDupInst         = null;
        public $manualExtractFileName = null;
        public $isCustomDupFolder     = false;
        public $hasZipArchive         = false;
        public $hasShellExecUnzip     = false;
        public $mainInstallerURL;
        public $archiveExpectedSize   = 0;
        public $archiveActualSize     = 0;
        public $archiveRatio          = 0;
        private static $instance = null;
        private function __construct()
        {
            $this->setHTTPHeaders();
            $this->targetRoot        = self::setSafePath(dirname(__FILE__));
            $this->log('', true);
            $archive_filepath = $this->getArchiveFilePath();
            $this->origDupInstFolder = self::INSTALLER_DIR_NAME;
            $this->targetDupInstFolder = filter_input(INPUT_GET, 'dup_folder', FILTER_SANITIZE_SPECIAL_CHARS, array(
                "options" => array(
                    "default" => self::INSTALLER_DIR_NAME,
                ),
                'flags'   => FILTER_FLAG_STRIP_HIGH));
            $this->isCustomDupFolder     = $this->origDupInstFolder !== $this->targetDupInstFolder;
            $this->targetDupInst         = $this->targetRoot . '/' . $this->targetDupInstFolder;
            $this->manualExtractFileName = 'dup-manual-extract__' . self::PACKAGE_HASH;
            if ($this->isCustomDupFolder) {
                $this->extractionTmpFolder = $this->getTempDir($this->targetRoot);
            } else {
                $this->extractionTmpFolder = $this->targetRoot;
            }
            DUPX_CSRF::init($this->targetDupInst, self::PACKAGE_HASH);
            $archiveActualSize         = @file_exists($archive_filepath) ? @filesize($archive_filepath) : false;
            $archiveActualSize         = ($archiveActualSize !== false) ? $archiveActualSize : 0;
            $this->hasZipArchive       = class_exists('ZipArchive');
            $this->hasShellExecUnzip   = $this->getUnzipFilePath() != null ? true : false;
            $this->archiveExpectedSize = strlen(self::ARCHIVE_SIZE) ? self::ARCHIVE_SIZE : 0;
            $this->archiveActualSize   = $archiveActualSize;
            if ($this->archiveExpectedSize > 0) {
                $this->archiveRatio = (((1.0) * $this->archiveActualSize) / $this->archiveExpectedSize) * 100;
            } else {
                $this->archiveRatio = 100;
            }
        }
        public static function getInstance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        private function setHTTPHeaders()
        {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }


        

