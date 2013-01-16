<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Calendar_Model_AttenderTests::main');
}

/**
 * Test class for Calendar_Model_Attender
 * 
 * @package     Calendar
 */
class Calendar_Model_AttenderTests extends Calendar_TestCase
{
    protected $_testEmailContacts = array();
    
    /**
     * tear down tests
     *
     */
    public function tearDown()
    {
        parent::tearDown();
        
        foreach($this->_testEmailContacts as $email) {
            $contactIdsToDelete = Addressbook_Controller_Contact::getInstance()->search(new Addressbook_Model_ContactFilter(array(
        	    array('field' => 'containerType', 'operator' => 'equals', 'value' => 'all'),
                array('field' => 'email',      'operator'  => 'equals', 'value' => $email)
        	)), null, false, true);
            
            Addressbook_Controller_Contact::getInstance()->delete($contactIdsToDelete);
        }
    }
    
    /**
     * @return void
     */
    public function testEmailsToAttendee()
    {
    	$event = $this->_getEvent();
    	
        $persistentEvent = Calendar_Controller_Event::getInstance()->create($event);
        
        $sclever = Tinebase_User::getInstance()->getUserByLoginName('sclever', 'Tinebase_Model_FullUser');
        $newEmail = Tinebase_Record_Abstract::generateUID() . '@unittest.com';
        
        // delete newly created contact in tearDown()
        $this->_testEmailContacts[] = $newEmail;
        
        $newAttendees = array(
            array(
            	'userType'    => Calendar_Model_Attender::USERTYPE_USER,
                'firstName'   => $this->_testUser->accountFirstName,
        		'lastName'    => $this->_testUser->accountLastName,
                'partStat'    => Calendar_Model_Attender::STATUS_ACCEPTED,
                'role'        => Calendar_Model_Attender::ROLE_REQUIRED,
                'email'       => $this->_testUser->accountEmailAddress
            ),
            array(
                'userType'    => Calendar_Model_Attender::USERTYPE_USER,
                'displayName' => $sclever->accountDisplayName,
                'partStat'    => Calendar_Model_Attender::STATUS_DECLINED,
                'role'        => Calendar_Model_Attender::ROLE_REQUIRED,
                'email'       => $sclever->accountEmailAddress
            ),
            array(
            	'userType'    => Calendar_Model_Attender::USERTYPE_USER,
                'firstName'   => 'Lars',
        		'lastName'    => 'Kneschke',
                'partStat'    => Calendar_Model_Attender::STATUS_TENTATIVE,
                'role'        => Calendar_Model_Attender::ROLE_REQUIRED,
                'email'       => $newEmail
            )
        );
        
        Calendar_Model_Attender::emailsToAttendee($persistentEvent, $newAttendees, TRUE);
        
        $this->assertEquals(3, count($persistentEvent->attendee));
        $this->assertEquals(1, count($persistentEvent->attendee->filter('status', Calendar_Model_Attender::STATUS_ACCEPTED)));
        $this->assertEquals(1, count($persistentEvent->attendee->filter('status', Calendar_Model_Attender::STATUS_DECLINED)));
        $this->assertEquals(1, count($persistentEvent->attendee->filter('status', Calendar_Model_Attender::STATUS_TENTATIVE)));
    }
    
    /**
     * @return void
     */
    public function testEmailsToAttendeeWithGroups()
    {
    	$event = $this->_getEvent();
    	
        $persistentEvent = Calendar_Controller_Event::getInstance()->create($event);
        
        $primaryGroup = Tinebase_Group::getInstance()->getGroupById(Tinebase_Core::getUser()->accountPrimaryGroup);
        
        $newAttendees = array(
            array(
            	'userType'    => Calendar_Model_Attender::USERTYPE_USER,
                'firstName'   => $this->_testUser->accountFirstName,
        		'lastName'    => $this->_testUser->accountLastName,
                'partStat'    => Calendar_Model_Attender::STATUS_TENTATIVE,
                'role'        => Calendar_Model_Attender::ROLE_REQUIRED,
                'email'       => $this->_testUser->accountEmailAddress
            ),
            array(
                'userType'    => Calendar_Model_Attender::USERTYPE_GROUP,
                'displayName' => $primaryGroup->name,
                'partStat'    => Calendar_Model_Attender::STATUS_NEEDSACTION,
                'role'        => Calendar_Model_Attender::ROLE_REQUIRED,
                'email'       => 'foobar@example.org'
            )
        );
        
        Calendar_Model_Attender::emailsToAttendee($persistentEvent, $newAttendees, TRUE);
        
        $persistentEvent = Calendar_Controller_Event::getInstance()->update($persistentEvent);

        $attendees = clone $persistentEvent->attendee;
        
        Calendar_Model_Attender::resolveAttendee($attendees);
        
        $newAttendees = array();
        
        foreach ($attendees as $attendee) {
            $newAttendees[] = array(
            	'userType'    => $attendee->user_type == 'group' ? Calendar_Model_Attender::USERTYPE_GROUP : Calendar_Model_Attender::USERTYPE_USER,
            	'partStat'    => Calendar_Model_Attender::STATUS_TENTATIVE,
            	'role'        => Calendar_Model_Attender::ROLE_REQUIRED,
            	'email'       => $attendee->user_type == 'group' ? $attendee->user_id->getId() : $attendee->user_id->email,
            	'displayName' => $attendee->user_type == 'group' ? $attendee->user_id->name : $attendee->user_id->n_fileas
            ); 
        }
        
        Calendar_Model_Attender::emailsToAttendee($persistentEvent, $newAttendees, TRUE);
        
        $persistentEvent = Calendar_Controller_Event::getInstance()->update($persistentEvent);
        
        //var_dump($persistentEvent->attendee->toArray());
        
        // there must be more than 2 attendees the user, the group + the groupmembers
        $this->assertGreaterThan(2, count($persistentEvent->attendee));
    }
}
    

if (PHPUnit_MAIN_METHOD == 'Calendar_Model_AttenderTests::main') {
    Calendar_Model_AttenderTests::main();
}
