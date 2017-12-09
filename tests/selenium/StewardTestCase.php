<?php

namespace My;

define('BASE_URL', 'http://webtrees-git.localhost');

use Lmc\Steward\Test\AbstractTestCase;

// Setup required defines for webtrees
define('WT_WEBTREES', 'webtrees');
define('WT_ROOT', realpath(dirname(__DIR__) . "/../../..") . DIRECTORY_SEPARATOR);
define('WT_MODULES_DIR', 'modules_v3/');
define('WT_DEBUG_SQL', true);
define('WT_CLIENT_IP', '127.0.0.1');

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Module;

class StewardTestCase extends AbstractTestCase {

    public static $module;

    public static function setUpBeforeClass() {
        $dbconfig = parse_ini_file('../../data/config.ini.php');
        Database::createInstance($dbconfig['dbhost'], $dbconfig['dbport'], $dbconfig['dbname'], $dbconfig['dbuser'], $dbconfig['dbpass']);
        define('WT_TBLPREFIX', $dbconfig['tblpfx']);
        unset($dbconfig);

        self::$module = Module::getModuleByName('facebook');
    }

    public function tearDown() {
        echo Database::getQueryLog();
    }
}
