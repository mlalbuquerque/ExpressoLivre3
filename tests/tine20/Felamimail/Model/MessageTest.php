<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2010-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Felamimail_Model_MessageTest
 */
class Felamimail_Model_MessageTest extends PHPUnit_Framework_TestCase
{
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
		$suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Felamimail Message Model Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
	}

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    }

    /********************************* test funcs *************************************/
    
    /**
     * test conversion to plain text (blockquotes to quotes) / tests linebreaks, too
     */
    public function testGetPlainTextBody()
    {
        $message = new Felamimail_Model_Message(array(
            'body'  =>  'blabla<br/><blockquote class="felamimail-body-blockquote">lalülüüla<br/><br/><div>lala</div><br/><blockquote class="felamimail-body-blockquote">xyz</blockquote></blockquote><br/><br/>jojo<br/>' .
                        'lkjlhk<div><br></div><div><br><div>jjlöjlö</div><div><font face="arial"><br></font></div></div><div><font face="arial">Pickhuben 2-4, 20457 Hamburg</font></div>'
        ));
        
        $result = $message->getPlainTextBody();
        //echo $result;
        
        $this->assertEquals("blabla\n" .
            "> lalülüüla\n" .
            "> \n" .
            "> lala\n" .
            "> \n" . 
            "> > xyz\n" .
            "\n\n" .
            "jojo\n" .
            "lkjlhk\n\n\n" .
            "jjlöjlö\n\n" .
            "Pickhuben 2-4, 20457 Hamburg\n", $result);
    }
}
