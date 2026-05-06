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
        private function getTempDir($path)
        {
            $tempfile = tempnam($path, 'dup-installer_tmp_');
            if (file_exists($tempfile)) {
                unlink($tempfile);
                mkdir($tempfile);
                if (is_dir($tempfile)) {
                    return $tempfile;
                }
            }
            return false;
        }
        public static function phpVersionCheck()
        {
            if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=')) {
                return true;
            }
            $match = null;
            if (preg_match("#^\d+(\.\d+)*#", PHP_VERSION, $match)) {
                $phpVersion = $match[0];
            } else {
                $phpVersion = PHP_VERSION;
            }
            ?><!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                    <meta name="robots" content="noindex,nofollow">
                    <title>Duplicator - issue</title>
                </head>
                <body>
                    <div>
                        <h1>DUPLICATOR ISSUE: PHP <?php echo self::MINIMUM_PHP_VERSION; ?> REQUIRED</h1>
                        <p>
                            This server is running PHP: <b><?php echo $phpVersion; ?></b>. <i>A minimum of <b>PHP 
                            <?php echo self::MINIMUM_PHP_VERSION; ?></b> is required</i>.<br><br>
                            <b>Contact your hosting provider or server administrator and let them know you would like to upgrade your PHP version.</b>
                        </p>
                    </div>
                </body>
            </html>
            <?php
            die();
        }
        public function run()
        {
            date_default_timezone_set('UTC'); // Some machines don't have this set so just do it here
            $this->log('==DUPLICATOR INSTALLER BOOTSTRAP v' . self::VERSION . '==');
            $this->log('----------------------------------------------------');
            $this->log('Installer bootstrap start');
            $archive_filepath = $this->getArchiveFilePath();
            $archive_filename = self::ARCHIVE_FILENAME;
            $error               = null;
            $is_installer_file_valid = true;
            if (preg_match('/_([a-z0-9]{7})[a-z0-9]+_[0-9]{6}([0-9]{8})_archive.(?:zip|daf)$/', $archive_filename, $matches)) {
                $expected_package_hash = $matches[1] . '-' . $matches[2];
                if (self::PACKAGE_HASH != $expected_package_hash) {
                    $is_installer_file_valid = false;
                    $this->log("[ERROR] Installer and archive mismatch detected.");
                }
            } else {
                $this->log("[ERROR] Invalid archive file name.");
                $is_installer_file_valid = false;
            }
            if (false  === $is_installer_file_valid) {
                $error = "Installer and archive mismatch detected.
                        Ensure uncorrupted installer and matching archive are present.";
                return $error;
            }
            $extract_installer   = true;
            $extract_success     = false;
            $archiveExpectedEasy = $this->readableByteSize($this->archiveExpectedSize);
            $archiveActualEasy   = $this->readableByteSize($this->archiveActualSize);
            $archive_extension   = strtolower(pathinfo($archive_filepath, PATHINFO_EXTENSION));
            $installer_dir_found = (
                file_exists($this->targetDupInst) &&
                file_exists($this->targetDupInst . "/main.installer.php") &&
                file_exists($this->targetDupInst . "/dup-archive__" . self::PACKAGE_HASH . ".txt")
            );
            $manual_extract_found = (
                $installer_dir_found &&
                file_exists($this->targetDupInst . "/" . $this->manualExtractFileName)
            );
            $isZip = ($archive_extension == 'zip');
            if (!$manual_extract_found) {
                if (!file_exists($archive_filepath)) {
                    $this->log("[ERROR] Archive file not found!");
                    $archive_candidates = ($isZip) ? $this->getFilesWithExtension('zip') : $this->getFilesWithExtension('daf');
                    $candidate_count    = count($archive_candidates);
                    $candidate_html     = "- No {$archive_extension} files found -";
                    if ($candidate_count >= 1) {
                        $candidate_html = "<ol>";
                        foreach ($archive_candidates as $archive_candidate) {
                            $candidate_html .= '<li class="diff-list"> ' . $this->compareStrings($archive_filename, $archive_candidate) . '</li>';
                        }
                        $candidate_html .= "</ol>";
                    }
                    $error = "<style>.diff-list font { font-weight: bold; }</style>"
                        . "<b>Archive not found!</b> The required archive file must be present in the <i>'Extraction Path'</i> below. "
                        . "When the archive file name was created it was named with a secure file name.  This file name must be "
                        . "the <i>exact same</i> name as when it was created character for character.  Each archive file has a unique installer associated "
                        . "with it and must be used together.  See the list below for more options:<br/>"
                        . "<ul>"
                        . "<li>If the archive is not finished downloading please wait for it to complete.</li>"
                        . "<li>Rename the file to it original hash name.  See WordPress-Admin ❯ Packages ❯  Details. </li>"
                        . "<li>When downloading, both files both should be from the same package line. </li>"
                        . "<li>Also see: <a href='https://duplicator.com/knowledge-base/how-to-fix-general-installer-ui-bootstrap-archive-issues' target='_blank'>"
                        . "How to fix various errors that show up before step-1 of the installer?</a></li>"
                        . "</ul>";
                    return $error;
                }
                $archive_size = self::ARCHIVE_SIZE;
                if (!empty($archive_size) && !self::checkInputValidInt(self::ARCHIVE_SIZE)) {
                    $no_of_bits = PHP_INT_SIZE * 8;
                    $error      = 'Current is a ' . $no_of_bits . '-bit SO. This archive is too large for ' . $no_of_bits . '-bit PHP.' . '<br>';
                    $this->log('[ERROR] ' . $error);
                    $error      .= 'Possibibles solutions:<br>';
                    $error      .= '- Use the file filters to get your package lower to support this server or try the package on a Linux server.' . '<br>';
                    $error      .= '- Perform a <a target="_blank" href="https://duplicator.com/knowledge-base/how-to-handle-various-install-scenarios">' .
                        'Manual Extract Install</a>' . '<br>';
                    switch ($no_of_bits == 32) {
                        case 32:
                            $error .= '- Ask your host to upgrade the server to 64-bit PHP or install on another system has 64-bit PHP' . '<br>';
                            break;
                        case 64:
                            $error .= '- Ask your host to upgrade the server to 128-bit PHP or install on another system has 128-bit PHP' . '<br>';
                            break;
                    }
                    if (self::isWindows()) {
                        $error .= '- <a target="_blank" href="https://duplicator.com/knowledge-base/how-to-work-with-daf-files-and-the-duparchive-extraction-tool">' .
                            'Windows DupArchive extractor</a> to extract all files from the archive.' . '<br>';
                    }
                    return $error;
                }
                if (($this->archiveRatio < 90) && ($this->archiveActualSize > 0) && ($this->archiveExpectedSize > 0)) {
                    $this->log(
                        "ERROR: The expected archive size should be around [{$archiveExpectedEasy}]. " .
                        "The actual size is currently [{$archiveActualEasy}]."
                    );
                    $this->log("ERROR: The archive file may not have fully been downloaded to the server");
                    $percent = round($this->archiveRatio);
                    $autochecked = isset($_POST['auto-fresh']) ? "checked='true'" : '';
                    $error       = "<b>Archive file size warning.</b><br/> The expected archive size is <b class='pass'>[{$archiveExpectedEasy}]</b>. "
                        . "Currently the archive size is <b class='fail'>[{$archiveActualEasy}]</b>. <br/>"
                        . "The archive file may have <b>not fully been uploaded to the server.</b>"
                        . "<ul>"
                        . "<li>Download the whole archive from the source website (open WordPress Admin &gt; Duplicator &gt; Packages) "
                        . "and validate that the file size is close to the expected size. </li>"
                        . "<li>Make sure to upload the whole archive file to the destination server.</li>"
                        . "<li>If the archive file is still uploading then please refresh this page to get an update on the currently uploaded file size.</li>"
                        . "</ul>";
                    return $error;
                }
            }
            if ($installer_dir_found) {
                if (($extract_installer = filter_input(INPUT_GET, 'force-extract-installer', FILTER_VALIDATE_BOOLEAN))) {
                    $this->log("Manual extract found with force extract installer get parametr");
                } else {
                    $this->log("Manual extract found so not going to extract " . $this->targetDupInstFolder . " dir");
                }
            } else {
                $extract_installer = true;
            }
            if (file_exists($this->targetDupInst)) {
                $this->log("EXTRACT " . $this->targetDupInstFolder . " dir");
                $hash_pattern                 = '[a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9][a-z0-9]-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]';
                $file_patterns_with_hash_file = array(
                    'dup-archive__' . $hash_pattern . '.txt'        => 'dup-archive__' . self::PACKAGE_HASH . '.txt',
                    'dup-database__' . $hash_pattern . '.sql'       => 'dup-database__' . self::PACKAGE_HASH . '.sql',
                    'dup-installer-data__' . $hash_pattern . '.sql' => 'dup-installer-data__' . self::PACKAGE_HASH . '.sql',
                    'dup-installer-log__' . $hash_pattern . '.txt'  => 'dup-installer-log__' . self::PACKAGE_HASH . '.txt',
                    'dup-scan__' . $hash_pattern . '.json'          => 'dup-scan__' . self::PACKAGE_HASH . '.json',
                    'dup-scanned-dirs__' . $hash_pattern . '.txt'   => 'dup-scanned-dirs__' . self::PACKAGE_HASH . '.txt',
                    'dup-scanned-files__' . $hash_pattern . '.txt'  => 'dup-scanned-files__' . self::PACKAGE_HASH . '.txt',
                );
                foreach ($file_patterns_with_hash_file as $file_pattern => $hash_file) {
                    $globs = glob($this->targetDupInst . '/' . $file_pattern);
                    if (!empty($globs)) {
                        foreach ($globs as $glob) {
                            $file = basename($glob);
                            if ($file != $hash_file) {
                                if (unlink($glob)) {
                                    $this->log('Successfully deleted the file ' . $glob);
                                } else {
                                    $error .= '[ERROR] Error deleting the file ' . $glob . ' Please manually delete it and try again.';
                                    $this->log($error);
                                }
                            }
                        }
                    }
                }
            }
            if ($extract_installer) {
                $this->log("Ready to extract the installer");
                $this->log("Checking permission of destination folder");
                $destination = $this->targetRoot;
                if (!is_writable($destination)) {
                    $this->log("destination folder for extraction is not writable");
                    if (self::chmod($destination, 'u+rwx')) {
                        $this->log("Permission of destination folder changed to u+rwx");
                    } else {
                        $this->log("[ERROR] Permission of destination folder failed to change to u+rwx");
                    }
                }
                if (!is_writable($destination)) {
                    $this->log("WARNING: The {$destination} directory is not writable.");
                    $error = "NOTICE: The {$destination} directory is not writable on this server please talk to your host or server admin about making ";
                    $error .= "<a target='_blank' href='https://duplicator.com/knowledge-base/how-to-fix-file-permissions-issues'>" .
                        "writable {$destination} directory</a> on this server. <br/>";
                    return $error;
                }
                if ($isZip) {
                    $zip_mode = $this->getZipMode();
                    if (($zip_mode == self::ZIP_MODE_AUTO) || ($zip_mode == self::ZIP_MODE_ARCHIVE) && class_exists('ZipArchive')) {
                        if ($this->hasZipArchive) {
                            $this->log("ZipArchive exists so using that");
                            $extract_success = $this->extractInstallerZipArchive($archive_filepath, $this->origDupInstFolder, $this->extractionTmpFolder);
                            if ($extract_success) {
                                $this->log('Successfully extracted with ZipArchive');
                            } else {
                                if (0 == $this->installer_files_found) {
                                    $error = "[ERROR] This archive is not properly formatted and does not contain a " . $this->origDupInstFolder .
                                        " directory. Please make sure you are attempting to install " .
                                        "the original archive and not one that has been reconstructed.";
                                    $this->log($error);
                                    return $error;
                                } else {
                                    $error = '[ERROR] Error extracting with ZipArchive. ';
                                    $this->log($error);
                                }
                            }
                        } else {
                            $this->log("WARNING: ZipArchive is not enabled.");
                            $error = "NOTICE: ZipArchive is not enabled on this server please talk to your host or server admin about enabling ";
                            $error .= "<a target='_blank' href='https://duplicator.com/knowledge-base/how-to-work-with-the-different-zip-engines'>" .
                                "ZipArchive</a> on this server. <br/>";
                        }
                    }
                    if (!$extract_success) {
                        if (($zip_mode == self::ZIP_MODE_AUTO) || ($zip_mode == self::ZIP_MODE_SHELL)) {
                            $unzip_filepath = $this->getUnzipFilePath();
                            if ($unzip_filepath != null) {
                                $extract_success = $this->extractInstallerShellexec($archive_filepath, $this->origDupInstFolder, $this->extractionTmpFolder);
                                $this->log("Resetting perms of items in folder {$this->targetDupInstFolder}");
                                self::setPermsToDefaultR($this->targetDupInstFolder);
                                if ($extract_success) {
                                    $this->log('Successfully extracted with Shell Exec');
                                    $error = null;
                                } else {
                                    $error .= '[ERROR] Error extracting with Shell Exec. ' .
                                        'Please manually extract archive then choose Advanced > Manual Extract in installer.';
                                    $this->log($error);
                                }
                            } else {
                                $this->log('WARNING: Shell Exec Zip is not available');
                                $error .= "NOTICE: Shell Exec is not enabled on this server please talk to your host or server admin about enabling ";
                                $error .= "<a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> " .
                                    "on this server or manually extract archive then choose Advanced > Manual Extract in installer.";
                            }
                        }
                    }
                    if (!$extract_success && $zip_mode == self::ZIP_MODE_AUTO) {
                        $unzip_filepath = $this->getUnzipFilePath();
                        if (!class_exists('ZipArchive') && empty($unzip_filepath)) {
                            $this->log("WARNING: ZipArchive and Shell Exec are not enabled on this server.");
                            $error = "NOTICE: ZipArchive and Shell Exec are not enabled on this server please " .
                                "talk to your host or server admin about enabling ";
                            $error .= "<a target='_blank' href='https://duplicator.com/knowledge-base/how-to-work-with-the-different-zip-engines'>ZipArchive</a> " .
                                "or <a target='_blank' href='http://php.net/manual/en/function.shell-exec.php'>Shell Exec</a> " .
                                "on this server or manually extract archive then choose Advanced > Manual Extract in installer.";
                        }
                    }
                } else {
                    try {
                        DupArchiveExpandBasicEngine::setCallbacks(
                            array($this, 'log'),
                            array($this, 'chmod'),
                            array($this, 'mkdir')
                        );
                        $offset = DupArchiveExpandBasicEngine::getExtraOffset($archive_filepath);
                        $this->log('Expand directory from offset ' . $offset);
                        DupArchiveExpandBasicEngine::expandDirectory(
                            $archive_filepath,
                            $this->origDupInstFolder,
                            $this->extractionTmpFolder,
                            false,
                            $offset
                        );
                        @unlink($this->extractionTmpFolder . "/" . $this->origDupInstFolder . "/" . $this->manualExtractFileName);
                    } catch (Exception $ex) {
                        $this->log("[ERROR] Error expanding installer subdirectory:" . $ex->getMessage());
                        throw $ex;
                    }
                }
                if ($this->isCustomDupFolder) {
                    $this->log("Move dup-installer folder to custom folder:" .  $this->targetDupInst);
                    if (file_exists($this->targetDupInst)) {
                        $this->log('Custom folder already exists so delete it');
                        if (self::rrmdir($this->targetDupInst) == false) {
                            throw new Exception('Can\'t remove custom target folder');
                        }
                    }
                    if (rename($this->extractionTmpFolder . '/' . $this->origDupInstFolder, $this->targetDupInst) === false) {
                        throw new Exception('Can\'t rename the tmp dup-installer folder');
                    }
                }
                $htaccessToRemove = $this->targetDupInst.'/.htaccess';
                if (is_file($htaccessToRemove) && is_writable($htaccessToRemove)) {
                    $this->log("Remove Htaccess in dup-installer folder");
                    @unlink($htaccessToRemove);
                }
                $is_apache = (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false || strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false);
                $is_nginx  = (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false);
                $sapi_type                   = php_sapi_name();
                $php_ini_data                = array(
                    'max_execution_time'     => 3600,
                    'max_input_time'         => -1,
                    'ignore_user_abort'      => 'On',
                    'post_max_size'          => '4096M',
                    'upload_max_filesize'    => '4096M',
                    'memory_limit'           => DUPLICATOR_PHP_MAX_MEMORY,
                    'default_socket_timeout' => 3600,
                    'pcre.backtrack_limit'   => 99999999999,
                );
                $sapi_type_first_three_chars = substr($sapi_type, 0, 3);
                if ('fpm' === $sapi_type_first_three_chars) {
                    $this->log("SAPI: FPM");
                    if ($is_apache) {
                        $this->log('Server: Apache');
                    } elseif ($is_nginx) {
                        $this->log('Server: Nginx');
                    }
                    if (($is_apache && function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) || $is_nginx) {
                        $htaccess_data = array();
                        foreach ($php_ini_data as $php_ini_key => $php_ini_val) {
                            if ($is_apache) {
                                $htaccess_data[] = 'SetEnv PHP_VALUE "' . $php_ini_key . ' = ' . $php_ini_val . '"';
                            } elseif ($is_nginx) {
                                if ('On' == $php_ini_val || 'Off' == $php_ini_val) {
                                    $htaccess_data[] = 'php_flag ' . $php_ini_key . ' ' . $php_ini_val;
                                } else {
                                    $htaccess_data[] = 'php_value ' . $php_ini_key . ' ' . $php_ini_val;
                                }
                            }
                        }
                        $htaccess_text      = implode("\n", $htaccess_data);
                        $htaccess_file_path = $this->targetDupInst . '/.htaccess';
                        $this->log("creating {$htaccess_file_path} with the content:");
                        $this->log($htaccess_text);
                        @file_put_contents($htaccess_file_path, $htaccess_text);
                    }
                } elseif ('cgi' === $sapi_type_first_three_chars || 'litespeed' === $sapi_type) {
                    if ('cgi' === $sapi_type_first_three_chars) {
                        $this->log("SAPI: CGI");
                    } else {
                        $this->log("SAPI: litespeed");
                    }
                    if (version_compare(phpversion(), 5.5) >= 0 && (!$is_apache || 'litespeed' === $sapi_type)) {
                        $ini_data = array();
                        foreach ($php_ini_data as $php_ini_key => $php_ini_val) {
                            $ini_data[] = $php_ini_key . ' = ' . $php_ini_val;
                        }
                        $ini_text      = implode("\n", $ini_data);
                        $ini_file_path = $this->targetDupInst . '/.user.ini';
                        $this->log("creating {$ini_file_path} with the content:");
                        $this->log($ini_text);
                        @file_put_contents($ini_file_path, $ini_text);
                    } else {
                        $this->log("No need to create " . $this->targetDupInstFolder . "/.htaccess or " . $this->targetDupInstFolder . "/.user.ini");
                    }
                } elseif ("apache2handler" === $sapi_type) {
                    $this->log("No need to create " . $this->targetDupInstFolder . "/.htaccess or " . $this->targetDupInstFolder . "/.user.ini");
                    $this->log("SAPI: apache2handler");
                }
                else {
                    $this->log("No need to create " . $this->targetDupInstFolder . "/.htaccess or " . $this->targetDupInstFolder . "/.user.ini");
                    $this->log("ERROR:  SAPI: Unrecognized");
                }
            } else {
                $this->log("NOTICE: Didn't need to extract the installer.");
            }
            if (empty($error)) {
                if ($this->isCustomDupFolder && file_exists($this->extractionTmpFolder)) {
                    rmdir($this->extractionTmpFolder);
                }
                $config_files              = glob($this->targetDupInst . '/dup-archive__*.txt');
                $config_file_absolute_path = array_pop($config_files);
                if (!file_exists($config_file_absolute_path)) {
                    $error = '<b>Archive config file not found in ' . $this->targetDupInstFolder . ' folder.</b> <br><br>';
                    return $error;
                }
            }
            $uri_start   = self::getCurrentUrl(false, false, 1);
            if ($error === null) {
                if (!file_exists($this->targetDupInst)) {
                    $error = 'Can\'t extract installer directory. ' .
                        'See <a target="_blank" href="https://duplicator.com/knowledge-base/how-to-fix-installer-archive-extraction-issues/">this FAQ item</a>' .
                        ' for details on how to resolve.</a>';
                }
                if ($error == null) {
                    $bootloader_name        = basename(__FILE__);
                    $this->mainInstallerURL = $uri_start . '/' . $this->targetDupInstFolder . '/main.installer.php';
                    $this->archive    = $archive_filepath;
                    $this->bootloader = $bootloader_name;
                    $this->fixInstallerPerms($this->mainInstallerURL);
                    $this->log("DONE: No detected errors so redirecting to the main installer. Main Installer URI = {$this->mainInstallerURL}");
                }
            }
            return $error;
        }
        public static function getCurrentUrl($queryString = true, $requestUri = false, $getParentDirLevel = 0)
        {
            if (isset($_SERVER['HTTP_X_ORIGINAL_HOST'])) {
                $host = $_SERVER['HTTP_X_ORIGINAL_HOST'];
            } else {
                $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']; //WAS SERVER_NAME and caused problems on some boxes
            }
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $_SERVER ['HTTPS'] = 'on';
            }
            if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'https') {
                $_SERVER ['HTTPS'] = 'on';
            }
            if (isset($_SERVER['HTTP_CF_VISITOR'])) {
                $visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
                if ($visitor->scheme == 'https') {
                    $_SERVER ['HTTPS'] = 'on';
                }
            }
            $protocol = 'http' . ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ? 's' : '');
            if ($requestUri) {
                $serverUrlSelf = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
            } else {
                $serverUrlSelf = $_SERVER['SCRIPT_NAME'];
                for ($i = 0; $i < $getParentDirLevel; $i++) {
                    $serverUrlSelf = preg_match('/^[\\\\\/]?$/', dirname($serverUrlSelf)) ? '' : dirname($serverUrlSelf);
                }
            }
            $query = ($queryString && isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0 ) ? '?' . $_SERVER['QUERY_STRING'] : '';
            return $protocol . '://' . $host . $serverUrlSelf . $query;
        }
        private function fixInstallerPerms()
        {
            $file_perms = 'u+rw';
            $dir_perms  = 'u+rwx';
            $installer_dir_path = $this->targetDupInstFolder;
            $this->setPerms($installer_dir_path, $dir_perms, false);
            $this->setPerms($installer_dir_path, $file_perms, true);
        }
        private function setPerms($directory, $perms, $do_files)
        {
            if (!$do_files) {
                $this->setPermsOnItem($directory, $perms);
            }
            $item_names = array_diff(scandir($directory), array('.', '..'));
            foreach ($item_names as $item_name) {
                $path = "$directory/$item_name";
                if (($do_files && is_file($path)) || (!$do_files && !is_file($path))) {
                    $this->setPermsOnItem($path, $perms);
                }
            }
        }
        private function setPermsOnItem($path, $perms)
        {
            if (($result = self::chmod($path, $perms)) === false) {
                $this->log("ERROR: Couldn't set permissions of $path<br/>");
            } else {
                $this->log("Set permissions of $path<br/>");
            }
            return $result;
        }
        private function compareStrings($oldString, $newString)
        {
            $ret = '';
            for ($i = 0; isset($oldString[$i]) || isset($newString[$i]); $i++) {
                if (!isset($oldString[$i])) {
                    $ret .= '<font color="red">' . $newString[$i] . '</font>';
                    continue;
                }
                for ($char = 0; isset($oldString[$i][$char]) || isset($newString[$i][$char]); $char++) {
                    if (!isset($oldString[$i][$char])) {
                        $ret .= '<font color="red">' . substr($newString[$i], $char) . '</font>';
                        break;
                    } elseif (!isset($newString[$i][$char])) {
                        break;
                    }
                    if (ord($oldString[$i][$char]) != ord($newString[$i][$char])) {
                        $ret .= '<font color="red">' . $newString[$i][$char] . '</font>';
                    } else {
                        $ret .= $newString[$i][$char];
                    }
                }
            }
            return $ret;
        }
        public function log($s, $deleteOld = false)
        {
            static $logfile = null;
            if (is_null($logfile)) {
                $logfile = $this->getBootLogFilePath();
            }
            if ($deleteOld && file_exists($logfile)) {
                @unlink($logfile);
            }
            $timestamp = date('M j H:i:s');
            return @file_put_contents($logfile, '[' . $timestamp . '] ' . self::postprocessLog($s) . "\n", FILE_APPEND);
        }
        public function getBootLogFilePath()
        {
            return $this->targetRoot . '/dup-installer-bootlog__' . self::SECONDARY_PACKAGE_HASH . '.txt';
        }
        protected static function postprocessLog($str)
        {
            return str_replace(array(
                self::getArchiveFileHash(),
                self::PACKAGE_HASH,
                self::SECONDARY_PACKAGE_HASH
                ), '[HASH]', $str);
        }
        
        


        
        
        
        


        

