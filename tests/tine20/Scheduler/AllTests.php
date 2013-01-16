<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Scheduler
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Gökmen Ciyiltepe <g.ciyiltepe@metaways.de>
 * 
 * @todo        add controller tests
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (! defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Scheduler_AllTests::main');
}

class Scheduler_AllTests
{
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Scheduler All Tests');
        $suite->addTestSuite('Scheduler_SchedulerTest');
        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Scheduler_AllTests::main') {
    Scheduler_AllTests::main();
}
#EOF

