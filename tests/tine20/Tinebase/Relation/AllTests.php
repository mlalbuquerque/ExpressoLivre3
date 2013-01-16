<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  Record
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (! defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Tinebase_Relation_AllTests::main');
}

class Tinebase_Relation_AllTests
{
    public static function main() 
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite() 
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Tinebase All Relation Tests');
        $suite->addTestSuite('Tinebase_Relation_Backend_SqlTest');
        $suite->addTestSuite('Tinebase_Relation_RelationTest');
        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Tinebase_Relation_AllTests::main') {
    Tinebase_Relation_AllTests::main();
}
