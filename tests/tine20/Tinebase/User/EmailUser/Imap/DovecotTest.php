<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  User
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Tinebase_User_EmailUser_Imap_DovecotTest::main');
}

/**
 * Test class for Tinebase_DovecotTest
 */
class Tinebase_User_EmailUser_Imap_DovecotTest extends PHPUnit_Framework_TestCase
{
    /**
     * email user backend
     *
     * @var Tinebase_User_Plugin_Abstract
     */
    protected $_backend = NULL;
        
    /**
     * @var array test objects
     */
    protected $_objects = array();
    
    protected $_config;

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tinebase_User_EmailUser_Imap_DovecotTest');
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
        $this->_config = Tinebase_Config::getInstance()->getConfigAsArray(Tinebase_Config::IMAP);
        if (!isset($this->_config['backend']) || !(ucfirst($this->_config['backend']) == Tinebase_EmailUser::DOVECOT_IMAP) || $this->_config['active'] != true) {
            $this->markTestSkipped('Dovecot MySQL backend not configured or not enabled');
        }
        
        $this->_backend = Tinebase_EmailUser::getInstance(Tinebase_Config::IMAP);
        
        $personas = Zend_Registry::get('personas');
        $this->_objects['user'] = clone $personas['jsmith'];

        $this->_objects['addedUsers'] = array();
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        // delete email account
        foreach ($this->_objects['addedUsers'] as $user) {
            $this->_backend->inspectDeleteUser($user);
        }
    }
    
    /**
     * try to add an email account
     */
    public function testAddEmailAccount()
    {
        $emailUser = clone $this->_objects['user'];
        $emailUser->imapUser = new Tinebase_Model_EmailUser(array(
            'emailPassword' => Tinebase_Record_Abstract::generateUID(),
            'emailUID'      => '1000',
            'emailGID'      => '1000'
        ));
        
        $this->_backend->inspectAddUser($this->_objects['user'], $emailUser);
        $this->_objects['addedUsers']['emailUser'] = $this->_objects['user'];
        
        //var_dump($this->_objects['user']->imapUser->toArray());
        
        $this->assertEquals(array(
            'emailUserId'     => $this->_objects['user']->getId(),
            'emailUsername'   => $this->_objects['user']->imapUser->emailUsername,
            'emailMailQuota'  => null,
        	'emailUID'        => !empty($this->_config['dovecot']['uid']) ? $this->_config['dovecot']['uid'] : '1000',
            'emailGID'        => !empty($this->_config['dovecot']['gid']) ? $this->_config['dovecot']['gid'] : '1000',
            'emailLastLogin'  => null,
            'emailMailSize'   => 0,
            #'emailSieveQuota' => 0,
            'emailSieveSize'  => null
        ), $this->_objects['user']->imapUser->toArray());
        
        return $this->_objects['user'];
    }
    
    /**
     * try to update an email account
     */
    public function testUpdateAccount()
    {
        // add smtp user
        $user = $this->testAddEmailAccount();
        
        // update user
        $user->imapUser->emailMailQuota = 600;
        
        $this->_backend->inspectUpdateUser($this->_objects['user'], $user);
        
        //print_r($user->toArray());
        
        $this->assertEquals(array(
            'emailUserId'      => $this->_objects['user']->getId(),
            'emailUsername'    => $this->_objects['user']->imapUser->emailUsername,
            'emailMailQuota'   => 600,
        	'emailUID'         => !empty($this->_config['dovecot']['uid']) ? $this->_config['dovecot']['uid'] : '1000',
            'emailGID'         => !empty($this->_config['dovecot']['gid']) ? $this->_config['dovecot']['gid'] : '1000',
            'emailLastLogin'   => null,
            'emailMailSize'    => 0,
            #'emailSieveQuota'  => 0,
            'emailSieveSize'   => null
        ), $this->_objects['user']->imapUser->toArray());
    }
    
    /**
     * try to update an email account
     */
    public function testSetPassword()
    {
        // add smtp user
        $user = $this->testAddEmailAccount();
        
        $this->_backend->inspectSetPassword($this->_objects['user']->getId(), Tinebase_Record_Abstract::generateUID());
        
        //$this->assertEquals(md5('password'), $updatedUser->emailPassword);
    }
}	
