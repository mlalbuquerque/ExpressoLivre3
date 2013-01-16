<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2011-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * Test class for Syncope_Command_Sync
 * 
 * @package     Backend
 */
class Syncope_Backend_SyncStateTests extends PHPUnit_Framework_TestCase
{
    /**
     * @var Syncope_Model_Device
     */
    protected $_device;
    
    /**
     * @var Syncope_Model_Folder
     */
    protected $_folder;
    
    /**
     * @var Syncope_Backend_Device
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
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Syncope SyncState backend tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }
    
    /**
     * (non-PHPdoc)
     * @see ActiveSync/ActiveSync_TestCase::setUp()
     */
    protected function setUp()
    {
        $this->_db = getTestDatabase();
        
        $this->_db->beginTransaction();

        $this->_deviceBackend     = new Syncope_Backend_Device($this->_db);
        $this->_folderBackend     = new Syncope_Backend_Folder($this->_db);
        $this->_syncStateBackend  = new Syncope_Backend_SyncState($this->_db);

        $this->_device = $this->_deviceBackend->create(
            Syncope_Backend_DeviceTests::getTestDevice()
        );
        $this->_folder = $this->_folderBackend->create(
            Syncope_Backend_FolderTests::getTestFolder($this->_device)
        );
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        $this->_db->rollBack();
    }
    
    /**
     * @return Syncope_Model_ISyncState
     */
    public function testCreate()
    {
        $syncState = new Syncope_Model_SyncState(array(
            'device_id'   => $this->_device,
            'type'        => 'FolderSync',
            'counter'     => '0',
            'lastsync'    => new DateTime(null, new DateTimeZone('utc')),
            'pendingdata' => array('foobar' => 'test')
        ));
        
        $syncState = $this->_syncStateBackend->create($syncState);
        
        $this->assertTrue($syncState->lastsync instanceof DateTime);
        $this->assertArrayHasKey('foobar', $syncState->pendingdata);
        
        return $syncState;
    }
    
    /**
     * @return Syncope_Model_ISyncState
     */
    public function testUpdate()
    {
        $syncState = $this->testCreate();
        
        $syncState->counter++;
    
        $syncState = $this->_syncStateBackend->update($syncState);
    
        $this->assertEquals(1, $syncState->counter);
        $this->assertTrue($syncState->lastsync instanceof DateTime);
        $this->assertArrayHasKey('foobar', $syncState->pendingdata);
        
        return $syncState;
    }
    
    /**
     * test validating synckey
     */
    public function testValidateSyncKey()
    {
        $syncState = $this->testUpdate();
        
        $validatedSyncState =  $this->_syncStateBackend->validate($this->_device, 'FolderSync', 1);
        
        $this->assertTrue($validatedSyncState instanceof Syncope_Model_ISyncState);
        $this->assertEquals(1, $validatedSyncState->counter);
        $this->assertTrue($validatedSyncState->lastsync instanceof DateTime);
        
        
        // invalid synckey must return false
        $validatedSyncState =  $this->_syncStateBackend->validate($this->_device, 'FolderSync', 2);
        
        $this->assertFalse($validatedSyncState);
    }
        
    /**
     * test if the previous synckey gets deleted after validating the lastest synckey
     */
    public function testDeletePreviousSynckeyAfterValidate()
    {
        $syncState = new Syncope_Model_SyncState(array(
            'device_id'   => $this->_device,
            'type'        => 'FolderSync',
            'counter'     => '0',
            'lastsync'    => new DateTime(null, new DateTimeZone('utc')),
            'pendingdata' => null
        ));
        $syncState->lastsync->modify('-2 min');
        $syncState = $this->_syncStateBackend->create($syncState);
        
        $syncState = new Syncope_Model_SyncState(array(
            'device_id'   => $this->_device,
            'type'        => 'FolderSync',
            'counter'     => '1',
            'lastsync'    => new DateTime(null, new DateTimeZone('utc')),
            'pendingdata' => null
        ));
        
        $syncState = $this->_syncStateBackend->create($syncState);
    
        $syncState = $this->_syncStateBackend->validate($this->_device, 'FolderSync', '1');
    
        $this->assertEquals('FolderSync', $syncState->type);
        $this->assertEquals(1,            $syncState->counter);
        
        
        // the other synckey must be deleted now
        $syncState = $this->_syncStateBackend->validate($this->_device, 'FolderSync', '0');
    
        $this->assertFalse($syncState);
    }
    
    /**
     * test if the latest synckey gets deleted after validating the previous synckey
     */
    public function testDeleteLatestSynckeyAfterValidate()
    {
        $syncState = new Syncope_Model_SyncState(array(
            'device_id'   => $this->_device,
            'type'        => 'FolderSync',
            'counter'     => '0',
            'lastsync'    => new DateTime(null, new DateTimeZone('utc')),
            'pendingdata' => array()
        ));
        $syncState->lastsync->modify('-2 min');
        $syncState = $this->_syncStateBackend->create($syncState);
        
        $syncState = new Syncope_Model_SyncState(array(
            'device_id'   => $this->_device,
            'type'        => 'FolderSync',
            'counter'     => '1',
            'lastsync'    => new DateTime(null, new DateTimeZone('utc')),
            'pendingdata' => array()
        ));
        
        $syncState = $this->_syncStateBackend->create($syncState);
    
        $syncState = $this->_syncStateBackend->validate($this->_device, 'FolderSync', '0');
    
        $this->assertEquals('FolderSync', $syncState->type);
        $this->assertEquals(0,            $syncState->counter);
        
        
        // the other synckey must be deleted now
        $syncState = $this->_syncStateBackend->validate($this->_device, 'FolderSync', '1');
    
        $this->assertFalse($syncState);
    }
    
    public function testGetExceptionNotFound()
    {
        $this->setExpectedException('Syncope_Exception_NotFound');
        
        $this->_syncStateBackend->get('invalidId');
    }
    
    public function testGetSyncStateExceptionNotFound()
    {
        $this->setExpectedException('Syncope_Exception_NotFound');
        
        $this->_syncStateBackend->getSyncState('invalidId', 'invalidId');
    }
}
