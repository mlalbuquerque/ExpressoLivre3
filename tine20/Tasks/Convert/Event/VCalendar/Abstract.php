<?php
/**
 * Tine 2.0
 *
 * @package     Calendar
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2011-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class to convert single event (repeating with exceptions) to/from VCalendar
 *
 * @package     Calendar
 * @subpackage  Convert
 */
class Tasks_Convert_Event_VCalendar_Abstract implements Tinebase_Convert_Interface
{
    public static $cutypeMap = array(
        //Tasks_Model_Attender::USERTYPE_USER          => 'INDIVIDUAL',
        //Tasks_Model_Attender::USERTYPE_GROUPMEMBER   => 'INDIVIDUAL',
        //Tasks_Model_Attender::USERTYPE_GROUP         => 'GROUP',
        //Tasks_Model_Attender::USERTYPE_RESOURCE      => 'RESOURCE',
    );
    
    protected $_supportedFields = array(
    );
    
    protected $_version;
    
    /**
     * value of METHOD property
     * @var string
     */
    protected $_method;
    
    /**
     * @param  string  $_version  the version of the client
     */
    public function __construct($_version = null)
    {
        $this->_version = $_version;
    }
        
    /**
     * convert Tasks_Model_Event to Sabre_VObject_Component
     *
     * @param  Tasks_Model_Event  $_record
     * @return Sabre_VObject_Component
     */
    public function fromTine20Model(Tinebase_Record_Abstract $_record)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' event ' . print_r($_record->toArray(), true));
        
        $vcalendar = new Sabre_VObject_Component('VCALENDAR');
        
        // required vcalendar fields
        $version = Tinebase_Application::getInstance()->getApplicationByName('Tasks')->version;
        if (isset($this->_method)) {
            $vcalendar->METHOD = $this->_method;
        }
        $vcalendar->PRODID   = "-//tine20.org//Tine 2.0 Calendar V$version//EN";
        $vcalendar->VERSION  = '2.0';
        $vcalendar->CALSCALE = 'GREGORIAN';
        
        //$vtimezone = $this->_convertDateTimezone($_record->originator_tz);
        //$vcalendar->add($vtimezone);
        
        $vtodo = $this->_convertTasksModelTask($_record);
        $vcalendar->add($vtodo);
        
        if ($_record->exdate instanceof Tinebase_Record_RecordSet) {
            $eventExceptions = $_record->exdate->filter('is_deleted', false);
            
            foreach($eventExceptions as $eventException) {
                // set timefields
                // @todo move to MS event facade
                $eventException->creation_time = $_record->creation_time;
                if (isset($_record->last_modified_time)) {
                    $eventException->last_modified_time = $_record->last_modified_time;
                }
                $vevent = $this->_convertTasksModelTask($eventException, $_record);
                $vcalendar->add($vevent);
            }
            
        }
        
        $this->_afterFromTine20Model($vcalendar);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' card ' . $vcalendar->serialize());
        
        return $vcalendar;
    }
    
    /**
     * convert DateTimezone to Sabre_VObject_Component('VTIMEZONE')
     * 
     * @param  string|DateTimeZone  $_timezone
     * @return Sabre_VObject_Component
     */
    protected function _convertDateTimezone($_timezone)
    {
        $timezone = new DateTimeZone($_timezone);
        
        $vtimezone = new Sabre_VObject_Component('VTIMEZONE');
        $vtimezone->add(new Sabre_VObject_Property('TZID', $timezone->getName()));
        $vtimezone->add(new Sabre_VObject_Property('X-LIC-LOCATION', $timezone->getName()));
        
        list($standardTransition, $daylightTransition) = $transitions = $this->_getTransitionsForTimezoneAndYear($timezone, date('Y'));
        
        $dtstart = new Sabre_VObject_Element_DateTime('DTSTART');
        $dtstart->setDateTime(new DateTime(), Sabre_VObject_Element_DateTime::LOCAL);
        
        if ($daylightTransition !== null) {
            $offsetTo   = ($daylightTransition['offset'] < 0 ? '-' : '+') . strftime('%H%M', abs($daylightTransition['offset']));
            $offsetFrom = ($standardTransition['offset'] < 0 ? '-' : '+') . strftime('%H%M', abs($standardTransition['offset']));
            
            $daylight  = new Sabre_VObject_Component('DAYLIGHT');
            $daylight->add('TZOFFSETFROM', $offsetFrom);
            $daylight->add('TZOFFSETTO',   $offsetTo);
            $daylight->add('TZNAME',       $daylightTransition['abbr']);
            $daylight->add($dtstart);
            #$daylight->add('RRULE', 'FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3');
            
            $vtimezone->add($daylight);
        }

        if ($standardTransition !== null) {
            $offsetTo   = ($standardTransition['offset'] < 0 ? '-' : '+') . strftime('%H%M', abs($standardTransition['offset']));
            if ($daylightTransition !== null) {
                $offsetFrom = ($daylightTransition['offset'] < 0 ? '-' : '+') . strftime('%H%M', abs($daylightTransition['offset']));
            } else {
                $offsetFrom = $offsetTo;
            }
            
            $standard  = new Sabre_VObject_Component('STANDARD');
            $standard->add('TZOFFSETFROM', $offsetFrom);
            $standard->add('TZOFFSETTO',   $offsetTo);
            $standard->add('TZNAME',       $standardTransition['abbr']);
            $standard->add($dtstart);
            #$standard->add('RRULE', 'FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10');
            
            $vtimezone->add($standard);
        }
        
        return $vtimezone;
    }
    
    /**
     * Returns the standard and daylight transitions for the given {@param $_timezone}
     * and {@param $_year}.
     *
     * @param DateTimeZone $_timezone
     * @param $_year
     * @return Array
     */
    protected function _getTransitionsForTimezoneAndYear(DateTimeZone $_timezone, $_year)
    {
        $standardTransition = null;
        $daylightTransition = null;
    
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
            // Since php version 5.3.0 getTransitions accepts optional start and end parameters.
            $start = mktime(0, 0, 0, 12, 1, $_year - 1);
            $end   = mktime(24, 0, 0, 12, 31, $_year);
            $transitions = $_timezone->getTransitions($start, $end);
        } else {
            $transitions = $_timezone->getTransitions();
        }
    
        $index = 0;            //we need to access index counter outside of the foreach loop
        $transition = array(); //we need to access the transition counter outside of the foreach loop
        foreach ($transitions as $index => $transition) {
            if (strftime('%Y', $transition['ts']) == $_year) {
                if (isset($transitions[$index+1]) && strftime('%Y', $transitions[$index]['ts']) == strftime('%Y', $transitions[$index+1]['ts'])) {
                    $daylightTransition = $transition['isdst'] ? $transition : $transitions[$index+1];
                    $standardTransition = $transition['isdst'] ? $transitions[$index+1] : $transition;
                } else {
                    $daylightTransition = $transition['isdst'] ? $transition : null;
                    $standardTransition = $transition['isdst'] ? null : $transition;
                }
                break;
            } elseif ($index == count($transitions) -1) {
                $standardTransition = $transition;
            }
        }
         
        return array($standardTransition, $daylightTransition);
    }
    
    /**
     * convert calendar event to Sabre_VObject_Component
     * 
     * @param Calendar_Model_Event $_event
     * @param Calendar_Model_Event $_mainEvent
     * @return Sabre_VObject_Component
     */
    protected function _convertTasksModelTask(Tasks_Model_Task $_event, Tasks_Model_Task $_mainEvent = null)
    {
        // clone the event and change the timezone
        $event = clone $_event;
        if(isset($event->originator_tz))
        {$event->setTimezone($event->originator_tz);}
        
        $vevent = new Sabre_VObject_Component('VTODO');
                
        $lastModifiedDatTime = $event->last_modified_time ? $event->last_modified_time : $event->creation_time;
        
        $created = new Sabre_VObject_Element_DateTime('CREATED');
        $created->setDateTime($event->creation_time, Sabre_VObject_Element_DateTime::UTC);
        $vevent->add($created);
        
        $lastModified = new Sabre_VObject_Element_DateTime('LAST-MODIFIED');
        $lastModified->setDateTime($lastModifiedDatTime, Sabre_VObject_Element_DateTime::UTC);
        $vevent->add($lastModified);
        
        $dtstamp = new Sabre_VObject_Element_DateTime('DTSTAMP');
        $dtstamp->setDateTime(Tinebase_DateTime::now(), Sabre_VObject_Element_DateTime::UTC);
        $vevent->add($dtstamp);
        
        $vevent->add(new Sabre_VObject_Property('UID', $event->id));
        //if(isset($event->seq))
        //{$vevent->add(new Sabre_VObject_Property('SEQUENCE', $event->seq));}

        /*  
        if ($event->isRecurException()) {
            $originalDtStart = $event->getOriginalDtStart();
            $originalDtStart->setTimezone($_event->originator_tz);
            
            $recurrenceId = new Sabre_VObject_Element_DateTime('RECURRENCE-ID');
            if ($_mainEvent && $_mainEvent->is_all_day_event == true) {
                $recurrenceId->setDateTime($originalDtStart, Sabre_VObject_Element_DateTime::DATE);
            } else {
                $recurrenceId->setDateTime($originalDtStart);
            }

            $vevent->add($recurrenceId);
        }
        */
        // dtstart and dtend
        /*if ($event->is_all_day_event == true) {
            $dtstart = new Sabre_VObject_Element_DateTime('DTSTART');
            $dtstart->setDateTime($event->dtstart, Sabre_VObject_Element_DateTime::DATE);
            
            // whole day events ends at 23:59:(00|59) in Tine 2.0 but 00:00 the next day in vcalendar
            $event->dtend->addSecond($event->dtend->get('s') == 59 ? 1 : 0);
            $event->dtend->addMinute($event->dtend->get('i') == 59 ? 1 : 0);
            
            $dtend = new Sabre_VObject_Element_DateTime('DTEND');
            $dtend->setDateTime($event->dtend, Sabre_VObject_Element_DateTime::DATE);
        } else {*/
        if(isset($event->start_time)){
        $dtstart = new Sabre_VObject_Element_DateTime('DTSTART');
        $dtstart->setDateTime($event->start_time);
        $vevent->add($dtstart);
        }
        if(isset($event->due)){
        $due = new Sabre_VObject_Element_DateTime('DUE');
        $due->setDateTime($event->due);
        $vevent->add($due);
        }
        if(isset($event->completed)){
         $dtend = new Sabre_VObject_Element_DateTime('COMPLETED');
         $dtend->setDateTime($event->completed);
         $vevent->add($dtend);
        }

        switch($event->priority) {
                     case 'LOW' :
                           $vevent->add(new Sabre_VObject_Property('PRIORITY','9'));
                             break;
                     case 'NORMAL' :
                           $vevent->add(new Sabre_VObject_Property('PRIORITY','0'));
                              break;
                     case 'HIGH' :
                           $vevent->add(new Sabre_VObject_Property('PRIORITY','1'));
                              break;
                     case 'URGENT' :
                           $vevent->add(new Sabre_VObject_Property('PRIORITY','1'));
                              break;
        }


        if(!empty($event->percent)){
         $vevent->add(new Sabre_VObject_Property('PERCENT-COMPLETE', $event->percent));
        }

        //}

        // event organizer
        if (!empty($event->organizer)) {
            $organizerContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId($event->organizer, TRUE);
            if ($organizerContact instanceof Addressbook_Model_Contact && !empty($organizerContact->email)) {
                $organizer = new Sabre_VObject_Property('ORGANIZER', 'mailto:' . $organizerContact->email);
                $organizer->add('CN', $organizerContact->n_fileas);
                $vevent->add($organizer);
            }
         }
        
        //$this->_addEventAttendee($vevent, $event);
        
        $optionalProperties = array(
            'class',
            'description',
            'geo',
            'location',
            #'priority',
            'summary',
            'status',
            'completed',
            'url'
        );
        
        foreach ($optionalProperties as $property) {
            if (!empty($event->$property)) {
                $vevent->add(new Sabre_VObject_Property(strtoupper($property), $event->$property));
            }
        }
        
        // categories
        if(isset($event->tags) && count($event->tags) > 0) {
            $vevent->add(new Sabre_VObject_Property_List('CATEGORIES', (array) $event->tags->name));
        }
        
        // repeating event properties
        /*if ($event->rrule) {
            if ($event->is_all_day_event == true) {
                $vevent->add(new Sabre_VObject_Property_Recure('RRULE', preg_replace_callback('/UNTIL=([\d :-]{19})(?=;?)/', function($matches) {
                    $dtUntil = new Tinebase_DateTime($matches[1]);
                    $dtUntil->setTimezone((string) Tinebase_Core::get(Tinebase_Core::USERTIMEZONE));
                    
                    return 'UNTIL=' . $dtUntil->format('Ymd');
                }, $event->rrule)));
            } else {
                $vevent->add(new Sabre_VObject_Property_Recure('RRULE', preg_replace('/(UNTIL=)(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/', '$1$2$3$4T$5$6$7Z', $event->rrule)));
            }
            if ($event->exdate instanceof Tinebase_Record_RecordSet) {
                $deletedEvents = $event->exdate->filter('is_deleted', true);
                
                foreach($deletedEvents as $deletedEvent) {
                    $exdate = new Sabre_VObject_Element_DateTime('EXDATE');
                    $dateTime = $deletedEvent->getOriginalDtStart();
                    
                    if ($event->is_all_day_event == true) {
                        $dateTime->setTimezone($event->originator_tz);
                        $exdate->setDateTime($dateTime, Sabre_VObject_Element_DateTime::DATE);
                    } else {
                        $exdate->setDateTime($dateTime, Sabre_VObject_Element_DateTime::UTC);
                    }
                    $vevent->add($exdate);
                }
            }
        }
        */
        // add alarms only to vcalendar if current user attends to this event
        if ($event->alarms) {
            //$ownAttendee = Calendar_Model_Attender::getOwnAttender($event->attendee);
            
            //if ($ownAttendee && $ownAttendee->alarm_ack_time instanceof Tinebase_DateTime) {
            //    $xMozLastAck = new Sabre_VObject_Element_DateTime('X-MOZ-LASTACK');
            //    $xMozLastAck->setDateTime($ownAttendee->alarm_ack_time, Sabre_VObject_Element_DateTime::UTC);
            //    $vevent->add($xMozLastAck);
            //}
            
            //if ($ownAttendee && $ownAttendee->alarm_snooze_time instanceof Tinebase_DateTime) {
            //    $xMozSnoozeTime = new Sabre_VObject_Element_DateTime('X-MOZ-SNOOZE-TIME');
            //    $xMozSnoozeTime->setDateTime($ownAttendee->alarm_snooze_time, Sabre_VObject_Element_DateTime::UTC);
            //    $vevent->add($xMozSnoozeTime);
            //}
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' event ' . print_r($event->alarms,TRUE));
 
            foreach($event->alarms as $alarm) {
                $valarm = new Sabre_VObject_Component('VALARM');
                $valarm->add('ACTION', 'DISPLAY');
                $valarm->add('DESCRIPTION', $event->summary);
                
                if (is_numeric($alarm->minutes_before)) {
                    if ($event->dtstart == $alarm->alarm_time) {
                        $periodString = 'PT0S';
                    } else {
                        $interval = $event->due->diff($alarm->alarm_time);
                        $periodString = sprintf('%sP%s%s%s%s',
                            $interval->format('%r'),
                            $interval->format('%d') > 0 ? $interval->format('%dD') : null,
                            ($interval->format('%h') > 0 || $interval->format('%i') > 0) ? 'T' : null,
                            $interval->format('%h') > 0 ? $interval->format('%hH') : null,
                            $interval->format('%i') > 0 ? $interval->format('%iM') : null
                        );
                    }
                    # TRIGGER;VALUE=DURATION:-PT1H15M
                    $trigger = new Sabre_VObject_Property('TRIGGER', $periodString);
                    $trigger->add('VALUE', "DURATION");
                    $valarm->add($trigger);
                } else {
                    # TRIGGER;VALUE=DATE-TIME:...
                    $trigger = new Sabre_VObject_Element_DateTime('TRIGGER');
                    $trigger->add('VALUE', "DATE-TIME");
                    $trigger->setDateTime($alarm->alarm_time, Sabre_VObject_Element_DateTime::UTC);
                    $valarm->add($trigger);
                }
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Entrou VALRM ' );

                $vevent->add($valarm);
            }
        }
         
        return $vevent;
    }
    
    protected function _addEventAttendee(Sabre_VObject_Component $_vevent, Calendar_Model_Event $_event)
    {
        Calendar_Model_Attender::resolveAttendee($_event->attendee, FALSE, $_event);
        
        foreach($_event->attendee as $eventAttendee) {
            $attendeeEmail = $eventAttendee->getEmail();
            
            $attendee = new Sabre_VObject_Property('ATTENDEE', (strpos($attendeeEmail, '@') !== false ? 'mailto:' : 'urn:uuid:') . $attendeeEmail);
            $attendee->add('CN',       $eventAttendee->getName());
            $attendee->add('CUTYPE',   Calendar_Convert_Event_VCalendar_Abstract::$cutypeMap[$eventAttendee->user_type]);
            $attendee->add('PARTSTAT', $eventAttendee->status);
            $attendee->add('ROLE',     "{$eventAttendee->role}-PARTICIPANT");
            $attendee->add('RSVP',     'FALSE');
            if (strpos($attendeeEmail, '@') !== false) {
                $attendee->add('EMAIL',    $attendeeEmail);
            }

            $_vevent->add($attendee);
        }
    }
    
    /**
     * to be overwriten in extended classes to modify/cleanup $_vcalendar
     * 
     * @param Sabre_VObject_Component $_vcalendar
     */
    protected function _afterFromTine20Model(Sabre_VObject_Component $_vcalendar)
    {
        
    }
    
    /**
     * set the METHOD for the generated VCALENDAR
     *
     * @param  string  $_method  the method
     */
    public function setMethod($_method)
    {
        $this->_method = $_method;
    }
    
    /**
     * converts vcalendar to Tasks_Model_Task
     * 
     * @param  mixed                 $_blob   the vcalendar to parse
     * @param  Calendar_Model_Event  $_record  update existing event
     * @return Calendar_Model_Event
     */
    public function toTine20Model($_blob, Tinebase_Record_Abstract $_record = null)
    {
        $vcalendar = self::getVcal($_blob);
        
        // contains the VCALENDAR any VEVENTS
        if (!isset($vcalendar->VTODO)) {
            throw new Tinebase_Exception_UnexpectedValue('no vevents found');
        }
        
        // update a provided record or create a new one
        if ($_record instanceof Tasks_Model_Task) {
            $event = $_record;
        } else {
            $event = new Tasks_Model_Task(null, false);
        }
        
        // keep current exdate's (only the not deleted ones)
       /* if ($event->exdate instanceof Tinebase_Record_RecordSet) {
            $oldExdates = $event->exdate->filter('is_deleted', false);
        } else {
            $oldExdates = new Tinebase_Record_RecordSet('Calendar_Model_Events');
        }
        */
        if (!isset($vcalendar->METHOD)) {
            $this->_method = $vcalendar->METHOD;
        }
        
        // find the main event - the main event has no RECURRENCE-ID
        foreach($vcalendar->VTODO as $vtodo) {
            // "-" is not allowed in property names
            $RECURRENCEID = 'RECURRENCE-ID';
            if(!isset($vtodo->$RECURRENCEID)) {
                $this->_convertVevent($vtodo, $event);
                
                break;
            }
        }

        // if we have found no VEVENT component something went wrong, lets stop here
        if (!isset($event)) {
            throw new Tinebase_Exception_UnexpectedValue('no main VEVENT component found in VCALENDAR');
        }
        
        // parse the event exceptions
        foreach($vcalendar->VTODO as $vtodo) {
            if(isset($vtodo->$RECURRENCEID) && $event->id == $vtodo->UID) {
                $recurException = $this->_getRecurException($oldExdates, $vtodo);
                
                // initialize attendee with attendee from base events for new exceptions
                // this way we can keep attendee extra values like groupmember type
                // attendees which do not attend to the new exception will be removed in _convertVevent
                /*if (! $recurException->attendee instanceof Tinebase_Record_RecordSet) {
                    $recurException->attendee = new Tinebase_Record_RecordSet('Calendar_Model_Attender');
                    foreach ($event->attendee as $attendee) {
                        $recurException->attendee->addRecord(new Calendar_Model_Attender(array(
                            'user_id'   => $attendee->user_id,
                            'user_type' => $attendee->user_type,
                            'role'      => $attendee->role,
                            'status'    => $attendee->status
                        )));
                    }
                }*/
                
                $this->_convertVevent($vtodo, $recurException);
                    
                //if(! $event->exdate instanceof Tinebase_Record_RecordSet) {
                //    $event->exdate = new Tinebase_Record_RecordSet('Calendar_Model_Event');
                //}
                $event->exdate->addRecord($recurException);
            }
        }
        
        $event->isValid(true);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' FLAMENGO3 ' . print_r($event->toarray(),TRUE));
        
        return $event;
    }
    
    /**
     * returns VObject of input data
     * 
     * @param mixed $_blob
     * @return Sabre_VObject_Component
     */
    public static function getVcal($_blob)
    {
        if ($_blob instanceof Sabre_VObject_Component) {
            $vcalendar = $_blob;
        } else {
            if (is_resource($_blob)) {
                $_blob = stream_get_contents($_blob);
            }
            $vcalendar = Sabre_VObject_Reader::read($_blob);
        }
        
        return $vcalendar;
    }
    
    public function getMethod($_blob = NULL)
    {
        $result = NULL;
        
        if ($this->_method) {
            $result = $this->_method;
        } else if ($_blob !== NULL) {
            $vcalendar = self::getVcal($_blob);
            $result = $vcalendar->METHOD;
        }
        
        return $result;
    }

    /**
     * find a matching exdate or return an empty event record
     * 
     * @param  Tinebase_Record_RecordSet  $_oldExdates
     * @param  Sabre_VObject_Component    $_vevent
     * @return Calendar_Model_Event
     */
    protected function _getRecurException(Tinebase_Record_RecordSet $_oldExdates, Sabre_VObject_Component $_vevent)
    {
        // "-" is not allowed in property names
        $RECURRENCEID = 'RECURRENCE-ID';
        
        $exDate = clone $_vevent->$RECURRENCEID->getDateTime();
        $exDate->setTimeZone(new DateTimeZone('UTC'));
        $exDateString = $exDate->format('Y-m-d H:i:s');
        foreach ($_oldExdates as $id => $oldExdate) {
            if ($exDateString == substr((string) $oldExdate->recurid, -19)) {
                unset($_oldExdates[$id]);
                
                return $oldExdate;
            }
        }
        
        return new Calendar_Model_Event();
    }
    
    /**
     * get attendee object for given contact
     * 
     * @param Sabre_VObject_Property     $_attendee  the attendee row from the vevent object
     * @return array
     */
    protected function _getAttendee(Sabre_VObject_Property $_attendee)
    {
        if (isset($_attendee['CUTYPE']) && in_array($_attendee['CUTYPE']->value, array('INDIVIDUAL', Calendar_Model_Attender::USERTYPE_GROUP, Calendar_Model_Attender::USERTYPE_RESOURCE))) {
            $type = $_attendee['CUTYPE']->value == 'INDIVIDUAL' ? Calendar_Model_Attender::USERTYPE_USER : $_attendee['CUTYPE']->value;
        } else {
            $type = Calendar_Model_Attender::USERTYPE_USER;
        }
        
        if (isset($_attendee['ROLE']) && in_array($_attendee['ROLE']->value, array(Calendar_Model_Attender::ROLE_OPTIONAL, Calendar_Model_Attender::ROLE_REQUIRED))) {
            $role = $_attendee['ROLE']->value;
        } else {
            $role = Calendar_Model_Attender::ROLE_REQUIRED;
        }
        
        if (in_array($_attendee['PARTSTAT']->value, array(Calendar_Model_Attender::STATUS_ACCEPTED,
            Calendar_Model_Attender::STATUS_DECLINED,
            Calendar_Model_Attender::STATUS_NEEDSACTION,
            Calendar_Model_Attender::STATUS_TENTATIVE)
        )) {
            $status = $_attendee['PARTSTAT']->value;
        } else {
            $status = Calendar_Model_Attender::STATUS_NEEDSACTION;
        }
        
        if (isset($_attendee['EMAIL']) && !empty($_attendee['EMAIL']->value)) {
            $email = $_attendee['EMAIL']->value;
        } else {
            if (!preg_match('/(?P<protocol>mailto:|urn:uuid:)(?P<email>.*)/', $_attendee->value, $matches)) {
                throw new Tinebase_Exception_UnexpectedValue('invalid attendee provided: ' . $_attendee->value);
            }
            $email = $matches['email'];
        }
        
        $fullName = isset($_attendee['CN']) ? $_attendee['CN']->value : $email;
        
        if (preg_match('/(?P<firstName>\S*) (?P<lastNameName>\S*)/', $fullName, $matches)) {
            $firstName = $matches['firstName'];
            $lastName  = $matches['lastNameName'];
        } else {
            $firstName = null;
            $lastName  = $fullName;
        }

        $attendee = array(
            'userType'  => $type,
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'partStat'  => $status,
            'role'      => $role,
            'email'     => $email
        );
        
        return $attendee;
    }
    
    /**
     * parse VTODO part of VCALENDAR
     * 
     * @param  Sabre_VObject_Component  $_vevent  the VTODO to parse
     * @param  Tasks_Model_Task     $_event   the Tine 2.0 event to update
     */
    protected function _convertVevent(Sabre_VObject_Component $_vevent, Tasks_Model_Task $_event)
    {
        $event = $_event;
        $newAttendees = array();
        
        // unset supported fields
        foreach ($this->_supportedFields as $field) {
            if ($field == 'alarms') {
                $event->$field = new Tinebase_Record_RecordSet('Tinebase_Model_Alarm');
            } elseif($field == 'class') {
                     if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $field);
                     $event->$field = 'PUBLIC';
            } elseif($field == 'priority') {
                     if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $field);
                     $event->$field = 'NORMAL';
            } elseif($field == 'status') {
                     if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $field);
                     $event->$field = '';
            } else {
                     if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $field);
                     $event->$field = null;
            }
           
        }
        
        foreach($_vevent->children() as $property) {
            switch($property->name) {
                case 'CREATED':
                case 'DTSTAMP':
                    // do nothing
                    break;
                    
                case 'LAST-MODIFIED':
                    $event->last_modified_time = new Tinebase_DateTime($property->value);
                    break;
                
                case 'ATTENDEE':
                    $newAttendees[] = $this->_getAttendee($property);
                    break;
                    
                case 'CLASS':
                    if (in_array($property->value, array(Tasks_Model_Task::CLASS_PRIVATE, Tasks_Model_Task::CLASS_PUBLIC))) {
                        $event->class = $property->value;
                    } else {
                        $event->class = Tasks_Model_Task::CLASS_PUBLIC;
                    }
                    
                    break;
                    
                case 'COMPLETED':
                    
                    if (isset($property['VALUE']) && strtoupper($property['VALUE']) == 'DATE') {
                        // all day event
                        //$event->is_all_day_event = true;
                        $dtend = $this->_convertToTinebaseDateTime($property, TRUE);
                        
                        // whole day events ends at 23:59:59 in Tine 2.0 but 00:00 the next day in vcalendar
                        $dtend->subSecond(1);
                    } else {
                        //$event->is_all_day_event = false;
                        $dtend = $this->_convertToTinebaseDateTime($property);
                    }
                    
                    $event->completed = $dtend;
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' FLAMENGO ' . $event->completed);
                    break;
                    
                case 'DUE':
                    if (isset($property['VALUE']) && strtoupper($property['VALUE']) == 'DATE') {
                        // all day event
                        //$event->is_all_day_event = true;
                        $due = $this->_convertToTinebaseDateTime($property, TRUE);
                    } else {
                        //$event->is_all_day_event = false;
                        $due = $this->_convertToTinebaseDateTime($property);
                    }
                    $event->originator_tz = $due->getTimezone()->getName();
                    $event->due = $due;
                   if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' FLAMENGO2 ' . $event->due);
  
                    break;
                case 'DTSTART':
                    if (isset($property['VALUE']) && strtoupper($property['VALUE']) == 'DATE') {
                        // all day event
                        //$event->is_all_day_event = true;
                        $dtstart = $this->_convertToTinebaseDateTime($property, TRUE);
                        // whole day events ends at 23:59:59 in Tine 2.0 but 00:00 the next day in vcalendar
                        $dstart->subSecond(1);
                    } else {
                        //$event->is_all_day_event = false;
                        $dtstart = $this->_convertToTinebaseDateTime($property);
                    }
                    $event->start_time = $dtstart;
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' FLAMENGO ' . $event->dtstart);
                    break;
                case 'STATUS':
                     $event->status = $property->value;
                    break;   
                case 'PERCENT-COMPLETE':
                        $event->percent = $property->value;
                    break;       
                case 'SEQUENCE':
                    //$event->seq = $property->value;
                    break;
                case 'PRIORITY':
                         if(is_numeric($property->value)){
                           switch($property->value) {
                            case '0' :
                                 $event->priority = 'NORMAL';
                             break;
                            case '1' :
                                 $event->priority = 'HIGH';
                              break;
                            case '9' :
                                 $event->priority = 'LOW';
                              break;
                           }
                         }
                        else{$event->priority = $property->value;}
                    break;
                case 'DESCRIPTION':
                case 'LOCATION':
                case 'SUMMARY':
                    $key = strtolower($property->name);
                    $event->$key = $property->value;
                    break;
                    
                case 'ORGANIZER':
                    if (preg_match('/mailto:(?P<email>.*)/i', $property->value, $matches)) {
                        // it's not possible to change the organizer by spec
                        if (empty($event->organizer)) {
                            $name = isset($property['CN']) ? $property['CN']->value : $matches['email'];
                            $contact = Calendar_Model_Attender::resolveEmailToContact(array(
                                'email'     => $matches['email'],
                                'lastName'  => $name,
                            ));
                        
                            $event->organizer = $contact->getId();
                        }
                        
                        // Lightning attaches organizer ATTENDEE properties to ORGANIZER property and does not add an ATTENDEE for the organizer
                        if (isset($property['PARTSTAT'])) {
                            $newAttendees[] = $this->_getAttendee($property);
                        }
                    }
                    
                    break;

                case 'RECURRENCE-ID':
                    // original start of the event
                    $event->recurid = $this->_convertToTinebaseDateTime($property);
                    
                    // convert recurrence id to utc
                    $event->recurid->setTimezone('UTC');
                    
                    break;
                    
                case 'RRULE':
                    $event->rrule = $property->value;
                    
                    // convert date format
                    $event->rrule = preg_replace_callback('/UNTIL=([\dTZ]+)(?=;?)/', function($matches) {
                        if (strlen($matches[1]) < 10) {
                            $dtUntil = date_create($matches[1], new DateTimeZone ((string) Tinebase_Core::get(Tinebase_Core::USERTIMEZONE)));
                            $dtUntil->setTimezone(new DateTimeZone('UTC'));
                        } else {
                            $dtUntil = date_create($matches[1]);
                        }
                        
                        return 'UNTIL=' . $dtUntil->format(Tinebase_Record_Abstract::ISO8601LONG);
                    }, $event->rrule);

                    // remove additional days from BYMONTHDAY property
                    $event->rrule = preg_replace('/(BYMONTHDAY=)([\d]+)([,\d]+)/', '$1$2', $event->rrule);
                    
                    // process exceptions
                    if (isset($_vevent->EXDATE)) {
                        $exdates = new Tinebase_Record_RecordSet('Tasks_Model_Task');
                        
                        foreach($_vevent->EXDATE as $exdate) {
                            foreach($exdate->getDateTimes() as $exception) {
                                if (isset($exdate['VALUE']) && strtoupper($exdate['VALUE']) == 'DATE') {
                                    $recurid = new Tinebase_DateTime($exception->format(Tinebase_Record_Abstract::ISO8601LONG), (string) Tinebase_Core::get(Tinebase_Core::USERTIMEZONE));
                                } else {
                                    $recurid = new Tinebase_DateTime($exception->format(Tinebase_Record_Abstract::ISO8601LONG), $exception->getTimezone());
                                }
                                $recurid->setTimezone(new DateTimeZone('UTC'));
                                                        
                                $eventException = new Calendar_Model_Event(array(
                                    'recurid'    => $recurid,
                                    'is_deleted' => true
                                ));
                        
                                $exdates->addRecord($eventException);
                            }
                        }
                    
                        $event->exdate = $exdates;
                    }     
                                   
                    break;
                    
                case 'TRANSP':
                    if (in_array($property->value, array(Calendar_Model_Event::TRANSP_OPAQUE, Calendar_Model_Event::TRANSP_TRANSP))) {
                        $event->transp = $property->value;
                    } else {
                        $event->transp = Calendar_Model_Event::TRANSP_TRANSP;
                    }
                    
                    break;
                    
                case 'UID':
                    // it's not possible to change the uid by spec
                    if (!empty($event->uid)) {
                        continue;
                    }
                    
                    $event->id = $property->value;
                
                    break;
                    
                case 'VALARM':
                    foreach($property as $valarm) {
                        switch(strtoupper($valarm->TRIGGER['VALUE']->value)) {
                            # TRIGGER;VALUE=DATE-TIME:20111031T130000Z
                            case 'DATE-TIME':
                                //@TODO fixme
                                $alarmTime = new Tinebase_DateTime($valarm->TRIGGER->value);
                                $alarmTime->setTimezone('UTC');
                                
                                $alarm = new Tinebase_Model_Alarm(array(
                                    'alarm_time'        => $alarmTime,
                                    'minutes_before'    => 'custom',
                                    'model'             => 'Tasks_Model_Task'
                                ));
                                
                                $event->alarms->addRecord($alarm);
                                
                                break;
                                
                            # TRIGGER;VALUE=DURATION:-PT1H15M
                            case 'DURATION':
                            default:
                                $alarmTime = $this->_convertToTinebaseDateTime($_vevent->DTSTART);
                                $alarmTime->setTimezone('UTC');
                                
                                preg_match('/(?P<invert>[+-]?)(?P<spec>P.*)/', $valarm->TRIGGER->value, $matches);
                                $duration = new DateInterval($matches['spec']);
                                $duration->invert = !!($matches['invert'] === '-');

                                $alarm = new Tinebase_Model_Alarm(array(
                                    'alarm_time'        => $alarmTime->add($duration),
                                    'minutes_before'    => ($duration->format('%d') * 60 * 24) + ($duration->format('%h') * 60) + ($duration->format('%i')),
                                    'model'             => 'Tasks_Model_Task'
                                ));
                                
                                $event->alarms->addRecord($alarm);
                                
                                break;
                        }
                    }
                    
                    break;
                    
                case 'CATEGORIES':
                    // @todo handle categories
                    break;
                    
                case 'X-MOZ-LASTACK':
                    $lastAck = $this->_convertToTinebaseDateTime($property);
                    break;
                    
                case 'X-MOZ-SNOOZE-TIME':
                    $snoozeTime = $this->_convertToTinebaseDateTime($property);
                    break;
                    
                default:
                
                    break;
            }
        }
        
        // merge old and new attendees
        //Calendar_Model_Attender::emailsToAttendee($event, $newAttendees);
        
        //if (($ownAttendee = Calendar_Model_Attender::getOwnAttender($event->attendee)) !== null) {
        //    if (isset($lastAck)) {
        //        $ownAttendee->alarm_ack_time = $lastAck;
        //    }
        //    if (isset($snoozeTime)) {
        //        $ownAttendee->alarm_snooze_time = $snoozeTime;
        //    }
        //}
        
        if (empty($event->percent)) {
            $event->percent = 0;
        }
        if (empty($event->organizer))
        {
              $event->organizer = Tinebase_Core::getUser()->accountId;
        }
        if (empty($event->class)) {
            $event->class = Tasks_Model_Task::CLASS_PUBLIC;
        }
        
        // convert all datetime fields to UTC
        $event->setTimezone('UTC');
    }
    
    /**
     * get datetime from sabredav datetime property (user TZ is fallback)
     * 
     * @param Sabre_VObject_Element_DateTime $dateTime
     * @param boolean $_useUserTZ
     * @return Tinebase_DateTime
     * 
     * @todo try to guess some common timezones
     */
    protected function _convertToTinebaseDateTime(Sabre_VObject_Element_DateTime $dateTimeProperty, $_useUserTZ = FALSE)
    {
        try {
            $dateTime = $dateTimeProperty->getDateTime();
        } catch (Exception $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Error: ' . $e->getMessage());
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))  Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
            $dateTimeProperty['TZID'] = (string) Tinebase_Core::get(Tinebase_Core::USERTIMEZONE);
            $dateTime = $dateTimeProperty->getDateTime();
        }
        $tz = ($_useUserTZ) ? (string) Tinebase_Core::get(Tinebase_Core::USERTIMEZONE) : $dateTime->getTimezone();
        $result = new Tinebase_DateTime($dateTime->format(Tinebase_Record_Abstract::ISO8601LONG), $tz);
        
        return $result;
    }
}
