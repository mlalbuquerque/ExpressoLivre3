<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (! defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Phone_Backend_Snom_AllTests::main');
}

class Phone_Backend_Snom_AllTests
{
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Phone All Snom Backend Tests');
        $suite->addTestSuite('Phone_Backend_Snom_CallhistoryTest');
        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Phone_Backend_Snom_AllTests::main') {
    Phone_Backend_Snom_AllTests::main();
}
