<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (! defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Addressbook_AllTests::main');
}

class Addressbook_AllTests
{
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Addressbook All Tests');
        $suite->addTest(Addressbook_Backend_AllTests::suite());
        $suite->addTest(Addressbook_Import_AllTests::suite());
        $suite->addTest(Addressbook_Frontend_AllTests::suite());
        $suite->addTestSuite('Addressbook_ControllerTest');
        $suite->addTestSuite('Addressbook_Controller_ListTest');
        $suite->addTestSuite('Addressbook_Convert_Contact_VCard_AllTests');
        $suite->addTestSuite('Addressbook_PdfTest');
        $suite->addTestSuite('Addressbook_JsonTest');
        $suite->addTestSuite('Addressbook_CliTest');
        $suite->addTestSuite('Addressbook_Model_ContactIdFilterTest');
        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Addressbook_AllTests::main') {
    Addressbook_AllTests::main();
}
