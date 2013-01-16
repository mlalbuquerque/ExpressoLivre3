<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     ActiveSync
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for FolderSync_Controller_Event
 * 
 * @package     ActiveSync
 */
class ActiveSync_Command_FolderSyncTests extends PHPUnit_Framework_TestCase
{
    /**
     * @var ActiveSync_Model_Device
     */
    protected $_device;
    
    /**
     * @var ActiveSync_Backend_Device
     */
    protected $_deviceBackend;
    
    /**
     * @var Syncope_Backend_Folder
     */
    protected $_folderBackend;

    /**
     * @var Syncope_Backend_SyncState
     */
    protected $_syncStateBackend;
    
    /**
     * @var Syncope_Backend_IContent
     */
    protected $_contentStateBackend;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 ActiveSync FolderSync Command Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }
    
    /**
     * (non-PHPdoc)
     * @see ActiveSync/ActiveSync_TestCase::setUp()
     */
    protected function setUp()
    {
        Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
        
        Syncope_Registry::setDatabase(Tinebase_Core::getDb());
        Syncope_Registry::setTransactionManager(Tinebase_TransactionManager::getInstance());
        
        $this->_deviceBackend       = new Syncope_Backend_Device(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_');
        $this->_folderBackend       = new Syncope_Backend_Folder(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_');
        $this->_syncStateBackend    = new Syncope_Backend_SyncState(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_');
        $this->_contentStateBackend = new Syncope_Backend_Content(Tinebase_Core::getDb(), SQL_TABLE_PREFIX . 'acsync_');
        
        $this->_device = $this->_deviceBackend->create(
            ActiveSync_Backend_DeviceTests::getTestDevice()
        );
        
        Syncope_Registry::set('deviceBackend',       $this->_deviceBackend);
        Syncope_Registry::set('folderStateBackend',  $this->_folderBackend);
        Syncope_Registry::set('syncStateBackend',    $this->_syncStateBackend);
        Syncope_Registry::set('contentStateBackend', $this->_contentStateBackend);
        Syncope_Registry::set('loggerBackend',       Tinebase_Core::getLogger());  

        Syncope_Registry::setContactsDataClass('ActiveSync_Controller_Contacts');
        Syncope_Registry::setCalendarDataClass('ActiveSync_Controller_Calendar');
        Syncope_Registry::setEmailDataClass('ActiveSync_Controller_Email');
        Syncope_Registry::setTasksDataClass('ActiveSync_Controller_Tasks');
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        Tinebase_TransactionManager::getInstance()->rollBack();
    }
    
    /**
     * test xml generation for IPhone
     */
    public function testGetFoldersSyncKey0()
    {
        $doc = new DOMDocument();
        $doc->loadXML('<?xml version="1.0" encoding="utf-8"?>
            <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
            <FolderSync xmlns="uri:FolderHierarchy"><SyncKey>0</SyncKey></FolderSync>'
        );
        
        $folderSync = new Syncope_Command_FolderSync($doc, $this->_device, $this->_device->policykey);
        
        $folderSync->handle();
        
        $responseDoc = $folderSync->getResponse();
        #$responseDoc->formatOutput = true; echo $responseDoc->saveXML();
        
        $xpath = new DomXPath($responseDoc);
        $xpath->registerNamespace('FolderHierarchy', 'uri:FolderHierarchy');
        
        $nodes = $xpath->query('//FolderHierarchy:FolderSync/FolderHierarchy:Status');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertEquals(Syncope_Command_FolderSync::STATUS_SUCCESS, $nodes->item(0)->nodeValue, $responseDoc->saveXML());
        
        $nodes = $xpath->query('//FolderHierarchy:FolderSync/FolderHierarchy:SyncKey');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertEquals(1, $nodes->item(0)->nodeValue, $responseDoc->saveXML());

        $nodes = $xpath->query('//FolderHierarchy:FolderSync/FolderHierarchy:Changes/FolderHierarchy:Add');
        $this->assertGreaterThanOrEqual(1, $nodes->length, $responseDoc->saveXML());
    }
}
