<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  Acl
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';
if (! defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Tinebase_Acl_AllTests::main');
}
class Tinebase_Acl_AllTests
{
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Tinebase All Acl Tests');
        $suite->addTestSuite('Tinebase_Acl_RolesTest');
        $suite->addTestSuite('Tinebase_Acl_RightsTest');
        return $suite;
    }
}
if (PHPUnit_MAIN_METHOD == 'Tinebase_Acl_AllTests::main') {
    Tinebase_Acl_AllTests::main();
}
