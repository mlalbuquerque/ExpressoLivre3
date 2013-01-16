<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Scheduler
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Gökmen Ciyiltepe <g.ciyiltepe@metaways.de>
 * 
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Tinebase_AllTests
 *
 * @package     Tinebase
 */
class Tinebase_Scheduler_AllTests
{
    /**
     * main
     */
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    /**
     * suite
     */
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Scheduler All Tests');
        $suite->addTestSuite('Tinebase_Scheduler_SchedulerTest');
        return $suite;
    }
}
