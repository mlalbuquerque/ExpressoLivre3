<?php
/**
 * Tine 2.0
 * 
 * @package     tests
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'TestHelper.php';

class AllTests
{
    public static function suite()
    {
        $suite = new SessionTestSuite('JSClient AllTests');
    
        $suite->addTestSuite('Tinebase_AllTests');
        
        return $suite;
    }
}
