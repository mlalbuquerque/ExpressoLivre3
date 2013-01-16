<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Projects
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (! defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Projects_AllTests::main');
}

class Projects_AllTests
{
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Projects All Tests');
        $suite->addTestSuite('Projects_JsonTest');
        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Projects_AllTests::main') {
    Projects_AllTests::main();
}
