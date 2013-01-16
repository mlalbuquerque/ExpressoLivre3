<?php
/**
 * Tine 2.0
 *
 * @package     Tasks
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2011-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class to handle a single event
 *
 * This class handles the creation, update and deletion of vevents
 *
 * @package     Tasks
 * @subpackage  Frontend
 */
class Tasks_Frontend_WebDAV_Task extends Sabre_DAV_File implements Sabre_CalDAV_ICalendarObject, Sabre_DAVACL_IACL
{
    /**
     * @var Tinebase_Model_Container
     */
    protected $_container;
    
    /**
     * @var Tasks_Model_Event
     */
    protected $_event;
    
    /**
     * holds the vevent returned to the client
     * 
     * @var string
     */
    protected $_vevent;
    
    /**
     * @var Tasks_Convert_Event_VCalendar
     */
    protected $_converter;
    
    /**
     * Constructor 
     * 
     * @param  string|Tasks_Model_Event  $_event  the id of a event or the event itself 
     */
    public function __construct(Tinebase_Model_Container $_container, $_event = null) 
    {
        $this->_container = $_container;
        $this->_event     = $_event;
        
        if (! $this->_event instanceof Tasks_Model_Task) {
            $this->_event = ($pos = strpos($this->_event, '.')) === false ? $this->_event : substr($this->_event, 0, $pos);
        } else {
            // resolve alarms
            Tasks_Controller_Task::getInstance()->getAlarms($this->_event);
        }
        
        list($backend, $version) = Tasks_Convert_Event_VCalendar_Factory::parseUserAgent($_SERVER['HTTP_USER_AGENT']);
        
        $this->_converter = Tasks_Convert_Event_VCalendar_Factory::factory($backend, $version);
    }
    
    /**
     * this function creates a Tasks_Model_Event and stores it in the database
     * 
     * @todo the header handling does not belong here. It should be moved to the DAV_Server class when supported
     * 
     * @param  Tinebase_Model_Container  $container
     * @param  stream|string             $vobjectData
     */
    public static function create(Tinebase_Model_Container $container, $name, $vobjectData)
    {
        if (is_resource($vobjectData)) {
            $vobjectData = stream_get_contents($vobjectData);
        }
        // Converting to UTF-8, if needed
        $vobjectData = Sabre_DAV_StringUtil::ensureUTF8($vobjectData);
        
        Sabre_CalDAV_ICalendarUtil::validateICalendarObject($vobjectData, array('VTODO', 'VFREEBUSY'));
        
        list($backend, $version) = Tasks_Convert_Event_VCalendar_Factory::parseUserAgent($_SERVER['HTTP_USER_AGENT']);
        
        $event = Tasks_Convert_Event_VCalendar_Factory::factory($backend, $version)->toTine20Model($vobjectData);
        $event->container_id = $container->getId();
        $id = ($pos = strpos($name, '.')) === false ? $name : substr($name, 0, $pos);
        $event->setId($id);
        
        self::enforceEventParameters($event);
        
        if ($event->exdate instanceof Tinebase_Record_RecordSet) {
            foreach($event->exdate as $exdate) {
                if ($exdate->is_deleted == false && $exdate->organizer != $event->organizer) {
                    throw new Sabre_DAV_Exception_PreconditionFailed('Organizer for exdate must be the same like base event');
                }
            }
        }
        
        // check if there is already an existing event with this ID
        // this can happen when the invitation email is faster then the caldav update or
        // or when an event gets moved to another container
        
        $filter = new Tasks_Model_TaskFilter(array(
            array(
                'field' => 'containerType', 
                'operator' => 'equals', 
                'value' => 'all'
            ),
            array(
                'field' => 'completed', 
                'operator' => 'equals', 
                'value' => $event->completed
            ),
            array(
                'field' => 'due', 
                'operator' => 'equals', 
                'value' => $event->due
            ),
            array('condition' => 'OR', 'filters' => array(
                array(
                    'field'     => 'id',
                    'operator'  => 'equals',
                    'value'     => $id
                ),
                array(
                    'field'     => 'uid',
                    'operator'  => 'equals',
                    'value'     => $id
                )
            ))
        ));
        $existingEvent = Tasks_Controller_Task::getInstance()->search($filter, null, false, false, 'sync')->getFirstRecord();
        
        if ($existingEvent === null) {
            $event = Tasks_Controller_Task::getInstance()->create($event);
            
            $vevent = new self($container, $event);
        } else {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' update existing event');
            $vevent = new self($container, $existingEvent);
            $vevent->put($vobjectData);
        }
        
        return $vevent;
    }
    
    public static function enforceEventParameters(Tasks_Model_Task $_event)
    {
        // got there any attendees added?
        //if(! $_event->attendee instanceof Tinebase_Record_RecordSet) {
        //    $_event->attendee = new Tinebase_Record_RecordSet('Calendar_Model_Attender');
        //}

        // can happen only during create not on update
        //if (empty($_event->organizer)) {
        //    $_event->organizer = Tinebase_Core::getUser()->contact_id;
        // }

        // the organizer must always be an attendee
        //if ($_event->organizer === Tinebase_Core::getUser()->contact_id && ($ownAttendee = Calendar_Model_Attender::getOwnAttender($_event->attendee)) == null) {
        //    $_event->attendee->addRecord(new Calendar_Model_Attender(array(
        //         'user_id'   => Tinebase_Core::getUser()->contact_id,
        //        'user_type' => Calendar_Model_Attender::USERTYPE_USER,
        //        'role'      => Calendar_Model_Attender::ROLE_REQUIRED,
        //        'status'    => Calendar_Model_Attender::STATUS_ACCEPTED
        //    )));
        //    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
        //        Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " added organizer as attendee ");
        //}

        //if (empty($_event->transp)) {
        //    $_event->transp = Calendar_Model_Event::TRANSP_OPAQUE;
        //}

        // check also attached exdates
        //if ($_event->exdate instanceof Tinebase_Record_RecordSet) {
        //    foreach($_event->exdate as $exdate) {
        //        if ($exdate->is_deleted == false) {
        //            $exdate->container_id = $_event->container_id;
        //            $exdate->organizer    = $_event->organizer;
        //            self::enforceEventParameters($exdate);
        //        }
        //
        //        if (! $exdate->getId()) {
        //            // new exdates must authenticate for status updates
        //            foreach($_event->attendee as $attendee) {
        //                if ($attendee->status_authkey) {
        //                    $exdateAttedee = Calendar_Model_Attender::getAttendee($exdate->attendee, $attendee);
        //                    if ($exdateAttedee) {
        //                        $exdateAttedee->status_authkey = $attendee->status_authkey;
        //                    }
        //                }
        //            }
        //        }
        //    }
        //}
    }

    /**
     * Deletes the card
     *
     * @todo improve handling
     * @return void
     */
    public function delete() 
    {
        // when a move occurs, thunderbird first sends to delete command and immediately a put command
        // we must delay the delete command, otherwise the put command fails
        sleep(1);
        
        // (re) fetch event as tree move does not refresh src node before delete
        $event = Tasks_Controller_Task::getInstance()->get($this->_event);
        
        // allow delete only if deleted in origin calendar
        if ($event->container_id == $this->_container->getId()) {
            //if (strpos($_SERVER['REQUEST_URI'], Calendar_Frontend_CalDAV_ScheduleInbox::NAME) === false) {
                Tasks_Controller_Task::getInstance()->delete($event->getId());
            //}
        }
        
        // implicitly DECLINE event 
        //else {
            //$attendee = $event->attendee instanceof Tinebase_Record_RecordSet ? 
            //    $event->attendee->filter('displaycontainer_id', $this->_container->getId())->getFirstRecord() :
            //    NULL;
            
            // NOTE: don't allow organizer to instantly delete after update, otherwise we can't handle move @see{Calendar_Frontend_WebDAV_EventTest::testMoveOriginPersonalToShared}
        //    if ($attendee && $attendee->user_id != $event->organizer || Tinebase_DateTime::now()->subSecond(10) > $event->last_modified_time) {
        //        $attendee->status = Calendar_Model_Attender::STATUS_DECLINED;
       //         
       //         self::enforceEventParameters($event);
       //         $this->_event = Calendar_Controller_MSEventFacade::getInstance()->update($event);
         //   } 
       // }
    }
    
    /**
     * Returns the VCard-formatted object 
     * 
     * @return stream
     */
    public function get() 
    {
        $s = fopen('php://temp','r+');
        fwrite($s, $this->_getVEvent());
        rewind($s);
        
        return $s;
    }
    
    /**
     * Returns the uri for this object 
     * 
     * @return string 
     */
    public function getName() 
    {
        return $this->getRecord()->getId() . '.ics';
    }
    
    /**
     * Returns the owner principal
     *
     * This must be a url to a principal, or null if there's no owner 
     * 
     * @todo add real owner
     * @return string|null
     */
    public function getOwner() 
    {
        return null;
        return $this->addressBookInfo['principaluri'];
    }

    /**
     * Returns a group principal
     *
     * This must be a url to a principal, or null if there's no owner
     * 
     * @todo add real group
     * @return string|null 
     */
    public function getGroup() 
    {
        return null;
    }
    
    /**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are 
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to 
     *      be updated. 
     * 
     * @todo add the real logic
     * @return array 
     */
    public function getACL() 
    {
        return null;
        
        return array(
            array(
                'privilege' => '{DAV:}read',
                'principal' => $this->addressBookInfo['principaluri'],
                'protected' => true,
            ),
            array(
                'privilege' => '{DAV:}write',
                'principal' => $this->addressBookInfo['principaluri'],
                'protected' => true,
            ),
        );

    }
    
    /**
     * Returns the mime content-type
     *
     * @return string
     */
    public function getContentType() {
    
        return 'text/calendar';
    
    }
    
    /**
     * Returns an ETag for this object
     *
     * How to calculate the etag?
     * The etag consists of 2 parts. The part, which is equal for all users (subject, dtstart, dtend, ...) and
     * the part which is different for all users(X-MOZ-LASTACK for example).
     * Because of the this we have to generate the etag as the hash of the record id, the lastmodified time stamp and the
     * hash of the json encoded attendee object.
     * This way the etag changes when the general properties or the user specific properties change.
     * 
     * @return string
     */
    public function getETag() 
    {
        $attendeeHash = null;
        
        //if ( ($ownAttendee = Calendar_Model_Attender::getOwnAttender($this->getRecord()->attendee)) instanceof Calendar_Model_Attender) {
        //    $attendeeHash = sha1(Zend_Json::encode($ownAttendee->toArray()));
        //}
        
        //if ($this->getRecord()->exdate instanceof Tinebase_Record_RecordSet) {
        //    foreach ($this->getRecord()->exdate as $exdate) {
        //        if ( ($ownAttendee = Calendar_Model_Attender::getOwnAttender($exdate->attendee)) instanceof Calendar_Model_Attender) {
        //            $attendeeHash = sha1($attendeeHash . Zend_Json::encode($ownAttendee->toArray()));
        //        }
        //    }
        // }
        
        return '"' . sha1($this->getRecord()->getId() . $this->getLastModified()) . $attendeeHash . '"'; 
    }
    
    /**
     * Returns the last modification date as a unix timestamp
     *
     * @return time
     */
    public function getLastModified() 
    {
        return ($this->getRecord()->last_modified_time instanceof Tinebase_DateTime) ? $this->getRecord()->last_modified_time->toString() : $this->getRecord()->creation_time->toString();
    }
    
    /**
     * Returns the size of the vcard in bytes
     *
     * @return int
     */
    public function getSize() 
    {
        return strlen($this->_getVEvent());
    }
    
    /**
     * Updates the VCard-formatted object
     *
     * @param string $cardData
     * @return void
     */
    public function put($cardData) 
    {
        if (get_class($this->_converter) == 'Tasks_Convert_Event_VCalendar_Generic') {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) 
                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " update by generic client not allowed. See Calendat_Convert_Event_VCalendar_Factory for supported clients.");
            throw new Sabre_DAV_Exception_Forbidden('Update denied for unknow client');
        }
        
        if (is_resource($cardData)) {
            $cardData = stream_get_contents($cardData);
        }
        // Converting to UTF-8, if needed
        $cardData = Sabre_DAV_StringUtil::ensureUTF8($cardData);
        
        Sabre_CalDAV_ICalendarUtil::validateICalendarObject($cardData, array('VTODO', 'VFREEBUSY'));
        
        $vobject = Tasks_Convert_Event_VCalendar_Abstract::getVcal($cardData);
        foreach ($vobject->children() as $component) {
            if (isset($component->{'X-TINE20-CONTAINER'})) {
                $xContainerId = $component->{'X-TINE20-CONTAINER'};
                break;
            }
        }
        
        // keep old record for reference
        $recordBeforeUpdate = clone $this->getRecord();
        
        $event = $this->_converter->toTine20Model($vobject, $this->getRecord());
        
        // iCal does sends back an old value, because it does not refresh the vcalendar after 
        // update. Therefor we must reapply the value of last_modified_time after the convert
        $event->last_modified_time = $recordBeforeUpdate->last_modified_time;
        
        $currentContainer = Tinebase_Container::getInstance()->getContainerById($this->getRecord()->container_id);
        //$ownAttendee = Calendar_Model_Attender::getOwnAttender($this->getRecord()->attendee);
        
        // event 'belongs' current user -> allow container move
        if ($currentContainer->isPersonalOf(Tinebase_Core::getUser())) {
            $event->container_id = $this->_container->getId();
        }
        
        // client sends CalDAV event -> handle a container move
        /*else if (isset($xContainerId)) {
            if ($xContainerId == $currentContainer->getId()) {
                $event->container_id = $this->_container->getId();
            } else {
                // @TODO allow organizer to move original cal when he edits the displaycal event?
                if ($ownAttendee && $this->_container->type == Tinebase_Model_Container::TYPE_PERSONAL) {
                    $ownAttendee->displaycontainer_id = $this->_container->getId();
                }
            }
        }
        */
        // client sends event from iMIP invitation -> only allow displaycontainer move
        /*else {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " X-TINE20-CONTAINER not present -> restrict container moves");
            if ($ownAttendee && $this->_container->type == Tinebase_Model_Container::TYPE_PERSONAL) {
                if ($ownAttendee->displaycontainer_id == $currentContainer->getId()) {
                    $event->container_id = $this->_container->getId();
                }
                
                $ownAttendee->displaycontainer_id = $this->_container->getId();
            }
        }*/
        
        self::enforceEventParameters($event);
        
        // don't allow update of alarms for non organizer if oganizer is Tine 2.0 user
        if ($event->organizer !== Tinebase_Core::getUser()->contact_id) {
            $organizerContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId($event->organizer,TRUE);
            
            // reset alarms if organizer is Tine 2.0 user
            if (!empty($organizerContact->account_id)) {
                $this->_resetAlarms($event, $recordBeforeUpdate);
            }
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " " . print_r($event->toArray(), true));
        
        try {
            $this->_event = Tasks_Controller_Task::getInstance()->update($event);
        } catch (Tinebase_Timemachine_Exception_ConcurrencyConflict $ttecc) {
            throw new Sabre_DAV_Exception_PreconditionFailed('An If-Match header was specified, but none of the specified the ETags matched.','If-Match');
        }
        
        // avoid sending headers during unit tests
        if (php_sapi_name() != 'cli') {
            // @todo this belong to DAV_Server, but it currently not supported
            header('ETag: ' . $this->getETag());
        }
    }
    
    /**
     * reset alarms to previous values
     * 
     * we don't reset the alarms in the vcalendar parser already, because this it is a limitation
     * of our current calendar implementation to not allow user specific alarms
     * 
     * @param Calendar_Model_Event $event
     * @param Calendar_Model_Event $recordBeforeUpdate
     */
    protected function _resetAlarms(Tasks_Model_Task $event, Tasks_Model_Task $recordBeforeUpdate)
    {
        $event->alarms = $recordBeforeUpdate->alarms;
    
        if ($event->exdate instanceof Tinebase_Record_RecordSet) {
            foreach ($event->exdate as $exdate) {
                $recurId = $event->id . '-' . (string) $exdate->recurid;
                
                if ($recordBeforeUpdate->exdate instanceof Tinebase_Record_RecordSet && ($matchingRecord = $recordBeforeUpdate->exdate->find('recurid', $recurId)) !== null) {
                    $exdate->alarms = $matchingRecord->alarms;
                } else {
                    $exdate->alarms = new Tinebase_Record_RecordSet('Tinebase_Model_Alarm');
                }
            }
        }
    }
    
    /**
     * Updates the ACL
     *
     * This method will receive a list of new ACE's. 
     * 
     * @param array $acl 
     * @return void
     */
    public function setACL(array $acl) 
    {
        throw new Sabre_DAV_Exception_MethodNotAllowed('Changing ACL is not yet supported');
    }
    
    /**
     * return Tasks_Model_Task and convert contact id to model if needed
     * 
     * @return Tasks_Model_Task
     */
    public function getRecord()
    {
        if (! $this->_event instanceof Tasks_Model_Task) {
            $this->_event = Tasks_Controller_Task::getInstance()->get($this->_event);
            
            // resolve alarms
            //Tasks_Controller_Task::getInstance()->getAlarms($this->_event);
            
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " " . print_r($this->_event->toArray(), true));
        }

        return $this->_event;
    }
    
    /**
     * return vcard and convert Calendar_Model_Event to vcard if needed
     * 
     * @return string
     */
    protected function _getVEvent()
    {
        if ($this->_vevent == null) {
            $this->_vevent = $this->_converter->fromTine20Model($this->getRecord());
            
            foreach ($this->_vevent->children() as $component) {
                if ($component->name == 'VTODO') {
                    // NOTE: we store the requested container here to have an origin when the event is moved
                    $component->{'X-TINE20-CONTAINER'} = $this->_container->getId();
                    
                    if (isset($component->{'VALARM'}) && !$this->_container->isPersonalOf(Tinebase_Core::getUser())) {
                        // prevent duplicate alarms
                        $component->{'X-MOZ-LASTACK'} = new Sabre_VObject_Element_DateTime('X-MOZ-LASTACK');
                        $component->{'X-MOZ-LASTACK'}->setDateTime(Tinebase_DateTime::now()->addYear(100), Sabre_VObject_Element_DateTime::UTC);
                    }
                }
            }
        }
        
        return $this->_vevent->serialize();
    }
}
