<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Crm_Json
 */
class Crm_JsonTest extends Crm_AbstractTest
{
    /**
     * Backend
     *
     * @var Crm_Frontend_Json
     */
    protected $_instance;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
		$suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Crm Json Tests');
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
        $this->_instance = new Crm_Frontend_Json();
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
    
    /**
     * test get crm registry
     * 
     * @return void
     */
    public function testGetRegistryData()
    {
        $registry = $this->_instance->getRegistryData();
        
        $types = array('leadtypes', 'leadstates', 'leadsources');
        
        // check data
        foreach ($types as $type) {
            $this->assertGreaterThan(0, $registry[$type]['totalcount']);
            $this->assertGreaterThan(0, count($registry[$type]['results']));
        }
        
        // check defaults
        $this->assertEquals(array(
            'leadstate_id'  => 1,
            'leadtype_id'   => 1,
            'leadsource_id' => 1,
        ), array(
            'leadstate_id' => $registry['defaults']['leadstate_id'],
            'leadtype_id' => $registry['defaults']['leadtype_id'],
            'leadsource_id' => $registry['defaults']['leadsource_id'],
        ));
        $this->assertEquals(
            Tinebase_Container::getInstance()->getDefaultContainer('Crm')->getId(),
            $registry['defaults']['container_id']['id']
        );
        //print_r($registry);
    }
    
    /**
     * test get settings/config
     * 
     * @return void
     */
    public function testGetSettings()
    {
        $result = $this->_instance->getSettings();

        $this->assertEquals(array('leadstates', 'leadtypes', 'leadsources', 'defaults'), array_keys($result));
        $this->assertEquals(6, count($result[Crm_Model_Config::LEADSTATES]));
        $this->assertEquals(3, count($result[Crm_Model_Config::LEADTYPES]));
        $this->assertEquals(4, count($result[Crm_Model_Config::LEADSOURCES]));
    }
    
    /**
     * test get settings/config
     * 
     * @return void
     */
    public function testSaveSettings()
    {
        $oldSettings = $this->_instance->getSettings();
        
        // change some settings
        $newSettings = $oldSettings;
        $newSettings['defaults']['leadstate_id'] = 2;
        $newSettings['leadsources'][] = array(
            'id' => 5,
            'leadsource' => 'Another Leadsource'
        );
        $anotherResult = $this->_instance->saveSettings($newSettings);
        $this->assertEquals($anotherResult, $newSettings);
        
        // reset original settings
        $result = $this->_instance->saveSettings($oldSettings);
        $this->assertEquals($oldSettings, $result);
        
        // test Crm_Model_Config::getOptionById
        $settings = Crm_Controller::getInstance()->getConfigSettings();
        $this->assertEquals(array(), $settings->getOptionById(5, 'leadsources'));
    }
    
    /**
     * try to add a lead and link a contact
     *
     */
    public function testAddGetSearchDeleteLead()
    {
        // create lead with task and contact
        $contact    = $this->_getContact();
        $task       = $this->_getTask();
        $lead       = $this->_getLead();
        $product    = $this->_getProduct();
        $price      = 200; 
        
        $leadData = $lead->toArray();
        $leadData['relations'] = array(
            array('type'  => 'TASK',    'related_record' => $task->toArray()),
            array('type'  => 'PARTNER', 'related_record' => $contact->toArray()),
            array('type'  => 'PRODUCT', 'related_record' => $product->toArray(), 'remark' => array('price' => $price)),
        );
        // add note
        $note = array(
            'note_type_id'      => 1,
            'note'              => 'phpunit test note',
        );
        $leadData['notes'] = array($note);
        
        $savedLead = $this->_instance->saveLead($leadData);
        $getLead = $this->_instance->getLead($savedLead['id']);
        $searchLeads = $this->_instance->searchLeads($this->_getLeadFilter(), '');
        
        //print_r($searchLeads);
        
        // assertions
        $this->assertEquals($getLead, $savedLead);
        $this->assertEquals($getLead['notes'][0]['note'], $note['note']);
        $this->assertTrue($searchLeads['totalcount'] > 0);
        $this->assertTrue(isset($searchLeads['totalleadstates']) && count($searchLeads['totalleadstates']) > 0);
        $this->assertEquals($lead->description, $searchLeads['results'][0]['description']);
        $this->assertEquals($price, $searchLeads['results'][0]['turnover'], 'turnover has not been calculated using product prices');
        $this->assertEquals($searchLeads['results'][0]['turnover']*$lead->probability/100, $searchLeads['results'][0]['probableTurnover']);
        $this->assertTrue(count($searchLeads['results'][0]['relations']) == 3, 'did not get all relations');

        // get related records and check relations
        foreach ($searchLeads['results'][0]['relations'] as $relation) {
            switch ($relation['type']) {
                case 'PRODUCT':
                    //print_r($relation);
                    $this->assertEquals(200, $relation['remark']['price'], 'product price (remark) does not match');
                    $relatedProduct = $relation['related_record'];
                    break;
                case 'TASK':
                    $relatedTask = $relation['related_record'];
                    break;
                case 'PARTNER':
                    $relatedContact = $relation['related_record'];
                    break;
            }
        }
        $this->assertTrue(isset($relatedContact), 'contact not found');
        $this->assertEquals($contact->n_fn, $relatedContact['n_fn'], 'contact name does not match');
        
        $this->assertTrue(isset($relatedTask), 'task not found');
        $this->assertEquals($task->summary, $relatedTask['summary'], 'task summary does not match');
        $defaultTaskContainerId = Tinebase_Core::getPreference('Tasks')->getValue(Tasks_Preference::DEFAULTTASKLIST);
        $this->assertEquals($defaultTaskContainerId, $relatedTask['container_id']);
        $this->assertTrue(isset($relatedProduct), 'product not found');
        $this->assertEquals($product->name, $relatedProduct['name'], 'product name does not match');
         
        // delete all
        $this->_instance->deleteLeads($savedLead['id']);
        Addressbook_Controller_Contact::getInstance()->delete($relatedContact['id']);
        Sales_Controller_Product::getInstance()->delete($relatedProduct['id']);
        
        // check if delete worked
        $result = $this->_instance->searchLeads($this->_getLeadFilter(), '');
        $this->assertEquals(0, $result['totalcount']);   
        
        // check if linked task got removed as well
        $this->setExpectedException('Tinebase_Exception_NotFound');
        $task = Tasks_Controller_Task::getInstance()->get($relatedTask['id']);
    }
    
    /**
     * test tag filter (adds a contact with the same id + tag)
     * 
     * see bug #4834 (http://forge.tine20.org/mantisbt/view.php?id=4834)
     */
    public function testTagFilter()
    {
        $lead       = $this->_getLead();
        $savedLead = $this->_instance->saveLead($lead->toArray());
        
        $sharedTagName = Tinebase_Record_Abstract::generateUID();
        $tag = new Tinebase_Model_Tag(array(
            'type'  => Tinebase_Model_Tag::TYPE_SHARED,
            'name'  => $sharedTagName,
            'description' => 'testTagFilter',
            'color' => '#009B31',
        ));
        $contact    = $this->_getContact();
        $contact->setId($savedLead['id']);
        
        $contact->tags = array($tag);
        $savedContact = Addressbook_Controller_Contact::getInstance()->create($contact);
        $tag = $savedContact->tags->getFirstRecord();
        
        $filter = array(
            array('field' => 'tag',           'operator' => 'equals',       'value' => $tag->getId()),
        );
        
        $result = $this->_instance->searchLeads($filter, array());
        $this->assertEquals(0, $result['totalcount'], 'Should not find the lead!');
    }    
    
    /**
     * add relation, remove relation and add relation again
     * 
     * see bug #4840 (http://forge.tine20.org/mantisbt/view.php?id=4840)
     */
    public function testAddRelationAgain()
    {
        $contact    = $this->_getContact();
        $savedContact = Addressbook_Controller_Contact::getInstance()->create($contact, FALSE);
        $lead       = $this->_getLead();
        
        $leadData = $lead->toArray();
        $leadData['relations'] = array(
            array('type'  => 'PARTNER', 'related_record' => $savedContact->toArray()),
        );
        $savedLead = $this->_instance->saveLead($leadData);
        
        $savedLead['relations'] = array();
        $savedLead = $this->_instance->saveLead($savedLead);
        $this->assertEquals(0, count($savedLead['relations']));
        
        $savedLead['relations'] = array(
            array('type'  => 'PARTNER', 'related_record' => $savedContact->toArray()),
        );
        $savedLead = $this->_instance->saveLead($savedLead);
        
        $this->assertEquals(1, count($savedLead['relations']), 'Relation has not been added');
        $this->assertEquals($contact->n_fn, $savedLead['relations'][0]['related_record']['n_fn'], 'Contact name does not match');
    }
    
    /**
     * get contact
     * 
     * @return Addressbook_Model_Contact
     */
    protected function _getContact()
    {
        return new Addressbook_Model_Contact(array(
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
            'assistent'             => 'Cornelius Weiß',
            'bday'                  => '1975-01-02 03:04:05', // new Tinebase_DateTime???
            'email'                 => 'unittests@tine20.org',
            'email_home'            => 'unittests@tine20.org',
            'note'                  => 'Bla Bla Bla',
            'role'                  => 'Role',
            'title'                 => 'Title',
            'url'                   => 'http://www.tine20.org',
            'url_home'              => 'http://www.tine20.com',
            'n_family'              => 'Kneschke',
            'n_fileas'              => 'Kneschke, Lars',
            'n_given'               => 'Lars',
            'n_middle'              => 'no middle name',
            'n_prefix'              => 'no prefix',
            'n_suffix'              => 'no suffix',
            'org_name'              => 'Metaways Infosystems GmbH',
            'org_unit'              => 'Tine 2.0',
            'tel_assistent'         => '+49TELASSISTENT',
            'tel_car'               => '+49TELCAR',
            'tel_cell'              => '+49TELCELL',
            'tel_cell_private'      => '+49TELCELLPRIVATE',
            'tel_fax'               => '+49TELFAX',
            'tel_fax_home'          => '+49TELFAXHOME',
            'tel_home'              => '+49TELHOME',
            'tel_pager'             => '+49TELPAGER',
            'tel_work'              => '+49TELWORK',
        ));        
    }

    /**
     * get task
     * 
     * @return Tasks_Model_Task
     */
    protected function _getTask()
    {
        return new Tasks_Model_Task(array(
            'created_by'           => Zend_Registry::get('currentAccount')->getId(),
            'creation_time'        => Tinebase_DateTime::now(),
            'percent'              => 70,
            'due'                  => Tinebase_DateTime::now()->addMonth(1),
            'summary'              => 'phpunit: crm test task',
        ));
    }
    
    /**
     * get lead
     * 
     * @return Crm_Model_Lead
     */
    protected function _getLead()
    {
        return new Crm_Model_Lead(array(
            'lead_name'     => 'PHPUnit',
            'leadstate_id'  => 1,
            'leadtype_id'   => 1,
            'leadsource_id' => 1,
            'container_id'  => Tinebase_Container::getInstance()->getDefaultContainer('Crm')->getId(),
            'start'         => Tinebase_DateTime::now(),
            'description'   => 'Description',
            'end'           => NULL,
            'turnover'      => 0,
            'probability'   => 70,
            'end_scheduled' => NULL,
        ));
    }
    
    /**
     * get product
     * 
     * @return Sales_Model_Product
     */
    protected function _getProduct()
    {
        return new Sales_Model_Product(array(
            'name'  => 'PHPUnit test product',
            'price' => 10000,        
        ));
    }
    
    /**
     * get lead filter
     * 
     * @return array
     */
    protected function _getLeadFilter()
    {
        return array(
            array('field' => 'query',           'operator' => 'contains',       'value' => 'PHPUnit'),
        );
    }
}		
