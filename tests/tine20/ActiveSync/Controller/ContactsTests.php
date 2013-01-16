<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     ActiveSync
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Calendar_Controller_Event
 * 
 * @package     Calendar
 */
class ActiveSync_Controller_ContactsTests extends PHPUnit_Framework_TestCase
{
    /**
     * @var ActiveSync_Controller_Contacts controller
     */
    protected $_controller;
    
    /**
     * @var array test objects
     */
    protected $objects = array();
    
    protected $_exampleXMLNotExisting = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
<Sync xmlns="uri:AirSync" xmlns:Contacts="uri:Contacts"><Collections><Collection><Class>Contacts</Class><SyncKey>1</SyncKey><CollectionId>addressbook-root</CollectionId><DeletesAsMoves/><GetChanges/><WindowSize>50</WindowSize><Options><FilterType>0</FilterType><Truncation>2</Truncation><Conflict>0</Conflict></Options><Commands><Add><ClientId>1</ClientId><ApplicationData><Contacts:FileAs>ads2f, asdfadsf</Contacts:FileAs><Contacts:FirstName>asdf </Contacts:FirstName><Contacts:LastName>asdfasdfaasd </Contacts:LastName><Contacts:MobilePhoneNumber>+4312341234124</Contacts:MobilePhoneNumber><Contacts:Body>&#13;
</Contacts:Body></ApplicationData></Add></Commands></Collection></Collections></Sync>';
    
    protected $_exampleXMLExisting = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
<Sync xmlns="uri:AirSync" xmlns:Contacts="uri:Contacts"><Collections><Collection><Class>Contacts</Class><SyncKey>1</SyncKey><CollectionId>addressbook-root</CollectionId><DeletesAsMoves/><GetChanges/><WindowSize>50</WindowSize><Options><FilterType>0</FilterType><Truncation>2</Truncation><Conflict>0</Conflict></Options><Commands><Add><ClientId>1</ClientId><ApplicationData><Contacts:FileAs>Kneschke, Lars</Contacts:FileAs><Contacts:FirstName>Lars</Contacts:FirstName><Contacts:LastName>Kneschke</Contacts:LastName></ApplicationData></Add></Commands></Collection></Collections></Sync>';
    
    protected $_xmlContactBirthdayWithoutTimeAndroid = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
<Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Contacts="uri:Contacts"><Collections><Collection><Class>Contacts</Class><SyncKey>3</SyncKey><CollectionId>addressbook-root</CollectionId><DeletesAsMoves/><WindowSize>100</WindowSize><Options><AirSyncBase:BodyPreference><AirSyncBase:Type>1</AirSyncBase:Type><AirSyncBase:TruncationSize>5120</AirSyncBase:TruncationSize></AirSyncBase:BodyPreference><Conflict>1</Conflict></Options><Commands><Add><ClientId>4600</ClientId><ApplicationData><Contacts:FileAs>Fritzchen</Contacts:FileAs><Contacts:FirstName>Fritzchen</Contacts:FirstName><Contacts:LastName>Meinen</Contacts:LastName><Contacts:Birthday>1969-12-31</Contacts:Birthday><AirSyncBase:Body><AirSyncBase:Type>1</AirSyncBase:Type><AirSyncBase:EstimatedDataSize>0</AirSyncBase:EstimatedDataSize><AirSyncBase:Data></AirSyncBase:Data></AirSyncBase:Body><Contacts:Categories/><Contacts:Picture/></ApplicationData></Add></Commands></Collection></Collections></Sync>';
    
    protected $_setGeoData = TRUE;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 ActiveSync Controller Contacts Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }
    
    
    protected function setUp()
    {       
        $this->_setGeoData = Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts(FALSE);
        
        $appName = 'Addressbook';
        
        ############# TEST USER ##########
        $user = new Tinebase_Model_FullUser(array(
            'accountLoginName'      => 'tine20phpunit',
            'accountDisplayName'    => 'tine20phpunit',
            'accountStatus'         => 'enabled',
            'accountExpires'        => NULL,
            'accountPrimaryGroup'   => Tinebase_Group::getInstance()->getGroupByName('Users')->getId(),
            'accountLastName'       => 'Tine 2.0',
            'accountFirstName'      => 'PHPUnit',
            'accountEmailAddress'   => 'phpunit@metaways.de'
        ));
        
        try {
            $user = Tinebase_User::getInstance()->getUserByLoginName($user->accountLoginName);
        } catch (Tinebase_Exception_NotFound $e) {
            $user = Tinebase_User::getInstance()->addUser($user);
        }
        $this->objects['user'] = $user;
        
        
        ############# TEST CONTACT ##########
        try {
            $containerWithSyncGrant = Tinebase_Container::getInstance()->getContainerByName($appName, 'ContainerWithSyncGrant', Tinebase_Model_Container::TYPE_PERSONAL, Tinebase_Core::getUser());
        } catch (Tinebase_Exception_NotFound $e) {
            $containerWithSyncGrant = new Tinebase_Model_Container(array(
                'name'              => 'ContainerWithSyncGrant',
                'type'              => Tinebase_Model_Container::TYPE_PERSONAL,
                'owner_id'          => Tinebase_Core::getUser(),
                'backend'           => 'Sql',
                'application_id'    => Tinebase_Application::getInstance()->getApplicationByName($appName)->getId()
            ));
            $containerWithSyncGrant = Tinebase_Container::getInstance()->addContainer($containerWithSyncGrant);
        }
        $this->objects['containerWithSyncGrant'] = $containerWithSyncGrant;
        
        try {
            $containerWithoutSyncGrant = Tinebase_Container::getInstance()->getContainerByName($appName, 'ContainerWithoutSyncGrant', Tinebase_Model_Container::TYPE_PERSONAL, Tinebase_Core::getUser());
        } catch (Tinebase_Exception_NotFound $e) {
            $creatorGrants = array(
                'account_id'     => Tinebase_Core::getUser()->getId(),
                'account_type'   => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                Tinebase_Model_Grants::GRANT_READ      => true,
                Tinebase_Model_Grants::GRANT_ADD       => true,
                Tinebase_Model_Grants::GRANT_EDIT      => true,
                Tinebase_Model_Grants::GRANT_DELETE    => true,
                //Tinebase_Model_Grants::GRANT_EXPORT    => true,
                //Tinebase_Model_Grants::GRANT_SYNC      => true,
                Tinebase_Model_Grants::GRANT_ADMIN     => true,
            );            
            $grants = new Tinebase_Record_RecordSet('Tinebase_Model_Grants', array($creatorGrants));
            
            $containerWithoutSyncGrant = new Tinebase_Model_Container(array(
                'name'              => 'ContainerWithoutSyncGrant',
                'type'              => Tinebase_Model_Container::TYPE_PERSONAL,
                'owner_id'          => Tinebase_Core::getUser(),
                'backend'           => 'Sql',
                'application_id'    => Tinebase_Application::getInstance()->getApplicationByName($appName)->getId()
            ));
            $containerWithSyncGrant = Tinebase_Container::getInstance()->addContainer($containerWithoutSyncGrant, $grants);
        }
        $this->objects['containerWithoutSyncGrant'] = $containerWithoutSyncGrant;
        
        $contact = new Addressbook_Model_Contact(array(
            'adr_one_countryname'   => 'DE',
            'adr_one_locality'      => 'Hamburg',
            'adr_one_postalcode'    => '24xxx',
            'adr_one_region'        => 'Hamburg',
            'adr_one_street'        => 'Pickhuben 4',
            'adr_one_street2'       => 'no second street',
            'adr_two_countryname'   => 'DE',
            'adr_two_locality'      => 'Hamburg',
            'adr_two_postalcode'    => '24xxx',
            'adr_two_region'        => 'Hamburg',
            'adr_two_street'        => 'Pickhuben 4',
            'adr_two_street2'       => 'no second street2',
            'bday'                  => '1975-01-02 03:00:00', // new Tinebase_DateTime???
            'email'                 => 'unittests@tine20.org',
            'email_home'            => 'unittests@tine20.org',
//            'jpegphoto'             => file_get_contents(dirname(__FILE__) . '/../../Tinebase/ImageHelper/phpunit-logo.gif'),
            'container_id'          => $this->objects['containerWithSyncGrant']->id,
            'role'                  => 'Role',
            'n_given'               => 'Lars',
            'n_family'              => 'Kneschke',
            'n_fileas'              => 'Kneschke, Lars',
        )); 
        
        $contact = Addressbook_Controller_Contact::getInstance()->create($contact, FALSE);
        $this->objects['contact'] = $contact;
        
        $unSyncableContact = new Addressbook_Model_Contact(array(
            'adr_one_countryname'   => 'DE',
            'adr_one_locality'      => 'Hamburg',
            'adr_one_postalcode'    => '24xxx',
            'adr_one_region'        => 'Hamburg',
            'adr_one_street'        => 'Pickhuben 4',
            'adr_one_street2'       => 'no second street',
            'adr_two_countryname'   => 'DE',
            'adr_two_locality'      => 'Hamburg',
            'adr_two_postalcode'    => '24xxx',
            'adr_two_region'        => 'Hamburg',
            'adr_two_street'        => 'Pickhuben 4',
            'adr_two_street2'       => 'no second street2',
            'bday'                  => '1975-01-02 03:00:00', // new Tinebase_DateTime???
            'email'                 => 'unittests@tine20.org',
            'email_home'            => 'unittests@tine20.org',
//            'jpegphoto'             => file_get_contents(dirname(__FILE__) . '/../../Tinebase/ImageHelper/phpunit-logo.gif'),
            'container_id'          => $this->objects['containerWithoutSyncGrant']->id,
            'role'                  => 'Role',
            'n_given'               => 'Lars',
            'n_family'              => 'Kneschke',
            'n_fileas'              => 'Kneschke, Lars',
        )); 
        
        $unSyncableContact = Addressbook_Controller_Contact::getInstance()->create($unSyncableContact, FALSE);
        $this->objects['unSyncableContact'] = $unSyncableContact;

        
        ########### define test filter
        $filterBackend = new Tinebase_PersistentFilter_Backend_Sql();
        
        try {
            $filter = $filterBackend->getByProperty('Contacts Sync Test', 'name');
        } catch (Tinebase_Exception_NotFound $e) {
            $filter = new Tinebase_Model_PersistentFilter(array(
                'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
                'account_id'        => Tinebase_Core::getUser()->getId(),
                'model'             => 'Addressbook_Model_ContactFilter',
                'filters'           => array(array(
                    'field'     => 'container_id', 
                    'operator'  => 'equals', 
                    'value'     => $this->objects['containerWithSyncGrant']->getId()
                )),
                'name'              => 'Contacts Sync Test',
                'description'       => 'Created by unit test'
            ));
            
            $filter = $filterBackend->create($filter);
        }
        $this->objects['filter'] = $filter;
        
        
        ########### define test devices
        $palm = ActiveSync_Backend_DeviceTests::getTestDevice(Syncope_Model_Device::TYPE_WEBOS);
        $palm->contactsfilter_id = $this->objects['filter']->getId();
        $this->objects['deviceWebOS']   = ActiveSync_Controller_Device::getInstance()->create($palm);
        
        $iphone = ActiveSync_Backend_DeviceTests::getTestDevice(Syncope_Model_Device::TYPE_IPHONE);
        $iphone->contactsfilter_id = $this->objects['filter']->getId();
        $this->objects['deviceIPhone'] = ActiveSync_Controller_Device::getInstance()->create($iphone);
        
        $htcAndroid = ActiveSync_Backend_DeviceTests::getTestDevice(Syncope_Model_Device::TYPE_ANDROID);
        $htcAndroid->contactsfilter_id = $this->objects['filter']->getId();
        $this->objects['deviceAndroid'] = ActiveSync_Controller_Device::getInstance()->create($htcAndroid);
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        // remove accounts for group member tests
        try {
            Tinebase_User::getInstance()->deleteUser($this->objects['user']->accountId);
        } catch (Exception $e) {
            // do nothing
        }

        Addressbook_Controller_Contact::getInstance()->delete(array($this->objects['contact']->getId(), $this->objects['unSyncableContact']->getId()));
        
        Tinebase_Container::getInstance()->deleteContainer($this->objects['containerWithSyncGrant']);
        Tinebase_Container::getInstance()->deleteContainer($this->objects['containerWithoutSyncGrant']);
        
        ActiveSync_Controller_Device::getInstance()->delete($this->objects['deviceWebOS']);
        ActiveSync_Controller_Device::getInstance()->delete($this->objects['deviceIPhone']);
        
        $filterBackend = new Tinebase_PersistentFilter_Backend_Sql();
        $filterBackend->delete($this->objects['filter']->getId());
        
        Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts($this->_setGeoData);
    }
    
    /**
     * validate getFolders for all devices except IPhone
     */
    public function testGetFoldersPalm()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceWebOS'], new Tinebase_DateTime(null, null, 'de_DE'));
        
        $folders = $controller->getAllFolders();
        
        $this->assertArrayHasKey("addressbook-root", $folders, "key addressbook-root not found");
    }
    
    /**
     * validate getFolders for IPhones
     */
    public function testGetFoldersIPhone()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceIPhone'], new Tinebase_DateTime(null, null, 'de_DE'));
        
        $folders = $controller->getAllFolders();
        
        foreach($folders as $folder) {
            $this->assertTrue(Tinebase_Core::getUser()->hasGrant($folder['folderId'], Tinebase_Model_Grants::GRANT_SYNC));
        }
        $this->assertArrayNotHasKey("addressbook-root", $folders, "key addressbook-root found");
        $this->assertEquals(1, count($folders));
    }
    
    /**
     * validate xml generation for all devices except IPhone
     */
    public function testAppendXmlPalm()
    {
        $imp                   = new DOMImplementation();
        
        $dtd                   = $imp->createDocumentType('AirSync', "-//AIRSYNC//DTD AirSync//EN", "http://www.microsoft.com/");
        $testDom               = $imp->createDocument('uri:AirSync', 'Sync', $dtd);
        $testDom->formatOutput = true;
        $testDom->encoding     = 'utf-8';
        
        $testDom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:Contacts', 'uri:Contacts');
        $testNode = $testDom->documentElement->appendChild($testDom->createElementNS('uri:AirSync', 'TestAppendXml'));
        
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceWebOS'], new Tinebase_DateTime());       
        
        $controller->appendXML($testNode, null, $this->objects['contact']->getId(), array());
        
        $controller->appendXML($testNode, null, $this->objects['contact']->getId(), array());
        
        $this->assertEquals(Tinebase_Translation::getCountryNameByRegionCode('DE'), @$testDom->getElementsByTagNameNS('uri:Contacts', 'BusinessCountry')->item(0)->nodeValue, $testDom->saveXML());
        $this->assertEquals('1975-01-02T03:00:00.000Z', @$testDom->getElementsByTagNameNS('uri:Contacts', 'Birthday')->item(0)->nodeValue, $testDom->saveXML());
    }
    
    /**
     * test add contact
     */
    public function testAddContact()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceAndroid'], new Tinebase_DateTime());
        
        $xml = new SimpleXMLElement($this->_xmlContactBirthdayWithoutTimeAndroid);
        $result = $controller->createEntry($this->objects['containerWithSyncGrant']->getId(), $xml->Collections->Collection->Commands->Add->ApplicationData);
        
        $userTimezone = Tinebase_Core::get(Tinebase_Core::USERTIMEZONE);
        $bday = new Tinebase_DateTime('1969-12-31', $userTimezone);
        $bday->setTimezone('UTC');        
        
        $this->assertTrue(!empty($result));
    }
    
    /**
     * test xml generation for IPhone
     * 
     * birthday must have 12 hours added
     */
    public function testAppendXmlIPhone()
    {
        $imp                   = new DOMImplementation();
        
        $dtd                   = $imp->createDocumentType('AirSync', "-//AIRSYNC//DTD AirSync//EN", "http://www.microsoft.com/");
        $testDom               = $imp->createDocument('uri:AirSync', 'Sync', $dtd);
        $testDom->formatOutput = true;
        $testDom->encoding     = 'utf-8';
        $testDom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:Contacts', 'uri:Contacts');
        
        $collections    = $testDom->documentElement->appendChild($testDom->createElementNS('uri:AirSync', 'Collections'));
        $collection     = $collections->appendChild($testDom->createElementNS('uri:AirSync', 'Collection'));
        $commands       = $collection->appendChild($testDom->createElementNS('uri:AirSync', 'Commands'));
        $add            = $commands->appendChild($testDom->createElementNS('uri:AirSync', 'Add'));
        $appData        = $add->appendChild($testDom->createElementNS('uri:AirSync', 'ApplicationData'));
        
        
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceIPhone'], new Tinebase_DateTime(null, null, 'de_DE'));     
        
        $controller->appendXML($appData, null, $this->objects['contact']->getId(), array());
        
        // no offset and namespace === uri:Contacts
        $this->assertEquals('1975-01-02T03:00:00.000Z', @$testDom->getElementsByTagNameNS('uri:Contacts', 'Birthday')->item(0)->nodeValue, $testDom->saveXML());
        
        #echo $testDom->saveXML();

        // try to encode XML until we have wbxml tests
        $outputStream = fopen("php://temp", 'r+');
        $encoder = new Wbxml_Encoder($outputStream, 'UTF-8', 3);
        $encoder->encode($testDom);
        
        #rewind($outputStream);
        #fpassthru($outputStream);
    }
    
    /**
     * test xml generation for IPhone
     * 
     * birthday must have 12 hours added
     */
    public function testGetServerEntries()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceIPhone'], new Tinebase_DateTime(null, null, 'de_DE'));
        
        $entries = $controller->getServerEntries('addressbook-root', null);
        
        $this->assertContains($this->objects['contact']->getId(), $entries);
        $this->assertNotContains($this->objects['unSyncableContact']->getId(), $entries);
    }
    
    /**
     * test xml generation for IPhone
     * 
     * birthday must have 12 hours added
     */
    public function testSyncableFolder()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceIPhone'], new Tinebase_DateTime(null, null, 'de_DE'));
        
        $entries = $controller->getServerEntries($this->objects['containerWithSyncGrant']->getId(), null);
        
        $this->assertContains($this->objects['contact']->getId(), $entries);
        $this->assertNotContains($this->objects['unSyncableContact']->getId(), $entries);
    }
    
    /**
     * test xml generation for IPhone
     * 
     * birthday must have 12 hours added
     */
    public function testUnSyncableFolder()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceIPhone'], new Tinebase_DateTime(null, null, 'de_DE'));
        
        $entries = $controller->getServerEntries($this->objects['containerWithoutSyncGrant']->getId(), null);
        
        $this->assertNotContains($this->objects['contact']->getId(), $entries);
        $this->assertNotContains($this->objects['unSyncableContact']->getId(), $entries);
    }
    
    /**
     * test getChanged entries
     */
    public function testGetChanged()
    {
        $controller = new ActiveSync_Controller_Contacts($this->objects['deviceIPhone'], new Tinebase_DateTime(null, null, 'de_DE'));
        
        Addressbook_Controller_Contact::getInstance()->update($this->objects['contact'], FALSE);
        Addressbook_Controller_Contact::getInstance()->update($this->objects['unSyncableContact'], FALSE);
        
        $entries = $controller->getChangedEntries('addressbook-root', Tinebase_DateTime::now()->subMinute(1));
        #var_dump($entries);
        $this->assertContains($this->objects['contact']->getId(), $entries);
        $this->assertNotContains($this->objects['unSyncableContact']->getId(), $entries);
    }
    
    /**
     * test search contacts
     * 
     */
    #public function testSearch()
    #{
    #    $controller = new ActiveSync_Controller_Contacts($this->objects['deviceWebOS'], new Tinebase_DateTime(null, null, 'de_DE'));

        // search for non existing contact
    #    $xml = new SimpleXMLElement($this->_exampleXMLNotExisting);
    #    $existing = $controller->search('addressbook-root', $xml->Collections->Collection->Commands->Add->ApplicationData);
        
     #   $this->assertEquals(count($existing), 0);
        
        // search for existing contact
    #    $xml = new SimpleXMLElement($this->_exampleXMLExisting);
    #    $existing = $controller->search('addressbook-root', $xml->Collections->Collection->Commands->Add->ApplicationData);
     #   
    #    $this->assertGreaterThanOrEqual(1, count($existing));
    #}
}
